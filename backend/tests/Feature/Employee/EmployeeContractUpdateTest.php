<?php

namespace Tests\Feature\Employee;

use App\Models\User;
use App\Modules\Employee\Models\Employee;
use App\Modules\EmploymentType\Models\EmploymentType;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sejak Employee Movement (Phase 2), employment_type_id TIDAK BISA lagi
 * diubah lewat UpdateEmployeeRequest — harus lewat
 * POST /employees/{employee}/movements (movement_type=contract_change).
 * Test di file ini karena itu tidak lagi mencoba mengubah employment_type_id
 * lewat PUT /employees/{id}; employment_type di-set langsung di factory
 * (setara "sudah begini dari awal"), lalu yang diuji cuma field yang MEMANG
 * masih boleh diubah lewat Edit: contract_start_date, contract_end_date,
 * probation_end_date.
 */
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
     * Regression test asli (Phase 1): contract_start_date/contract_end_date/
     * probation_end_date sempat hilang dari validated() dan tidak pernah
     * tersimpan walau request sukses. employment_type_id sengaja di-set
     * SAAT CREATE (bukan lewat payload update ini) karena field itu sekarang
     * lifecycle-controlled.
     */
    public function test_update_persists_contract_and_probation_dates(): void
    {
        $admin = $this->makeAdmin();

        $contractType = EmploymentType::where('code', 'CONTRACT')->firstOrFail();
        $employee = Employee::factory()->create(['employment_type_id' => $contractType->id]);

        $payload = $this->baseUpdatePayload($employee);
        $payload['contract_start_date'] = '2026-08-01';
        $payload['contract_end_date'] = '2027-08-01';
        $payload['probation_end_date'] = '2026-11-01';

        $response = $this->actingAs($admin)
            ->putJson("/api/employees/{$employee->id}", $payload);

        $response->assertOk();

        $employee->refresh();

        // employment_type_id TIDAK berubah (tidak pernah dikirim di payload,
        // dan memang tidak bisa diubah lewat endpoint ini) — tetap CONTRACT
        // dari factory.
        $this->assertSame($contractType->id, $employee->employment_type_id);
        $this->assertSame('2026-08-01', $employee->contract_start_date->toDateString());
        $this->assertSame('2027-08-01', $employee->contract_end_date->toDateString());
        $this->assertSame('2026-11-01', $employee->probation_end_date->toDateString());
    }

    /**
     * Sama seperti StoreEmployeeRequest: kalau employment type CURRENT-nya
     * CONTRACT, contract_start_date/contract_end_date tetap wajib diisi
     * waktu update — walau employment_type_id sendiri sudah tidak bisa
     * diubah lewat request ini. Validasi ini baca dari
     * $employee->employmentType (current state), bukan dari payload.
     */
    public function test_update_requires_contract_dates_when_current_type_is_contract(): void
    {
        $admin = $this->makeAdmin();

        $contractType = EmploymentType::where('code', 'CONTRACT')->firstOrFail();
        $employee = Employee::factory()->create(['employment_type_id' => $contractType->id]);

        $payload = $this->baseUpdatePayload($employee);
        // contract_start_date / contract_end_date sengaja tidak diisi.

        $response = $this->actingAs($admin)
            ->putJson("/api/employees/{$employee->id}", $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['contract_start_date', 'contract_end_date']);
    }

    /**
     * probation_end_date harus bisa diisi berapapun employment_type-nya
     * (termasuk PERMANENT) — probation independen dari jenis hubungan kerja.
     */
    public function test_probation_end_date_is_independent_of_employment_type(): void
    {
        $admin = $this->makeAdmin();

        $permanentType = EmploymentType::where('code', 'PERMANENT')->firstOrFail();
        $employee = Employee::factory()->create(['employment_type_id' => $permanentType->id]);

        $payload = $this->baseUpdatePayload($employee);
        $payload['probation_end_date'] = '2026-09-30';

        $response = $this->actingAs($admin)
            ->putJson("/api/employees/{$employee->id}", $payload);

        $response->assertOk();
        $this->assertSame('2026-09-30', $employee->refresh()->probation_end_date->toDateString());
        // employment_type_id tetap PERMANENT, tidak ikut berubah.
        $this->assertSame($permanentType->id, $employee->employment_type_id);
    }

    /**
     * Regression test Phase 2: percobaan menyelundupkan perubahan
     * employment_type_id lewat endpoint ini (bukan lewat Employee Movement)
     * harus ditolak, bukan diam-diam berhasil atau diam-diam diabaikan.
     */
    public function test_update_rejects_employment_type_change_attempt(): void
    {
        $admin = $this->makeAdmin();

        $permanentType = EmploymentType::where('code', 'PERMANENT')->firstOrFail();
        $contractType = EmploymentType::where('code', 'CONTRACT')->firstOrFail();
        $employee = Employee::factory()->create(['employment_type_id' => $permanentType->id]);

        $payload = $this->baseUpdatePayload($employee);
        $payload['employment_type_id'] = $contractType->id;

        $response = $this->actingAs($admin)
            ->putJson("/api/employees/{$employee->id}", $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['employment_type_id']);
        $this->assertSame($permanentType->id, $employee->refresh()->employment_type_id);
    }

    /**
     * @return array<string, mixed>
     */
    private function baseUpdatePayload(Employee $employee): array
    {
        return [
            'employee_number' => $employee->employee_number,
            'user_id' => $employee->user_id,
            'join_date' => $employee->join_date->toDateString(),
            'first_name' => $employee->first_name,
            'last_name' => $employee->last_name,
            'gender' => $employee->gender,
        ];
    }
}
