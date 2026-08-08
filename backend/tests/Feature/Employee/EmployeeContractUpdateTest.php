<?php

namespace Tests\Feature\Employee;

use App\Models\User;
use App\Modules\Employee\Models\Employee;
use App\Modules\EmploymentType\Models\EmploymentType;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeContractUpdateTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): User
    {
        $this->seed(RolePermissionSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    /**
     * Regression test: sebelum fix, employment_type_id/contract_start_date/
     * contract_end_date/probation_end_date hilang begitu saja dari hasil
     * validated() di UpdateEmployeeRequest, jadi $employee->update() tidak
     * pernah menyentuh field-field ini walau request-nya sukses (200).
     */
    public function test_update_persists_employment_type_and_contract_dates(): void
    {
        $admin = $this->makeAdmin();

        $employee = Employee::factory()->create();
        $contractType = EmploymentType::where('code', 'CONTRACT')->firstOrFail();

        $payload = $this->baseUpdatePayload($employee);
        $payload['employment_type_id'] = $contractType->id;
        $payload['contract_start_date'] = '2026-08-01';
        $payload['contract_end_date'] = '2027-08-01';
        $payload['probation_end_date'] = '2026-11-01';

        $response = $this->actingAs($admin)
            ->putJson("/api/employees/{$employee->id}", $payload);

        $response->assertOk();

        $employee->refresh();

        $this->assertSame($contractType->id, $employee->employment_type_id);
        $this->assertSame('2026-08-01', $employee->contract_start_date->toDateString());
        $this->assertSame('2027-08-01', $employee->contract_end_date->toDateString());
        $this->assertSame('2026-11-01', $employee->probation_end_date->toDateString());
    }

    /**
     * Sama seperti StoreEmployeeRequest: employment_type CONTRACT tanpa
     * contract_start_date/contract_end_date harus ditolak (bukan cuma di
     * Store, sekarang Update juga menegakkan aturan yang sama).
     */
    public function test_update_requires_contract_dates_when_type_is_contract(): void
    {
        $admin = $this->makeAdmin();

        $employee = Employee::factory()->create();
        $contractType = EmploymentType::where('code', 'CONTRACT')->firstOrFail();

        $payload = $this->baseUpdatePayload($employee);
        $payload['employment_type_id'] = $contractType->id;
        // contract_start_date / contract_end_date sengaja tidak diisi.

        $response = $this->actingAs($admin)
            ->putJson("/api/employees/{$employee->id}", $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['contract_start_date', 'contract_end_date']);
    }

    /**
     * probation_end_date harus bisa diisi berapapun employment_type-nya
     * (termasuk tanpa employment_type sama sekali) — probation independen
     * dari jenis hubungan kerja.
     */
    public function test_probation_end_date_is_independent_of_employment_type(): void
    {
        $admin = $this->makeAdmin();

        $employee = Employee::factory()->create();
        $permanentType = EmploymentType::where('code', 'PERMANENT')->firstOrFail();

        $payload = $this->baseUpdatePayload($employee);
        $payload['employment_type_id'] = $permanentType->id;
        $payload['probation_end_date'] = '2026-09-30';

        $response = $this->actingAs($admin)
            ->putJson("/api/employees/{$employee->id}", $payload);

        $response->assertOk();
        $this->assertSame('2026-09-30', $employee->refresh()->probation_end_date->toDateString());
    }

    /**
     * @return array<string, mixed>
     */
    private function baseUpdatePayload(Employee $employee): array
    {
        return [
            'employee_number' => $employee->employee_number,
            'company_id' => $employee->company_id,
            'user_id' => $employee->user_id,
            'join_date' => $employee->join_date->toDateString(),
            'first_name' => $employee->first_name,
            'last_name' => $employee->last_name,
            'gender' => $employee->gender,
        ];
    }
}
