<?php

namespace Tests\Feature\ApprovalFlow;

use App\Models\User;
use App\Modules\ApprovalFlow\Enums\ApproverType;
use App\Modules\ApprovalFlow\Models\ApprovalFlow;
use App\Modules\ApprovalFlow\Models\ApprovalStep;
use App\Modules\Company\Models\Company;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Backlog fix: DirectManager approver type buat Payroll selalu unresolvable
 * by design (PayrollApprovalService selalu resolve dengan subject employee
 * null), yang sebelumnya cuma didokumentasikan sebagai "sinyal salah
 * konfigurasi" tanpa validasi cegah — HR bisa dengan gampang bikin approval
 * yang nyangkut pending selamanya. Test ini membuktikan validasi baru di
 * Store/UpdateApprovalStepRequest MENOLAK konfigurasi ini dari sumbernya,
 * dan TIDAK menyentuh approval_type lain (Leave, dst) yang punya subject
 * employee natural dari requester-nya sendiri.
 */
class ApprovalStepPayrollGuardTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
        $this->company = Company::factory()->create();
    }

    private function makeFlow(string $approvalType): ApprovalFlow
    {
        return ApprovalFlow::create([
            'company_id' => $this->company->id,
            'name' => 'Test Flow',
            'code' => 'test-'.uniqid(),
            'approval_type' => $approvalType,
            'is_active' => true,
        ]);
    }

    public function test_cannot_create_direct_manager_step_for_payroll_flow(): void
    {
        $flow = $this->makeFlow('payroll');

        $response = $this->actingAs($this->admin)->postJson("/api/approval-flows/{$flow->id}/steps", [
            'sequence' => 1,
            'approver_type' => ApproverType::DirectManager->value,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('approver_type');
        $this->assertDatabaseCount('approval_steps', 0);
    }

    public function test_cannot_update_step_to_direct_manager_for_payroll_flow(): void
    {
        $flow = $this->makeFlow('payroll');
        $step = ApprovalStep::create([
            'approval_flow_id' => $flow->id, 'sequence' => 1, 'name' => 'Step 1',
            'approver_type' => ApproverType::SpecificRole->value,
            'approver_role_id' => \Spatie\Permission\Models\Role::where('name', 'hr')->first()->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->putJson("/api/approval-flows/{$flow->id}/steps/{$step->id}", [
            'sequence' => 1,
            'approver_type' => ApproverType::DirectManager->value,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('approver_type');
        $this->assertEquals('specific_role', $step->fresh()->approver_type->value);
    }

    // Regresi: approval_type SELAIN payroll (contoh: leave) tetap boleh pakai
    // DirectManager — requester punya subject employee natural, guard ini
    // sengaja spesifik ke payroll doang, bukan larangan global.
    public function test_direct_manager_step_still_allowed_for_leave_flow(): void
    {
        $flow = $this->makeFlow('leave');

        $response = $this->actingAs($this->admin)->postJson("/api/approval-flows/{$flow->id}/steps", [
            'sequence' => 1,
            'approver_type' => ApproverType::DirectManager->value,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('approval_steps', ['approval_flow_id' => $flow->id, 'approver_type' => 'direct_manager']);
    }

    // Regresi: SpecificRole/SpecificEmployee tetap boleh buat payroll (cuma
    // DirectManager doang yang ditolak).
    public function test_specific_role_step_still_allowed_for_payroll_flow(): void
    {
        $flow = $this->makeFlow('payroll');
        $role = \Spatie\Permission\Models\Role::where('name', 'hr')->first();

        $response = $this->actingAs($this->admin)->postJson("/api/approval-flows/{$flow->id}/steps", [
            'sequence' => 1,
            'approver_type' => ApproverType::SpecificRole->value,
            'approver_role_id' => $role->id,
        ]);

        $response->assertCreated();
    }
}
