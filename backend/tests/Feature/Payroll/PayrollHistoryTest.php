<?php

namespace Tests\Feature\Payroll;

use App\Models\User;
use App\Modules\ApprovalFlow\Enums\ApproverType;
use App\Modules\ApprovalFlow\Models\ApprovalFlow;
use App\Modules\ApprovalFlow\Models\ApprovalStep;
use App\Modules\Company\Models\Company;
use App\Modules\Employee\Models\Employee;
use App\Modules\Payroll\Contracts\PayrollCalculationEngineInterface;
use App\Modules\Payroll\DataTransferObjects\EmployeePayslipDraft;
use App\Modules\Payroll\Enums\PayrollRunStatus;
use App\Modules\Payroll\Models\PayrollRun;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Phase 3 — Payroll History refinement.
 *
 * Sengaja file test TERPISAH dari PayrollApprovalTest.php (Phase 1, sudah
 * CLOSED 17/17) — tidak menyentuh test itu sama sekali. Kalkulasi payroll
 * sesungguhnya di-stub sama seperti PayrollApprovalTest, fokus test ini
 * murni ke data integrity (cross-company) & exposure histori (List/Detail).
 */
class PayrollHistoryTest extends TestCase
{
    use RefreshDatabase;

    private Company $companyA;
    private Company $companyB;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->companyA = Company::factory()->create();
        $this->companyB = Company::factory()->create();
        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

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

    // ---------- 1. Cross-company employee validation ----------

