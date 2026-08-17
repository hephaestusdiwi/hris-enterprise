<?php

namespace Tests\Feature\Announcement;

use App\Models\User;
use App\Modules\Announcement\Models\Announcement;
use App\Modules\Announcement\Models\AnnouncementCategory;
use App\Modules\Announcement\Models\AnnouncementRecipient;
use App\Modules\Announcement\Services\AnnouncementService;
use App\Modules\Branch\Models\Branch;
use App\Modules\Employee\Models\Employee;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class AnnouncementFlowTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    public function test_create_announcement_as_draft(): void
    {
        $admin = $this->admin();
        $category = AnnouncementCategory::factory()->create();

        $response = $this->actingAs($admin)->postJson('/api/announcements', [
            'title' => 'Libur Nasional',
            'content' => 'Kantor libur tanggal 17 Agustus.',
            'announcement_category_id' => $category->id,
            'target_type' => 'all',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.status', 'draft');
        $this->assertDatabaseCount('announcement_recipients', 0);
    }

    public function test_publish_all_employees_creates_recipient_for_every_active_employee(): void
    {
        $admin = $this->admin();
        $category = AnnouncementCategory::factory()->create();

        Employee::factory()->count(3)->create();
        Employee::factory()->create(['resign_date' => now()->subDay()->toDateString()]); // resigned, TIDAK boleh jadi recipient

        $announcement = app(AnnouncementService::class)->create([
            'title' => 'Pengumuman',
            'content' => 'Isi',
            'announcement_category_id' => $category->id,
            'target_type' => 'all',
        ], $admin);

        $this->actingAs($admin)->postJson("/api/announcements/{$announcement->id}/publish")->assertOk();

        $this->assertSame(3, AnnouncementRecipient::where('announcement_id', $announcement->id)->count());
    }

    public function test_publish_with_criteria_only_targets_matching_employees(): void
    {
        $admin = $this->admin();
        $category = AnnouncementCategory::factory()->create();
        $branch = Branch::factory()->create();

        $inBranch = Employee::factory()->create(['branch_id' => $branch->id]);
        Employee::factory()->create(); // branch beda, TIDAK boleh jadi recipient

        $announcement = app(AnnouncementService::class)->create([
            'title' => 'Pengumuman Cabang',
            'content' => 'Isi',
            'announcement_category_id' => $category->id,
            'target_type' => 'criteria',
            'targets' => [['type' => 'branch', 'id' => $branch->id]],
        ], $admin);

        $this->actingAs($admin)->postJson("/api/announcements/{$announcement->id}/publish")->assertOk();

        $recipientIds = AnnouncementRecipient::where('announcement_id', $announcement->id)->pluck('employee_id')->all();
        $this->assertSame([$inBranch->id], $recipientIds);
    }

    public function test_read_unread_state(): void
    {
        $admin = $this->admin();
        $category = AnnouncementCategory::factory()->create();

        $employeeUser = User::factory()->create();
        $employeeUser->assignRole('employee');
        $employee = Employee::factory()->create(['user_id' => $employeeUser->id]);

        $announcement = app(AnnouncementService::class)->create([
            'title' => 'Pengumuman',
            'content' => 'Isi',
            'announcement_category_id' => $category->id,
            'target_type' => 'all',
        ], $admin);
        app(AnnouncementService::class)->publish($announcement);

        $recipient = AnnouncementRecipient::where('announcement_id', $announcement->id)->where('employee_id', $employee->id)->first();
        $this->assertNull($recipient->read_at);

        $this->actingAs($employeeUser)->postJson("/api/announcements/{$announcement->id}/read")->assertOk();

        $this->assertNotNull($recipient->fresh()->read_at);
    }

    public function test_category_search_filter_on_management_list(): void
    {
        $admin = $this->admin();
        $catA = AnnouncementCategory::factory()->create(['name' => 'Umum']);
        $catB = AnnouncementCategory::factory()->create(['name' => 'HR']);

        app(AnnouncementService::class)->create([
            'title' => 'Announcement A', 'content' => 'x', 'announcement_category_id' => $catA->id, 'target_type' => 'all',
        ], $admin);
        app(AnnouncementService::class)->create([
            'title' => 'Announcement B', 'content' => 'x', 'announcement_category_id' => $catB->id, 'target_type' => 'all',
        ], $admin);

        $response = $this->actingAs($admin)->getJson("/api/announcements?category_id={$catA->id}");
        $response->assertOk();
        $this->assertCount(1, $response->json('data.data'));
    }

    public function test_attachment_upload_validates_mime_and_size(): void
    {
        $admin = $this->admin();
        $category = AnnouncementCategory::factory()->create();
        $announcement = app(AnnouncementService::class)->create([
            'title' => 'x', 'content' => 'x', 'announcement_category_id' => $category->id, 'target_type' => 'all',
        ], $admin);

        $invalid = UploadedFile::fake()->create('malware.exe', 100);
        $this->actingAs($admin)
            ->post("/api/announcements/{$announcement->id}/attachments", ['file' => $invalid])
            ->assertStatus(422);

        $valid = UploadedFile::fake()->create('brosur.pdf', 500, 'application/pdf');
        $this->actingAs($admin)
            ->post("/api/announcements/{$announcement->id}/attachments", ['file' => $valid])
            ->assertStatus(201);
    }

    public function test_employee_role_cannot_create_announcement(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('employee');
        $category = AnnouncementCategory::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/announcements', [
            'title' => 'x', 'content' => 'x', 'announcement_category_id' => $category->id, 'target_type' => 'all',
        ]);

        $response->assertForbidden();
    }

    public function test_publish_is_idempotent_and_never_creates_duplicate_recipients(): void
    {
        $admin = $this->admin();
        $category = AnnouncementCategory::factory()->create();
        Employee::factory()->count(2)->create();

        $announcement = app(AnnouncementService::class)->create([
            'title' => 'x', 'content' => 'x', 'announcement_category_id' => $category->id, 'target_type' => 'all',
        ], $admin);

        $service = app(AnnouncementService::class);
        $service->publish($announcement);
        $countAfterFirst = AnnouncementRecipient::where('announcement_id', $announcement->id)->count();

        $service->publish($announcement->fresh());
        $countAfterSecond = AnnouncementRecipient::where('announcement_id', $announcement->id)->count();

        $this->assertSame(2, $countAfterFirst);
        $this->assertSame($countAfterFirst, $countAfterSecond);
    }

    public function test_draft_announcement_can_upload_attachment(): void
    {
        $admin = $this->admin();
        $category = AnnouncementCategory::factory()->create();
        $announcement = app(AnnouncementService::class)->create([
            'title' => 'x', 'content' => 'x', 'announcement_category_id' => $category->id, 'target_type' => 'all',
        ], $admin);

        $file = UploadedFile::fake()->create('brosur.pdf', 500, 'application/pdf');
        $this->actingAs($admin)
            ->post("/api/announcements/{$announcement->id}/attachments", ['file' => $file])
            ->assertStatus(201);
    }

    public function test_published_announcement_cannot_upload_attachment(): void
    {
        $admin = $this->admin();
        $category = AnnouncementCategory::factory()->create();
        $announcement = app(AnnouncementService::class)->create([
            'title' => 'x', 'content' => 'x', 'announcement_category_id' => $category->id, 'target_type' => 'all',
        ], $admin);
        app(AnnouncementService::class)->publish($announcement);

        $file = UploadedFile::fake()->create('brosur.pdf', 500, 'application/pdf');
        $response = $this->actingAs($admin)
            ->post("/api/announcements/{$announcement->id}/attachments", ['file' => $file]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('announcement_attachments', 0);
    }

    public function test_draft_announcement_can_delete_own_attachment(): void
    {
        $admin = $this->admin();
        $category = AnnouncementCategory::factory()->create();
        $announcement = app(AnnouncementService::class)->create([
            'title' => 'x', 'content' => 'x', 'announcement_category_id' => $category->id, 'target_type' => 'all',
        ], $admin);

        $file = UploadedFile::fake()->create('brosur.pdf', 500, 'application/pdf');
        $uploadResponse = $this->actingAs($admin)->post("/api/announcements/{$announcement->id}/attachments", ['file' => $file]);
        $attachmentId = $uploadResponse->json('data.id');

        $this->actingAs($admin)
            ->delete("/api/announcements/{$announcement->id}/attachments/{$attachmentId}")
            ->assertOk();

        $this->assertDatabaseCount('announcement_attachments', 0);
    }

    public function test_published_announcement_cannot_delete_attachment(): void
    {
        $admin = $this->admin();
        $category = AnnouncementCategory::factory()->create();
        $announcement = app(AnnouncementService::class)->create([
            'title' => 'x', 'content' => 'x', 'announcement_category_id' => $category->id, 'target_type' => 'all',
        ], $admin);

        $file = UploadedFile::fake()->create('brosur.pdf', 500, 'application/pdf');
        $uploadResponse = $this->actingAs($admin)->post("/api/announcements/{$announcement->id}/attachments", ['file' => $file]);
        $attachmentId = $uploadResponse->json('data.id');

        app(AnnouncementService::class)->publish($announcement->fresh());

        $response = $this->actingAs($admin)
            ->delete("/api/announcements/{$announcement->id}/attachments/{$attachmentId}");

        $response->assertStatus(422);
        $this->assertDatabaseCount('announcement_attachments', 1); // masih ada, tidak jadi kehapus
    }

    public function test_attachment_of_announcement_a_cannot_be_deleted_via_announcement_b(): void
    {
        $admin = $this->admin();
        $category = AnnouncementCategory::factory()->create();

        $announcementA = app(AnnouncementService::class)->create([
            'title' => 'A', 'content' => 'x', 'announcement_category_id' => $category->id, 'target_type' => 'all',
        ], $admin);
        $announcementB = app(AnnouncementService::class)->create([
            'title' => 'B', 'content' => 'x', 'announcement_category_id' => $category->id, 'target_type' => 'all',
        ], $admin);

        $file = UploadedFile::fake()->create('brosur.pdf', 500, 'application/pdf');
        $uploadResponse = $this->actingAs($admin)->post("/api/announcements/{$announcementA->id}/attachments", ['file' => $file]);
        $attachmentId = $uploadResponse->json('data.id');

        // Coba hapus attachment milik A lewat URL B.
        $response = $this->actingAs($admin)
            ->delete("/api/announcements/{$announcementB->id}/attachments/{$attachmentId}");

        $response->assertStatus(422);
        $this->assertDatabaseCount('announcement_attachments', 1); // masih ada
        $this->assertDatabaseHas('announcement_attachments', ['id' => $attachmentId, 'announcement_id' => $announcementA->id]);
    }

    public function test_update_category_with_same_code_still_succeeds(): void
    {
        $admin = $this->admin();
        $category = AnnouncementCategory::factory()->create(['code' => 'UMUM', 'name' => 'Umum']);

        $response = $this->actingAs($admin)->putJson("/api/announcement-categories/{$category->id}", [
            'name' => 'Umum (updated)',
            'code' => 'UMUM', // code SAMA, tidak berubah
            'is_active' => true,
        ]);

        $response->assertOk();
        $this->assertSame('Umum (updated)', $category->fresh()->name);
    }

    public function test_update_category_with_another_categorys_code_fails(): void
    {
        $admin = $this->admin();
        AnnouncementCategory::factory()->create(['code' => 'HR']);
        $category = AnnouncementCategory::factory()->create(['code' => 'UMUM']);

        $response = $this->actingAs($admin)->putJson("/api/announcement-categories/{$category->id}", [
            'name' => 'Umum',
            'code' => 'HR', // punya category lain
            'is_active' => true,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['code']);
    }
}
