<?php

namespace Tests\Feature\Employee;

use App\Modules\Company\Models\Company;
use App\Modules\Employee\Models\Employee;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Employee Profile (self-service) -- General tab ala Mekari Talenta.
 * GET/PUT /api/my-profile. Tanpa permission khusus (employee biasa tanpa
 * "view employees" tetap bisa akses punya sendiri), object-level check
 * ada di EmployeeProfileController, bukan EmployeePolicy.
 *
 * Testing minimal: happy path view + edit, plus 1 guard krusial (field
 * lifecycle tidak bisa diubah lewat endpoint ini sama sekali).
 */
class EmployeeSelfServiceProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_can_view_own_profile(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $company = Company::factory()->create();
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'phone' => '081234567890',
        ]);
        $employee->user->assignRole('employee');

        $response = $this->actingAs($employee->user)->getJson('/api/my-profile');

        $response->assertOk();
        $this->assertSame($employee->id, $response->json('data.id'));
        $this->assertSame('081234567890', $response->json('data.phone'));
    }

    public function test_employee_can_update_own_personal_fields(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $employee = Employee::factory()->create(['phone' => '081111111111']);
        $employee->user->assignRole('employee');

        $response = $this->actingAs($employee->user)->putJson('/api/my-profile', [
            'phone' => '089999999999',
            'address' => 'Jl. Contoh No. 1',
            'emergency_contact_name' => 'Budi',
            'emergency_contact_phone' => '087777777777',
        ]);

        $response->assertOk();
        $this->assertSame('089999999999', $employee->fresh()->phone);
        $this->assertSame('Jl. Contoh No. 1', $employee->fresh()->address);
        $this->assertSame('Budi', $employee->fresh()->emergency_contact_name);
    }

    /**
     * Guard krusial: field lifecycle (WAJIB lewat Employee Movement) sama
     * sekali tidak ada di whitelist UpdateOwnProfileRequest, jadi meskipun
     * dikirim lewat endpoint self-service ini, harus diam-diam diabaikan
     * -- bukan diterapkan, dan bukan juga 422 (karena endpoint ini bukan
     * tempat validasi lifecycle, field itu memang bukan urusannya).
     */
    public function test_lifecycle_fields_are_ignored_via_self_service_update(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $originalCompany = Company::factory()->create();
        $otherCompany = Company::factory()->create();

        $employee = Employee::factory()->create(['company_id' => $originalCompany->id]);
        $employee->user->assignRole('employee');

        $response = $this->actingAs($employee->user)->putJson('/api/my-profile', [
            'phone' => '080000000000',
            'company_id' => $otherCompany->id,
            'resign_date' => now()->toDateString(),
        ]);

        $response->assertOk();
        $this->assertSame('080000000000', $employee->fresh()->phone);
        $this->assertSame($originalCompany->id, $employee->fresh()->company_id);
        $this->assertNull($employee->fresh()->resign_date);
    }
}