    public function test_store_rejects_employee_from_different_company(): void
    {
        $employeeOtherCompany = Employee::factory()->create(['company_id' => $this->companyB->id]);

        $this->actingAs($this->admin)
            ->postJson('/api/payroll-runs', [
                'company_id' => $this->companyA->id,
                'period_year' => 2026,
                'period_month' => 6,
                'employee_ids' => [$employeeOtherCompany->id],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['employee_ids.0']);

        $this->assertDatabaseCount('payroll_runs', 0);
    }

    public function test_store_accepts_employee_from_same_company(): void
    {
        $employee = Employee::factory()->create(['company_id' => $this->companyA->id]);

        $this->actingAs($this->admin)
            ->postJson('/api/payroll-runs', [
                'company_id' => $this->companyA->id,
                'period_year' => 2026,
                'period_month' => 6,
                'employee_ids' => [$employee->id],
            ])
            ->assertCreated();
    }

    public function test_update_participants_rejects_employee_from_different_company(): void
    {
        $employeeSameCompany = Employee::factory()->create(['company_id' => $this->companyA->id]);
        $employeeOtherCompany = Employee::factory()->create(['company_id' => $this->companyB->id]);

        $run = PayrollRun::create([
            'company_id' => $this->companyA->id,
            'period_year' => 2026,
            'period_month' => 6,
            'status' => PayrollRunStatus::Draft->value,
        ]);
        $run->participants()->sync([$employeeSameCompany->id]);

        $this->actingAs($this->admin)
            ->putJson("/api/payroll-runs/{$run->id}/participants", [
                'employee_ids' => [$employeeSameCompany->id, $employeeOtherCompany->id],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['employee_ids.1']);

        $this->assertEquals([$employeeSameCompany->id], $run->fresh()->participants->pluck('id')->all());
    }

    // ---------- 3. Filtering (period_year / period_month) ----------

    public function test_index_filters_by_period_year_and_month(): void
    {
        PayrollRun::create(['company_id' => $this->companyA->id, 'period_year' => 2025, 'period_month' => 1, 'status' => PayrollRunStatus::Draft->value]);
        PayrollRun::create(['company_id' => $this->companyA->id, 'period_year' => 2026, 'period_month' => 6, 'status' => PayrollRunStatus::Draft->value]);
        PayrollRun::create(['company_id' => $this->companyA->id, 'period_year' => 2026, 'period_month' => 7, 'status' => PayrollRunStatus::Draft->value]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/payroll-runs?period_year=2026&period_month=6')
            ->assertOk();

        $data = $response->json('data.data');
        $this->assertCount(1, $data);
        $this->assertEquals(2026, $data[0]['period_year']);
        $this->assertEquals(6, $data[0]['period_month']);
    }

    // ---------- 4. Summary (jumlah employee & total net payroll) ----------

    public function test_index_returns_participants_count_and_total_net_payroll(): void
    {
        $employees = Employee::factory()->count(2)->create(['company_id' => $this->companyA->id]);

        $response = $this->actingAs($this->admin)->postJson('/api/payroll-runs', [
            'company_id' => $this->companyA->id,
            'period_year' => 2026,
            'period_month' => 6,
            'employee_ids' => $employees->pluck('id')->all(),
        ]);
        $runId = $response->json('data.id');

        $this->actingAs($this->admin)->postJson("/api/payroll-runs/{$runId}/proceed-payslip")->assertOk();

        $row = $this->actingAs($this->admin)
            ->getJson('/api/payroll-runs?period_year=2026&period_month=6')
            ->assertOk()
            ->json('data.data.0');

        $this->assertEquals(2, $row['participants_count']);
        // 2 employee x net_pay 4.850.000 (stub) = 9.700.000
        $this->assertEquals(9700000, (float) $row['total_net_payroll']);
    }

    public function test_index_total_net_payroll_zero_when_not_yet_calculated(): void
    {
        $employee = Employee::factory()->create(['company_id' => $this->companyA->id]);
        PayrollRun::create(['company_id' => $this->companyA->id, 'period_year' => 2026, 'period_month' => 6, 'status' => PayrollRunStatus::Draft->value])
            ->participants()->sync([$employee->id]);

        $row = $this->actingAs($this->admin)
            ->getJson('/api/payroll-runs?period_year=2026&period_month=6')
            ->assertOk()
            ->json('data.data.0');

        $this->assertEquals(1, $row['participants_count']);
        $this->assertEquals(0, (float) $row['total_net_payroll']);
    }

    // Regresi: current_revision di response index() harus tetap integer utuh,
    // bukan ke-overwrite oleh relation currentRevision (lihat komentar di
    // PayrollRunController::index()). Kalau regresi ini gagal, kolom "Revisi
    // ke-N" di List akan tampil rusak (bukan angka).
    public function test_index_current_revision_stays_integer_after_recalculate(): void
    {
        $employee = Employee::factory()->create(['company_id' => $this->companyA->id]);
        $response = $this->actingAs($this->admin)->postJson('/api/payroll-runs', [
            'company_id' => $this->companyA->id,
            'period_year' => 2026,
            'period_month' => 6,
            'employee_ids' => [$employee->id],
        ]);
        $runId = $response->json('data.id');
        $this->actingAs($this->admin)->postJson("/api/payroll-runs/{$runId}/proceed-payslip")->assertOk();
        $this->actingAs($this->admin)->postJson("/api/payroll-runs/{$runId}/proceed-payslip")->assertOk();

        $row = $this->actingAs($this->admin)
            ->getJson('/api/payroll-runs?period_year=2026&period_month=6')
            ->assertOk()
            ->json('data.data.0');

        $this->assertSame(2, $row['current_revision']);
    }

    // ---------- 2. Revision & Approval history exposure ----------

    public function test_show_exposes_all_revisions_not_only_current(): void
    {
        $employee = Employee::factory()->create(['company_id' => $this->companyA->id]);
        $response = $this->actingAs($this->admin)->postJson('/api/payroll-runs', [
            'company_id' => $this->companyA->id,
            'period_year' => 2026,
            'period_month' => 6,
            'employee_ids' => [$employee->id],
        ]);
        $runId = $response->json('data.id');

        // Hitung 3x — harus jadi 3 revisi terpisah, bukan overwrite.
        $this->actingAs($this->admin)->postJson("/api/payroll-runs/{$runId}/proceed-payslip", ['note' => 'revisi 1'])->assertOk();
        $this->actingAs($this->admin)->postJson("/api/payroll-runs/{$runId}/proceed-payslip", ['note' => 'revisi 2'])->assertOk();
        $this->actingAs($this->admin)->postJson("/api/payroll-runs/{$runId}/proceed-payslip", ['note' => 'revisi 3'])->assertOk();

        $data = $this->actingAs($this->admin)
            ->getJson("/api/payroll-runs/{$runId}")
            ->assertOk()
            ->json('data');

        $this->assertCount(3, $data['revisions']);
        $this->assertEquals([1, 2, 3], collect($data['revisions'])->pluck('revision_number')->sort()->values()->all());
        $this->assertEquals('revisi 1', collect($data['revisions'])->firstWhere('revision_number', 1)['note']);
        $this->assertEquals('revisi 3', collect($data['revisions'])->firstWhere('revision_number', 3)['note']);

        // Current revision tetap yang terbaru (behavior lama TIDAK berubah).
        // current_revision (scalar) dan current_revision_data (relation)
        // sengaja dipisah di response — collision snake_case kalau digabung.
        $this->assertEquals(3, $data['current_revision']);
        $this->assertEquals(3, $data['current_revision_data']['revision_number']);

        // Revisi lama beneran ke-preserve (payslip-nya masih ada, bukan cuma nomor doang).
        $rev1 = collect($data['revisions'])->firstWhere('revision_number', 1);
        $this->assertCount(1, $rev1['payslips']);
    }

    public function test_show_exposes_all_approval_requests_not_only_latest(): void
    {
        $employee = Employee::factory()->create(['company_id' => $this->companyA->id]);
        $response = $this->actingAs($this->admin)->postJson('/api/payroll-runs', [
            'company_id' => $this->companyA->id,
            'period_year' => 2026,
            'period_month' => 6,
            'employee_ids' => [$employee->id],
        ]);
        $runId = $response->json('data.id');

        // Approval flow beneran harus dikonfigurasi biar initiate() bikin
        // PayrollApprovalRequest — kalau gak ada flow aktif, requestApproval()
        // auto-approve TANPA bikin request row sama sekali (behavior existing
        // dari Phase 1, tidak diubah), jadi gak ada histori buat diuji.
        $role = Role::firstOrCreate(['name' => 'finance-approver', 'guard_name' => 'web']);
        $flow = ApprovalFlow::create([
            'company_id' => $this->companyA->id, 'name' => 'Payroll Lock Approval',
            'code' => 'payroll-lock-'.uniqid(), 'approval_type' => 'payroll', 'is_active' => true,
        ]);
        ApprovalStep::create([
            'approval_flow_id' => $flow->id, 'sequence' => 1, 'name' => 'Finance Approval',
            'approver_type' => ApproverType::SpecificRole->value, 'approver_role_id' => $role->id, 'is_active' => true,
        ]);

        // Siklus 1: request approval, lalu recalculate (auto-invalidate approval
        // yang lagi jalan, behavior existing dari Phase 1/2 — tidak diubah).
        $this->actingAs($this->admin)->postJson("/api/payroll-runs/{$runId}/proceed-payslip")->assertOk();
        $this->actingAs($this->admin)->postJson("/api/payroll-runs/{$runId}/request-approval")->assertOk();

        $this->actingAs($this->admin)->postJson("/api/payroll-runs/{$runId}/proceed-payslip", ['note' => 'recalc'])->assertOk();
        $this->actingAs($this->admin)->postJson("/api/payroll-runs/{$runId}/request-approval")->assertOk();

        $data = $this->actingAs($this->admin)
            ->getJson("/api/payroll-runs/{$runId}")
            ->assertOk()
            ->json('data');

        $this->assertCount(2, $data['approval_requests']);
    }
}