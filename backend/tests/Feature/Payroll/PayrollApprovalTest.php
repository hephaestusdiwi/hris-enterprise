<?php

namespace Tests\Feature\Payroll;

use App\Models\User;
use App\Modules\ApprovalFlow\Enums\ApproverType;
use App\Modules\ApprovalFlow\Models\ApprovalFlow;
use App\Modules\ApprovalFlow\Models\ApprovalStep;
use App\Modules\Company\Models\Company;
use App\Modules\Employee\Models\Employee;
use App\Modules\JobLevel\Models\JobLevel;
use App\Modules\Payroll\Contracts\PayrollCalculationEngineInterface;
use App\Modules\Payroll\DataTransferObjects\EmployeePayslipDraft;
use App\Modules\Payroll\Enums\PayrollApprovalRequestStatus;
use App\Modules\Payroll\Enums\PayrollApprovalStepDecisionStatus;
use App\Modules\Payroll\Enums\PayrollRunStatus;
use App\Modules\Payroll\Models\PayrollApprovalRequest;
use App\Modules\Payroll\Models\PayrollApprovalStepDecision;
use App\Modules\Payroll\Models\PayrollRun;
use App\Modules\Payroll\Services\PayrollApprovalService;
use App\Modules\Payroll\Services\PayrollRunService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Phase 1 — Payroll Approval.
 *
 * Kalkulasi payroll sesungguhnya (Salary Structure/BPJS/PPh21) BUKAN fokus
 * Phase 1 ini — di-stub lewat binding PayrollCalculationEngineInterface,
 * supaya test fokus murni ke state machine & approval, bukan ketergantungan
 * ke seluruh chain module lain yang sudah punya cakupan test-nya sendiri.
 */
class PayrollApprovalTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->company = Company::factory()->create();
        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        // Stub calculation engine — cukup buat naikin status ke Processed.
        $this->app->bind(PayrollCalculationEngineInterface::class, function () {
            return new class implements PayrollCalculationEngineInterface
            {
                public function calculateDraftsForRun(PayrollRun $payrollRun): array
                {
                    $drafts = [];

                    foreach ($payrollRun->participants as $employee) {
                        $drafts[$employee->id] = new EmployeePayslipDraft(
                            employeeId: $employee->id,
                            grossEarning: '5000000.00',
                            structuralDeduction: '0.00',
                            manualDeductionTotal: '0.00',
                            bpjsEmployeeTotal: '100000.00',
                            bpjsEmployerTotal: '200000.00',
                            taxAmount: '50000.00',
                            loanDeductionTotal: '0.00',
                            netPay: '4850000.00',
                            lines: [],
                        );
                    }

                    return $drafts;
                }
            };
        });
    }

    private function createDraftRun(): PayrollRun
    {
        $employee = Employee::factory()->create(['company_id' => $this->company->id]);

        $response = $this->actingAs($this->admin)->postJson('/api/payroll-runs', [
            'company_id' => $this->company->id,
            'period_year' => 2026,
            'period_month' => 6,
            'employee_ids' => [$employee->id],
        ]);

        $response->assertCreated();

        return PayrollRun::findOrFail($response->json('data.id'));
    }

    private function proceed(PayrollRun $run): void
    {
        $this->actingAs($this->admin)
            ->postJson("/api/payroll-runs/{$run->id}/proceed-payslip")
            ->assertOk();
    }

    private function makeSpecificRoleFlow(string $roleName = 'finance-approver'): array
    {
        $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);

        $flow = ApprovalFlow::create([
            'company_id' => $this->company->id,
            'name' => 'Payroll Lock Approval',
            'code' => 'payroll-lock-'.uniqid(),
            'approval_type' => 'payroll',
            'is_active' => true,
        ]);

        $step = ApprovalStep::create([
            'approval_flow_id' => $flow->id,
            'sequence' => 1,
            'name' => 'Finance Approval',
            'approver_type' => ApproverType::SpecificRole->value,
            'approver_role_id' => $role->id,
            'is_active' => true,
        ]);

        return [$flow, $step, $role];
    }

    // 1. Payroll dapat dihitung tanpa approval.
    public function test_payroll_can_be_calculated_without_approval(): void
    {
        $run = $this->createDraftRun();

        $this->actingAs($this->admin)
            ->postJson("/api/payroll-runs/{$run->id}/proceed-payslip")
            ->assertOk();

        $run->refresh();
        $this->assertEquals(PayrollRunStatus::Processed, $run->status);
        $this->assertEquals(1, $run->current_revision);
        $this->assertDatabaseHas('payslips', ['payroll_run_id' => $run->id]);
    }

    // 2. Processed payroll dapat Request Approval.
    public function test_processed_payroll_can_request_approval(): void
    {
        $run = $this->createDraftRun();
        $this->proceed($run);
        $this->makeSpecificRoleFlow();

        $this->actingAs($this->admin)
            ->postJson("/api/payroll-runs/{$run->id}/request-approval")
            ->assertOk();

        $run->refresh();
        $this->assertEquals(PayrollRunStatus::PendingApproval, $run->status);
    }

    // AUDIT PAYROLL REVISION — Critical #3 fix: dua requestApproval() untuk run
    // yang sama tidak boleh menghasilkan dua PayrollApprovalRequest pending.
    // Test sequential ini TIDAK membuktikan concurrency secara empiris (butuh 2
    // koneksi DB paralel buat itu), tapi mengunci business rule-nya: maksimal
    // satu pending approval request aktif per run. Correctness locking-nya
    // sendiri berasal dari lockForUpdate() di dalam transaction requestApproval().
    public function test_second_sequential_request_approval_is_rejected_while_first_still_pending(): void
    {
        $run = $this->createDraftRun();
        $this->proceed($run);
        $this->makeSpecificRoleFlow();

        $this->actingAs($this->admin)
            ->postJson("/api/payroll-runs/{$run->id}/request-approval")
            ->assertOk();

        $this->actingAs($this->admin)
            ->postJson("/api/payroll-runs/{$run->id}/request-approval")
            ->assertStatus(422);

        $run->refresh();
        $this->assertEquals(PayrollRunStatus::PendingApproval, $run->status);
        $this->assertEquals(
            1,
            PayrollApprovalRequest::where('payroll_run_id', $run->id)
                ->where('status', PayrollApprovalRequestStatus::Pending->value)
                ->count()
        );
    }

    // 3. Payroll approval menggunakan PayrollRun scope, BUKAN scope employee mana pun.
    public function test_payroll_approval_uses_payroll_run_scope_not_employee(): void
    {
        // Flow job-level-scoped ini seharusnya CUMA kepakai kalau resolusi berbasis
        // employee — Payroll harus tetap jatuh ke company-default, bukan ini.
        $jobLevel = JobLevel::create([
            'company_id' => $this->company->id,
            'name' => 'Manager',
            'code' => 'jl-'.uniqid(),
            'level_order' => 1,
            'is_active' => true,
        ]);

        $jobLevelFlow = ApprovalFlow::create([
            'company_id' => $this->company->id,
            'job_level_id' => $jobLevel->id,
            'name' => 'Job Level Flow (harus diabaikan Payroll)',
            'code' => 'joblevel-'.uniqid(),
            'approval_type' => 'payroll',
            'is_active' => true,
        ]);
        ApprovalStep::create([
            'approval_flow_id' => $jobLevelFlow->id, 'sequence' => 1, 'name' => 'x',
            'approver_type' => ApproverType::SpecificRole->value,
            'approver_role_id' => Role::firstOrCreate(['name' => 'irrelevant', 'guard_name' => 'web'])->id,
            'is_active' => true,
        ]);

        [$companyFlow] = $this->makeSpecificRoleFlow();

        $run = $this->createDraftRun();
        $this->proceed($run);

        $this->actingAs($this->admin)->postJson("/api/payroll-runs/{$run->id}/request-approval")->assertOk();

        $request = PayrollApprovalRequest::where('payroll_run_id', $run->id)->latest()->first();
        $this->assertEquals($companyFlow->id, $request->approval_flow_id);
    }

    // 4. Payroll tidak menggunakan DirectManager — approval macet (tidak ada yang eligible), bukan salah proxy.
    public function test_payroll_never_resolves_direct_manager(): void
    {
        $manager = Employee::factory()->create(['company_id' => $this->company->id]);
        $managerUser = User::factory()->create();
        $manager->update(['user_id' => $managerUser->id]);

        $flow = ApprovalFlow::create([
            'company_id' => $this->company->id, 'name' => 'DM Flow', 'code' => 'dm-'.uniqid(),
            'approval_type' => 'payroll', 'is_active' => true,
        ]);
        $step = ApprovalStep::create([
            'approval_flow_id' => $flow->id, 'sequence' => 1, 'name' => 'DM Step',
            'approver_type' => ApproverType::DirectManager->value, 'is_active' => true,
        ]);

        $run = $this->createDraftRun();
        $this->proceed($run);
        $this->actingAs($this->admin)->postJson("/api/payroll-runs/{$run->id}/request-approval")->assertOk();

        $decision = $run->approvalRequest->stepDecisions()->first();

        // Bahkan manager beneran pun TIDAK eligible, karena Payroll tidak pernah
        // ngirim subject employee — resolveApproverUserIds(step, null) buat DirectManager selalu [].
        $this->actingAs($managerUser)
            ->postJson("/api/payroll-approvals/{$decision->id}/decide", ['action' => 'approve'])
            ->assertStatus(422);
    }

    // 5. SpecificRole approver dapat resolve.
    public function test_specific_role_approver_can_decide(): void
    {
        [, , $role] = $this->makeSpecificRoleFlow();
        $approverUser = User::factory()->create();
        $approverUser->assignRole($role);

        $run = $this->createDraftRun();
        $this->proceed($run);
        $this->actingAs($this->admin)->postJson("/api/payroll-runs/{$run->id}/request-approval")->assertOk();

        $decision = $run->approvalRequest->stepDecisions()->first();

        $this->actingAs($approverUser)
            ->postJson("/api/payroll-approvals/{$decision->id}/decide", ['action' => 'approve'])
            ->assertOk();

        $run->refresh();
        $this->assertEquals(PayrollRunStatus::Approved, $run->status);
    }

    // 6. SpecificEmployee approver dapat resolve.
    public function test_specific_employee_approver_can_decide(): void
    {
        $approverEmployee = Employee::factory()->create(['company_id' => $this->company->id]);
        $approverUser = User::factory()->create();
        $approverEmployee->update(['user_id' => $approverUser->id]);

        $flow = ApprovalFlow::create([
            'company_id' => $this->company->id, 'name' => 'Specific Employee Flow', 'code' => 'se-'.uniqid(),
            'approval_type' => 'payroll', 'is_active' => true,
        ]);
        ApprovalStep::create([
            'approval_flow_id' => $flow->id, 'sequence' => 1, 'name' => 'Approve by CFO',
            'approver_type' => ApproverType::SpecificEmployee->value,
            'approver_employee_id' => $approverEmployee->id, 'is_active' => true,
        ]);

        $run = $this->createDraftRun();
        $this->proceed($run);
        $this->actingAs($this->admin)->postJson("/api/payroll-runs/{$run->id}/request-approval")->assertOk();

        $decision = $run->approvalRequest->stepDecisions()->first();

        $this->actingAs($approverUser)
            ->postJson("/api/payroll-approvals/{$decision->id}/decide", ['action' => 'approve'])
            ->assertOk();

        $run->refresh();
        $this->assertEquals(PayrollRunStatus::Approved, $run->status);
    }

    // 7. Pending Approval dapat Approve.
    public function test_pending_approval_can_be_approved(): void
    {
        [, , $role] = $this->makeSpecificRoleFlow();
        $approverUser = User::factory()->create();
        $approverUser->assignRole($role);

        $run = $this->createDraftRun();
        $this->proceed($run);
        $this->actingAs($this->admin)->postJson("/api/payroll-runs/{$run->id}/request-approval")->assertOk();

        $decision = $run->approvalRequest->stepDecisions()->first();
        $this->actingAs($approverUser)->postJson("/api/payroll-approvals/{$decision->id}/decide", ['action' => 'approve'])->assertOk();

        $this->assertEquals(PayrollApprovalRequestStatus::Approved, $run->approvalRequest->fresh()->status);
    }

    // 8. Pending Approval dapat Reject.
    public function test_pending_approval_can_be_rejected(): void
    {
        [, , $role] = $this->makeSpecificRoleFlow();
        $approverUser = User::factory()->create();
        $approverUser->assignRole($role);

        $run = $this->createDraftRun();
        $this->proceed($run);
        $this->actingAs($this->admin)->postJson("/api/payroll-runs/{$run->id}/request-approval")->assertOk();

        $decision = $run->approvalRequest->stepDecisions()->first();
        $this->actingAs($approverUser)
            ->postJson("/api/payroll-approvals/{$decision->id}/decide", ['action' => 'reject', 'notes' => 'Ada kesalahan nominal'])
            ->assertOk();

        $run->refresh();
        $this->assertEquals(PayrollRunStatus::Processed, $run->status);
        $this->assertEquals(PayrollApprovalRequestStatus::Rejected, $run->approvalRequest->fresh()->status);
    }

    // 9. Rejected payroll tidak dapat Lock.
    public function test_rejected_payroll_cannot_be_locked(): void
    {
        [, , $role] = $this->makeSpecificRoleFlow();
        $approverUser = User::factory()->create();
        $approverUser->assignRole($role);

        $run = $this->createDraftRun();
        $this->proceed($run);
        $this->actingAs($this->admin)->postJson("/api/payroll-runs/{$run->id}/request-approval")->assertOk();
        $decision = $run->approvalRequest->stepDecisions()->first();
        $this->actingAs($approverUser)->postJson("/api/payroll-approvals/{$decision->id}/decide", ['action' => 'reject', 'notes' => 'x'])->assertOk();

        $this->actingAs($this->admin)
            ->postJson("/api/payroll-runs/{$run->id}/lock")
            ->assertStatus(422);
    }

    // 10. Approved payroll dapat Lock.
    public function test_approved_payroll_can_be_locked(): void
    {
        [, , $role] = $this->makeSpecificRoleFlow();
        $approverUser = User::factory()->create();
        $approverUser->assignRole($role);

        $run = $this->createDraftRun();
        $this->proceed($run);
        $this->actingAs($this->admin)->postJson("/api/payroll-runs/{$run->id}/request-approval")->assertOk();
        $decision = $run->approvalRequest->stepDecisions()->first();
        $this->actingAs($approverUser)->postJson("/api/payroll-approvals/{$decision->id}/decide", ['action' => 'approve'])->assertOk();

        $this->actingAs($this->admin)->postJson("/api/payroll-runs/{$run->id}/lock")->assertOk();

        $run->refresh();
        $this->assertEquals(PayrollRunStatus::Locked, $run->status);
        $this->assertNotNull($run->locked_at);
    }

    // 11. Approved payroll tidak boleh diam-diam direcalculate — recalculate
    // sesudah Approved WAJIB nge-invalidate approval (status turun ke Processed,
    // approval request lama ke-preserve, bukan dihapus).
    public function test_recalculating_approved_payroll_invalidates_approval_visibly(): void
    {
        [, , $role] = $this->makeSpecificRoleFlow();
        $approverUser = User::factory()->create();
        $approverUser->assignRole($role);

        $run = $this->createDraftRun();
        $this->proceed($run);
        $this->actingAs($this->admin)->postJson("/api/payroll-runs/{$run->id}/request-approval")->assertOk();
        $decision = $run->approvalRequest->stepDecisions()->first();
        $this->actingAs($approverUser)->postJson("/api/payroll-approvals/{$decision->id}/decide", ['action' => 'approve'])->assertOk();

        $firstRequestId = $run->approvalRequest->fresh()->id;

        $this->proceed($run); // recalculate

        $run->refresh();
        $this->assertEquals(PayrollRunStatus::Processed, $run->status, 'Status harus visibly turun, bukan tetap Approved.');
        $this->assertEquals(2, $run->current_revision);

        // Approval lama TETAP ada di DB (history tidak hilang).
        $this->assertDatabaseHas('payroll_approval_requests', ['id' => $firstRequestId]);

        // Harus request approval ULANG sebelum bisa Lock lagi.
        $this->actingAs($this->admin)->postJson("/api/payroll-runs/{$run->id}/lock")->assertStatus(422);
    }

    // 12. Locked payroll tidak dapat diedit/recalculate.
    public function test_locked_payroll_cannot_be_recalculated(): void
    {
        [, , $role] = $this->makeSpecificRoleFlow();
        $approverUser = User::factory()->create();
        $approverUser->assignRole($role);

        $run = $this->createDraftRun();
        $this->proceed($run);
        $this->actingAs($this->admin)->postJson("/api/payroll-runs/{$run->id}/request-approval")->assertOk();
        $decision = $run->approvalRequest->stepDecisions()->first();
        $this->actingAs($approverUser)->postJson("/api/payroll-approvals/{$decision->id}/decide", ['action' => 'approve'])->assertOk();
        $this->actingAs($this->admin)->postJson("/api/payroll-runs/{$run->id}/lock")->assertOk();

        $this->actingAs($this->admin)
            ->postJson("/api/payroll-runs/{$run->id}/proceed-payslip")
            ->assertStatus(422);
    }

    // 13. Publish hanya dapat dilakukan setelah Lock.
    public function test_publish_only_allowed_after_lock(): void
    {
        $run = $this->createDraftRun();
        $this->proceed($run);

        $this->actingAs($this->admin)
            ->postJson("/api/payroll-runs/{$run->id}/publish")
            ->assertStatus(422);

        [, , $role] = $this->makeSpecificRoleFlow();
        $approverUser = User::factory()->create();
        $approverUser->assignRole($role);
        $this->actingAs($this->admin)->postJson("/api/payroll-runs/{$run->id}/request-approval")->assertOk();
        $decision = $run->approvalRequest->stepDecisions()->first();
        $this->actingAs($approverUser)->postJson("/api/payroll-approvals/{$decision->id}/decide", ['action' => 'approve'])->assertOk();
        $this->actingAs($this->admin)->postJson("/api/payroll-runs/{$run->id}/lock")->assertOk();

        $this->actingAs($this->admin)
            ->postJson("/api/payroll-runs/{$run->id}/publish")
            ->assertOk();

        $run->refresh();
        $this->assertNotNull($run->published_at);
    }

    // 14. Approval history tidak hilang setelah rejection + revision + approval ulang.
    public function test_approval_history_preserved_across_reject_and_revision(): void
    {
        [, , $role] = $this->makeSpecificRoleFlow();
        $approverUser = User::factory()->create();
        $approverUser->assignRole($role);

        $run = $this->createDraftRun();
        $this->proceed($run);

        $this->actingAs($this->admin)
            ->postJson("/api/payroll-runs/{$run->id}/request-approval")
            ->assertOk();

        $decision1 = $run->approvalRequest->stepDecisions()->first();

        $this->actingAs($approverUser)
            ->postJson("/api/payroll-approvals/{$decision1->id}/decide", [
                'action' => 'reject',
                'notes' => 'salah',
            ])
            ->assertOk();

        $this->proceed($run); // revisi ke-2

        $this->actingAs($this->admin)
            ->postJson("/api/payroll-runs/{$run->id}/request-approval")
            ->assertOk();

        // Ambil approval request terbaru setelah revision.
        $run->refresh();

        $decision2 = $run->approvalRequest->stepDecisions()->firstOrFail();

        $this->actingAs($approverUser)
            ->postJson("/api/payroll-approvals/{$decision2->id}/decide", [
                'action' => 'approve',
            ])
            ->assertOk();

        $this->assertEquals(
            2,
            PayrollApprovalRequest::where('payroll_run_id', $run->id)->count()
        );

        $this->assertEquals(
            PayrollApprovalRequestStatus::Rejected,
            PayrollApprovalRequest::where('payroll_run_id', $run->id)
                ->orderBy('id')
                ->first()
                ->status
        );

        $this->assertEquals(
            PayrollRunStatus::Approved,
            $run->fresh()->status
        );
    }

    /**
     * 15. requestApproval() harus atomic — kalau initiate() gagal di tengah
     * jalan, status run TIDAK BOLEH nyangkut di PendingApproval tanpa
     * ApprovalRequest. Dipanggil lewat service langsung (bukan HTTP), karena
     * yang diuji adalah atomicity DB::transaction()-nya, bukan error-mapping
     * controller.
     */
    public function test_request_approval_rolls_back_if_initiate_fails(): void
    {
        $run = $this->createDraftRun();
        $this->proceed($run);
        $this->makeSpecificRoleFlow();

        $this->partialMock(PayrollApprovalService::class, function ($mock) {
            $mock->shouldReceive('initiate')->once()->andThrow(new \RuntimeException('simulated initiate failure'));
        });

        $service = app(PayrollRunService::class);

        try {
            $service->requestApproval($run->fresh());
            $this->fail('Exception dari initiate() harusnya ke-propagate, bukan ketelan.');
        } catch (\RuntimeException $e) {
            $this->assertSame('simulated initiate failure', $e->getMessage());
        }

        $run->refresh();
        $this->assertEquals(PayrollRunStatus::Processed, $run->status);
        $this->assertNull($run->requested_at);
        $this->assertDatabaseCount('payroll_approval_requests', 0);
    }

    // 16. Endpoint request-approval sekarang cuma butuh permission 'request payroll approval' — tidak lagi butuh 'create payroll runs'.
    public function test_request_approval_only_needs_request_payroll_approval_permission(): void
    {
        $run = $this->createDraftRun();
        $this->proceed($run);
        $this->makeSpecificRoleFlow();

        $role = Role::firstOrCreate(['name' => 'payroll-approval-requester', 'guard_name' => 'web']);
        $role->givePermissionTo('request payroll approval');

        $user = User::factory()->create();
        $user->assignRole($role);

        $this->actingAs($user)
            ->postJson("/api/payroll-runs/{$run->id}/request-approval")
            ->assertOk();
    }

    // 17. Sebaliknya, permission 'create payroll runs' doang sudah TIDAK cukup buat request-approval (dulu cukup, sekarang tidak — sesuai fix route duplikat).
    public function test_create_payroll_runs_permission_alone_cannot_request_approval(): void
    {
        $run = $this->createDraftRun();
        $this->proceed($run);

        $role = Role::firstOrCreate(['name' => 'payroll-creator-only', 'guard_name' => 'web']);
        $role->givePermissionTo('create payroll runs');

        $user = User::factory()->create();
        $user->assignRole($role);

        $this->actingAs($user)
            ->postJson("/api/payroll-runs/{$run->id}/request-approval")
            ->assertForbidden();
    }

    // ==========================================================================
    // AUDIT PAYROLL REVISION — Critical #1 fix: stale approval decision dari
    // revisi lama tidak boleh lagi bisa mengubah status PayrollRun current.
    // ==========================================================================

    // 18. Regression test WAJIB dari audit — inti dari Critical #1.
    public function test_stale_approval_decision_from_previous_revision_cannot_approve_current_revision(): void
    {
        [, , $role] = $this->makeSpecificRoleFlow();
        $approverUser = User::factory()->create();
        $approverUser->assignRole($role);

        // Revision #1: hitung, request approval, TETAP PENDING (belum diputuskan siapa pun).
        $run = $this->createDraftRun();
        $this->proceed($run);
        $this->actingAs($this->admin)->postJson("/api/payroll-runs/{$run->id}/request-approval")->assertOk();

        $staleRequest = $run->approvalRequest;
        $staleDecision = $staleRequest->stepDecisions()->first();
        $this->assertEquals(PayrollApprovalRequestStatus::Pending, $staleRequest->status);
        $this->assertEquals(PayrollApprovalStepDecisionStatus::Pending, $staleDecision->status);

        // Recalculate SAAT masih pending -> Revision #2. Request #1 harus jadi Superseded.
        $this->proceed($run);
        $run->refresh();
        $this->assertEquals(2, $run->current_revision);
        $this->assertEquals(PayrollRunStatus::Processed, $run->status);

        $staleRequest->refresh();
        $staleDecision->refresh();
        $this->assertEquals(PayrollApprovalRequestStatus::Superseded, $staleRequest->status, 'Request lama harus Superseded, bukan tetap Pending.');
        $this->assertEquals(PayrollApprovalStepDecisionStatus::Superseded, $staleDecision->status, 'Decision lama harus ikut Superseded.');
        $this->assertNotNull($staleRequest->decided_at);

        // Request approval BARU untuk revisi #2.
        $this->actingAs($this->admin)->postJson("/api/payroll-runs/{$run->id}/request-approval")->assertOk();
        $newRequest = $run->fresh()->approvalRequest;
        $this->assertNotEquals($staleRequest->id, $newRequest->id);
        $this->assertEquals(PayrollApprovalRequestStatus::Pending, $newRequest->status);

        // >>> ATTEMPT: approver coba decide() pakai decision LAMA dari request #1 — HARUS DITOLAK.
        $this->actingAs($approverUser)
            ->postJson("/api/payroll-approvals/{$staleDecision->id}/decide", ['action' => 'approve'])
            ->assertStatus(422);

        // PayrollRun TIDAK BOLEH menjadi Approved gara-gara decision lama.
        $run->refresh();
        $this->assertEquals(PayrollRunStatus::PendingApproval, $run->status, 'Run tidak boleh ter-approve oleh decision dari revisi lama.');

        // Request #2 tetap genuinely pending, decision-nya belum tersentuh.
        $newRequest->refresh();
        $this->assertEquals(PayrollApprovalRequestStatus::Pending, $newRequest->status);
        $newDecision = $newRequest->stepDecisions()->first();
        $this->assertEquals(PayrollApprovalStepDecisionStatus::Pending, $newDecision->status);

        // Decision lama tidak bisa dipakai lagi WALAUPUN dicoba kedua kalinya.
        $this->actingAs($approverUser)
            ->postJson("/api/payroll-approvals/{$staleDecision->id}/decide", ['action' => 'approve'])
            ->assertStatus(422);

        // Flow approval NORMAL untuk request #2 (revisi current) tetap bisa approve.
        $this->actingAs($approverUser)
            ->postJson("/api/payroll-approvals/{$newDecision->id}/decide", ['action' => 'approve'])
            ->assertOk();

        $run->refresh();
        $this->assertEquals(PayrollRunStatus::Approved, $run->status);
    }

    // 19. Regresi: approval normal (tanpa recalculate di tengah) masih PASS.
    public function test_normal_approval_flow_still_works_after_fix(): void
    {
        [, , $role] = $this->makeSpecificRoleFlow();
        $approverUser = User::factory()->create();
        $approverUser->assignRole($role);

        $run = $this->createDraftRun();
        $this->proceed($run);
        $this->actingAs($this->admin)->postJson("/api/payroll-runs/{$run->id}/request-approval")->assertOk();
        $decision = $run->approvalRequest->stepDecisions()->first();

        $this->actingAs($approverUser)
            ->postJson("/api/payroll-approvals/{$decision->id}/decide", ['action' => 'approve'])
            ->assertOk();

        $run->refresh();
        $this->assertEquals(PayrollRunStatus::Approved, $run->status);
        $this->assertEquals(PayrollApprovalRequestStatus::Approved, $run->approvalRequest->fresh()->status);
    }

    // 20. Regresi: reject masih PASS.
    public function test_reject_flow_still_works_after_fix(): void
    {
        [, , $role] = $this->makeSpecificRoleFlow();
        $approverUser = User::factory()->create();
        $approverUser->assignRole($role);

        $run = $this->createDraftRun();
        $this->proceed($run);
        $this->actingAs($this->admin)->postJson("/api/payroll-runs/{$run->id}/request-approval")->assertOk();
        $decision = $run->approvalRequest->stepDecisions()->first();

        $this->actingAs($approverUser)
            ->postJson("/api/payroll-approvals/{$decision->id}/decide", ['action' => 'reject'])
            ->assertOk();

        $run->refresh();
        $this->assertEquals(PayrollRunStatus::Processed, $run->status);
        $this->assertEquals(PayrollApprovalRequestStatus::Rejected, $run->approvalRequest->fresh()->status);
    }

    // 21. Regresi: recalculate payroll yang SUDAH Approved masih PASS (request lama
    // sekarang Superseded, bukan cuma "tetap ada di DB" seperti sebelumnya).
    public function test_recalculate_approved_payroll_still_works_after_fix(): void
    {
        [, , $role] = $this->makeSpecificRoleFlow();
        $approverUser = User::factory()->create();
        $approverUser->assignRole($role);

        $run = $this->createDraftRun();
        $this->proceed($run);
        $this->actingAs($this->admin)->postJson("/api/payroll-runs/{$run->id}/request-approval")->assertOk();
        $decision = $run->approvalRequest->stepDecisions()->first();
        $this->actingAs($approverUser)->postJson("/api/payroll-approvals/{$decision->id}/decide", ['action' => 'approve'])->assertOk();

        $approvedRequestId = $run->approvalRequest->fresh()->id;

        $this->proceed($run); // recalculate saat Approved

        $run->refresh();
        $this->assertEquals(PayrollRunStatus::Processed, $run->status);
        $this->assertEquals(2, $run->current_revision);

        // Request yang SUDAH Approved (bukan Pending) tidak disentuh cancelApprovalIfAny —
        // statusnya tetap Approved (bukan di-superseded), karena approval-nya memang
        // valid untuk aksinya sendiri (masuk Lock), cuma revisinya yang sekarang usang.
        $this->assertEquals(PayrollApprovalRequestStatus::Approved, PayrollApprovalRequest::find($approvedRequestId)->status);
        $this->assertDatabaseHas('payroll_approval_requests', ['id' => $approvedRequestId]);
    }

    // 22. Regresi: approval history tetap tersimpan (request lama, termasuk yang
    // sekarang Superseded, tidak pernah dihapus dari DB).
    public function test_approval_history_preserved_including_superseded_requests(): void
    {
        $this->makeSpecificRoleFlow();

        $run = $this->createDraftRun();
        $this->proceed($run);
        $this->actingAs($this->admin)->postJson("/api/payroll-runs/{$run->id}/request-approval")->assertOk();
        $firstRequestId = $run->approvalRequest->id;

        $this->proceed($run); // recalculate saat masih pending -> request #1 jadi Superseded

        $this->assertDatabaseHas('payroll_approval_requests', [
            'id' => $firstRequestId,
            'status' => PayrollApprovalRequestStatus::Superseded->value,
        ]);

        // Request approval lagi buat revisi #2 -> jadi request #2 di riwayat.
        $this->actingAs($this->admin)->postJson("/api/payroll-runs/{$run->id}/request-approval")->assertOk();

        $data = $this->actingAs($this->admin)->getJson("/api/payroll-runs/{$run->id}")->assertOk()->json('data');
        $this->assertCount(2, $data['approval_requests'], 'Riwayat approval harus tetap nampilin request lama yang superseded.');
    }

    // 23. Regresi: Lock cuma bisa dilakukan setelah approval REVISI CURRENT beres
    // (bukan lewat decision lama yang superseded).
    public function test_lock_only_possible_after_current_revision_approved(): void
    {
        [, , $role] = $this->makeSpecificRoleFlow();
        $approverUser = User::factory()->create();
        $approverUser->assignRole($role);

        $run = $this->createDraftRun();
        $this->proceed($run);
        $this->actingAs($this->admin)->postJson("/api/payroll-runs/{$run->id}/request-approval")->assertOk();
        $staleDecision = $run->approvalRequest->stepDecisions()->first();

        $this->proceed($run); // recalculate -> stale
        $this->actingAs($this->admin)->postJson("/api/payroll-runs/{$run->id}/request-approval")->assertOk();

        // Lock harus gagal selama belum ada approval valid buat revisi current.
        $this->actingAs($this->admin)->postJson("/api/payroll-runs/{$run->id}/lock")->assertStatus(422);

        // Decision lama (superseded) tidak bisa dipakai buat "jalan pintas" approve.
        $this->actingAs($approverUser)
            ->postJson("/api/payroll-approvals/{$staleDecision->id}/decide", ['action' => 'approve'])
            ->assertStatus(422);
        $this->actingAs($this->admin)->postJson("/api/payroll-runs/{$run->id}/lock")->assertStatus(422);

        // Cuma approval revisi current (request baru) yang bisa bikin Lock jalan.
        $newDecision = $run->fresh()->approvalRequest->stepDecisions()->first();
        $this->actingAs($approverUser)->postJson("/api/payroll-approvals/{$newDecision->id}/decide", ['action' => 'approve'])->assertOk();
        $this->actingAs($this->admin)->postJson("/api/payroll-runs/{$run->id}/lock")->assertOk();

        $run->refresh();
        $this->assertEquals(PayrollRunStatus::Locked, $run->status);
    }
}