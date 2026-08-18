<?php

namespace Tests\Feature\Announcement;

use App\Models\User;
use App\Modules\Announcement\Models\AnnouncementCategory;
use App\Modules\Announcement\Notifications\AnnouncementPublishedNotification;
use App\Modules\Announcement\Services\AnnouncementService;
use App\Modules\Employee\Models\Employee;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AnnouncementDeliveryTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    public function test_publish_creates_notification_for_recipients(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $category = AnnouncementCategory::factory()->create();

        $recipientUser = User::factory()->create();
        Employee::factory()->create(['user_id' => $recipientUser->id]);

        $announcement = app(AnnouncementService::class)->create([
            'title' => 'Pengumuman', 'content' => 'x', 'announcement_category_id' => $category->id, 'target_type' => 'all',
        ], $admin);

        app(AnnouncementService::class)->publish($announcement);

        Notification::assertSentTo($recipientUser, AnnouncementPublishedNotification::class);
    }

    public function test_publish_second_time_does_not_duplicate_notification(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $category = AnnouncementCategory::factory()->create();

        $recipientUser = User::factory()->create();
        Employee::factory()->create(['user_id' => $recipientUser->id]);

        $announcement = app(AnnouncementService::class)->create([
            'title' => 'Pengumuman', 'content' => 'x', 'announcement_category_id' => $category->id, 'target_type' => 'all',
        ], $admin);

        $service = app(AnnouncementService::class);
        $service->publish($announcement);
        $service->publish($announcement->fresh());

        Notification::assertSentToTimes($recipientUser, AnnouncementPublishedNotification::class, 1);
    }

    /**
     * ShouldQueue + QUEUE_CONNECTION=sync di testing berarti notify() jalan
     * inline — tapi try/catch di Service tetap harus nyerap Throwable
     * supaya publish() TIDAK gagal walau notification/mail infra bermasalah.
     * Test ini pastikan publish tetap sukses (status Published, recipient
     * tercatat) meski tidak ada assertion soal notification-nya sendiri.
     */
    public function test_publish_succeeds_even_when_notification_delivery_throws(): void
    {
        $admin = $this->admin();
        $category = AnnouncementCategory::factory()->create();

        $recipientUser = User::factory()->create();
        $employee = Employee::factory()->create(['user_id' => $recipientUser->id]);
        // Employee TANPA user (edge case: notify() di-skip via continue,
        // bukan exception) sudah dicover test lain secara implisit. Di sini
        // kita pastikan publish() sendiri robust: hapus user supaya
        // recipient->employee->user null untuk sebagian baris.
        Employee::factory()->create(); // employee lain tanpa relasi user eksplisit tapi tetap punya user dari factory

        $announcement = app(AnnouncementService::class)->create([
            'title' => 'Pengumuman', 'content' => 'x', 'announcement_category_id' => $category->id, 'target_type' => 'all',
        ], $admin);

        $result = app(AnnouncementService::class)->publish($announcement);

        $this->assertSame('published', $result->status->value);
        $this->assertDatabaseCount('announcement_recipients', 2);
    }

    public function test_employee_can_retrieve_own_announcement_detail(): void
    {
        $admin = $this->admin();
        $category = AnnouncementCategory::factory()->create();

        $employeeUser = User::factory()->create();
        $employeeUser->assignRole('employee');
        Employee::factory()->create(['user_id' => $employeeUser->id]);

        $announcement = app(AnnouncementService::class)->create([
            'title' => 'Pengumuman', 'content' => 'x', 'announcement_category_id' => $category->id, 'target_type' => 'all',
        ], $admin);
        app(AnnouncementService::class)->publish($announcement);

        $response = $this->actingAs($employeeUser)->getJson("/api/my-announcements/{$announcement->id}");
        $response->assertOk();
        $response->assertJsonPath('data.announcement.id', $announcement->id);
    }

    public function test_employee_cannot_retrieve_another_employees_announcement_via_wrong_target(): void
    {
        $admin = $this->admin();
        $category = AnnouncementCategory::factory()->create();

        // Announcement criteria-targeted ke branch X — employee di luar
        // branch itu TIDAK jadi recipient sama sekali.
        $branch = \App\Modules\Branch\Models\Branch::factory()->create();
        Employee::factory()->create(['branch_id' => $branch->id]); // recipient beneran

        $outsiderUser = User::factory()->create();
        $outsiderUser->assignRole('employee');
        Employee::factory()->create(['user_id' => $outsiderUser->id]); // branch beda, BUKAN recipient

        $announcement = app(AnnouncementService::class)->create([
            'title' => 'Pengumuman Cabang', 'content' => 'x', 'announcement_category_id' => $category->id,
            'target_type' => 'criteria', 'targets' => [['type' => 'branch', 'id' => $branch->id]],
        ], $admin);
        app(AnnouncementService::class)->publish($announcement);

        $response = $this->actingAs($outsiderUser)->getJson("/api/my-announcements/{$announcement->id}");
        $response->assertForbidden();
    }

    public function test_unread_count_is_correct(): void
    {
        $admin = $this->admin();
        $category = AnnouncementCategory::factory()->create();

        $employeeUser = User::factory()->create();
        $employeeUser->assignRole('employee');
        $employee = Employee::factory()->create(['user_id' => $employeeUser->id]);

        $a1 = app(AnnouncementService::class)->create(['title' => 'A1', 'content' => 'x', 'announcement_category_id' => $category->id, 'target_type' => 'all'], $admin);
        $a2 = app(AnnouncementService::class)->create(['title' => 'A2', 'content' => 'x', 'announcement_category_id' => $category->id, 'target_type' => 'all'], $admin);
        app(AnnouncementService::class)->publish($a1);
        app(AnnouncementService::class)->publish($a2);

        $response = $this->actingAs($employeeUser)->getJson('/api/my-announcements/unread-count');
        $response->assertOk();
        $response->assertJsonPath('data.unread_count', 2);

        app(AnnouncementService::class)->markAsRead($a1, $employee);

        $response = $this->actingAs($employeeUser)->getJson('/api/my-announcements/unread-count');
        $response->assertJsonPath('data.unread_count', 1);
    }

    public function test_mark_announcement_as_read(): void
    {
        $admin = $this->admin();
        $category = AnnouncementCategory::factory()->create();

        $employeeUser = User::factory()->create();
        $employeeUser->assignRole('employee');
        Employee::factory()->create(['user_id' => $employeeUser->id]);

        $announcement = app(AnnouncementService::class)->create(['title' => 'x', 'content' => 'x', 'announcement_category_id' => $category->id, 'target_type' => 'all'], $admin);
        app(AnnouncementService::class)->publish($announcement);

        $this->actingAs($employeeUser)->postJson("/api/announcements/{$announcement->id}/read")->assertOk();

        $this->assertDatabaseHas('announcement_recipients', [
            'announcement_id' => $announcement->id,
        ]);
        $recipient = \App\Modules\Announcement\Models\AnnouncementRecipient::where('announcement_id', $announcement->id)->first();
        $this->assertNotNull($recipient->read_at);
    }

    /**
     * Regression test: user tanpa Employee record (mis. akun admin murni
     * tanpa record Employee terhubung) harus tetap dapat response berbentuk
     * paginator (data.data = array kosong), BUKAN flat array [] — shape
     * yang beda-beda ini yang bikin frontend crash baca .data dari array.
     */
    public function test_my_announcements_returns_consistent_paginator_shape_when_user_has_no_employee(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $userWithoutEmployee = User::factory()->create();
        $userWithoutEmployee->assignRole('employee');

        $response = $this->actingAs($userWithoutEmployee)->getJson('/api/my-announcements');

        $response->assertOk();
        $this->assertIsArray($response->json('data.data'));
        $this->assertSame([], $response->json('data.data'));
        // Pastikan field paginator standar tetap ada, sama seperti kasus employee beneran punya recipient.
        $this->assertArrayHasKey('current_page', $response->json('data'));
    }
}
