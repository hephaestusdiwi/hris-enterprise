<?php

namespace Tests\Feature\CompanyDocument;

use App\Models\User;
use App\Modules\Employee\Models\Employee;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CompanyDocumentTest extends TestCase
{
    use RefreshDatabase;

    public function test_hr_can_upload_company_wide_public_document(): void
    {
        Storage::fake('public');
        $this->seed(RolePermissionSeeder::class);

        $hrUser = User::factory()->create();
        $hrUser->assignRole('hr');

        $response = $this->actingAs($hrUser)->postJson('/api/company-documents', [
            'category' => 'sop',
            'title' => 'SOP Cuti Tahunan',
            'document' => UploadedFile::fake()->create('sop-cuti.pdf', 200, 'application/pdf'),
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.visibility', 'public');
        $this->assertDatabaseHas('company_documents', [
            'category' => 'sop',
            'employee_id' => null,
            'visibility' => 'public',
        ]);
    }

    public function test_employee_with_module_access_can_browse_public_documents(): void
    {
        Storage::fake('public');
        $this->seed(RolePermissionSeeder::class);

        $hrUser = User::factory()->create();
        $hrUser->assignRole('hr');
        $this->actingAs($hrUser)->postJson('/api/company-documents', [
            'category' => 'handbook',
            'title' => 'Employee Handbook 2026',
            'document' => UploadedFile::fake()->create('handbook.pdf', 300, 'application/pdf'),
        ]);

        // employee biasa TIDAK dikasih 'view company documents' secara default
        // di seeder -- jadi kita kasih permission itu manual buat simulasikan
        // "employee yang punya akses ke modul tersebut".
        $employeeUser = User::factory()->create();
        $employeeUser->assignRole('employee');
        $employeeUser->givePermissionTo('view company documents');

        $this->actingAs($employeeUser)
            ->getJson('/api/company-documents')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_employee_without_permission_cannot_browse_public_documents(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $employee = Employee::factory()->create();
        $employee->user->assignRole('employee');

        $this->actingAs($employee->user)
            ->getJson('/api/company-documents')
            ->assertForbidden();
    }

    public function test_hr_can_upload_private_document_for_employee(): void
    {
        Storage::fake('public');
        $this->seed(RolePermissionSeeder::class);

        $hrUser = User::factory()->create();
        $hrUser->assignRole('hr');

        $employee = Employee::factory()->create();

        $response = $this->actingAs($hrUser)->postJson("/api/employees/{$employee->id}/company-documents", [
            'category' => 'ktp',
            'title' => 'KTP an. '.$employee->first_name,
            'document' => UploadedFile::fake()->create('ktp.jpg', 200, 'image/jpeg'),
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.visibility', 'private');
    }

    /**
     * Guard krusial (poin 2A dari user): permission buat LIHAT kategori
     * private FIXED per kategori, gak bisa "dibuka" cuma karena punya
     * permission private LAIN. Punya akses ke financial_documents
     * TIDAK otomatis boleh lihat identity_documents (KTP).
     */
    public function test_view_permission_for_private_categories_is_fixed_per_category_not_shared(): void
    {
        Storage::fake('public');
        $this->seed(RolePermissionSeeder::class);

        $hrUser = User::factory()->create();
        $hrUser->assignRole('hr');
        $employee = Employee::factory()->create();

        $this->actingAs($hrUser)->postJson("/api/employees/{$employee->id}/company-documents", [
            'category' => 'ktp',
            'title' => 'KTP Karyawan',
            'document' => UploadedFile::fake()->create('ktp.jpg', 100, 'image/jpeg'),
        ]);
        $this->actingAs($hrUser)->postJson("/api/employees/{$employee->id}/company-documents", [
            'category' => 'bank_account',
            'title' => 'Rekening Karyawan',
            'document' => UploadedFile::fake()->create('rekening.jpg', 100, 'image/jpeg'),
        ]);

        // User cuma dikasih 'view financial documents', BUKAN 'view identity documents'.
        $financeUser = User::factory()->create();
        $financeUser->givePermissionTo('view financial documents');

        $response = $this->actingAs($financeUser)->getJson("/api/employees/{$employee->id}/company-documents");

        $response->assertOk();
        $response->assertJsonCount(1, 'data'); // cuma rekening yang keliatan, KTP kefilter
        $this->assertSame('bank_account', $response->json('data.0.category'));
    }

    /**
     * Guard krusial: uploader TIDAK BISA memilih permission bebas --
     * kategori 'disciplinary' otomatis private + butuh
     * 'view disciplinary documents', walau yang upload adalah HR yang
     * juga punya 'manage private documents'. Endpoint tidak menerima
     * field 'visibility' atau 'permission' dari client sama sekali.
     */
    public function test_visibility_and_permission_are_derived_from_category_not_client_input(): void
    {
        Storage::fake('public');
        $this->seed(RolePermissionSeeder::class);

        $hrUser = User::factory()->create();
        $hrUser->assignRole('hr');
        $employee = Employee::factory()->create();

        $response = $this->actingAs($hrUser)->postJson("/api/employees/{$employee->id}/company-documents", [
            'category' => 'disciplinary',
            'title' => 'SP Terlampir',
            'visibility' => 'public', // dicoba nge-override, harus diabaikan
            'document' => UploadedFile::fake()->create('sp.pdf', 100, 'application/pdf'),
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('company_documents', [
            'category' => 'disciplinary',
            'visibility' => 'private', // tetap private, override diabaikan
        ]);
    }

    public function test_employee_can_view_own_documents_regardless_of_category_permission(): void
    {
        Storage::fake('public');
        $this->seed(RolePermissionSeeder::class);

        $hrUser = User::factory()->create();
        $hrUser->assignRole('hr');

        $employee = Employee::factory()->create();
        $employee->user->assignRole('employee');

        $this->actingAs($hrUser)->postJson("/api/employees/{$employee->id}/company-documents", [
            'category' => 'ktp',
            'title' => 'KTP Karyawan',
            'document' => UploadedFile::fake()->create('ktp.jpg', 100, 'image/jpeg'),
        ]);

        // employee TIDAK punya 'view identity documents', tapi ini dokumen miliknya sendiri.
        $this->actingAs($employee->user)
            ->getJson('/api/my-company-documents')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_category_cannot_be_changed_on_update(): void
    {
        Storage::fake('public');
        $this->seed(RolePermissionSeeder::class);

        $hrUser = User::factory()->create();
        $hrUser->assignRole('hr');

        $created = $this->actingAs($hrUser)->postJson('/api/company-documents', [
            'category' => 'sop',
            'title' => 'SOP Awal',
            'document' => UploadedFile::fake()->create('sop.pdf', 100, 'application/pdf'),
        ])->json('data');

        $this->actingAs($hrUser)->postJson("/api/company-documents/{$created['id']}", [
            'category' => 'ktp', // dicoba, harus diabaikan
            'title' => 'SOP Direvisi',
        ])->assertOk();

        $this->assertDatabaseHas('company_documents', [
            'id' => $created['id'],
            'category' => 'sop',
            'title' => 'SOP Direvisi',
        ]);
    }

    public function test_employee_without_manage_permission_cannot_upload_private_document(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $employee = Employee::factory()->create();
        $employee->user->assignRole('employee');
        $target = Employee::factory()->create();

        $this->actingAs($employee->user)->postJson("/api/employees/{$target->id}/company-documents", [
            'category' => 'ktp',
            'title' => 'Coba Upload',
            'document' => UploadedFile::fake()->create('ktp.jpg', 100, 'image/jpeg'),
        ])->assertForbidden();
    }
}