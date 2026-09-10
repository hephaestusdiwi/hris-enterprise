<?php

namespace Tests\Feature\Payroll;

use App\Models\User;
use App\Modules\Bpjs\Models\EmployeeBpjsParticipation;
use App\Modules\Branch\Models\Branch;
use App\Modules\Company\Models\Company;
use App\Modules\Employee\Models\Employee;
use App\Modules\Payroll\Contracts\PayrollCalculationEngineInterface;
use App\Modules\Payroll\DataTransferObjects\EmployeePayslipDraft;
use App\Modules\Payroll\DataTransferObjects\PayslipLineDraft;
use App\Modules\Payroll\Enums\PayrollRunStatus;
use App\Modules\Payroll\Enums\PayslipLineSource;
use App\Modules\Payroll\Enums\PayslipLineType;
use App\Modules\Payroll\Models\PayrollRun;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollBpjsReportTest extends TestCase
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

        // Stub dengan PayslipLine BPJS persis format label yang dipakai
        // PayrollCalculationEngine aslinya (strtoupper($programKey).' (Karyawan)'/
        // ' (Company)') — dikonfirmasi dari source, bukan ditebak.
        $this->app->bind(PayrollCalculationEngineInterface::class, function () {
            return new class implements PayrollCalculationEngineInterface
            {
                public function calculateDraftsForRun(PayrollRun $run): array
                {
                    $drafts = [];
                    foreach ($run->participants as $employee) {
                        $drafts[$employee->id] = new EmployeePayslipDraft(
                            employeeId: $employee->id,
                            grossEarning: '6000000.00', structuralDeduction: '0.00', manualDeductionTotal: '0.00',
                            bpjsEmployeeTotal: '160000.00', bpjsEmployerTotal: '540000.00',
                            taxAmount: '50000.00', loanDeductionTotal: '0.00', netPay: '5790000.00',
                            lines: [
                                new PayslipLineDraft(PayslipLineType::BpjsEmployee, PayslipLineSource::Bpjs, 'KESEHATAN (Karyawan)', '60000.00', null),
                                new PayslipLineDraft(PayslipLineType::BpjsEmployer, PayslipLineSource::Bpjs, 'KESEHATAN (Company)', '240000.00', null),
                                new PayslipLineDraft(PayslipLineType::BpjsEmployee, PayslipLineSource::Bpjs, 'JHT (Karyawan)', '100000.00', null),
                                new PayslipLineDraft(PayslipLineType::BpjsEmployer, PayslipLineSource::Bpjs, 'JHT (Company)', '220000.00', null),
                                new PayslipLineDraft(PayslipLineType::BpjsEmployer, PayslipLineSource::Bpjs, 'JKK (Company)', '50000.00', null),
                                new PayslipLineDraft(PayslipLineType::BpjsEmployer, PayslipLineSource::Bpjs, 'JKM (Company)', '30000.00', null),
                            ],
                        );
                    }

                    return $drafts;
                }
            };
        });
    }

    private function makeLockedRunWithEmployees(array $employees): PayrollRun
    {
        $response = $this->actingAs($this->admin)->postJson('/api/payroll-runs', [
            'company_id' => $this->company->id, 'period_year' => 2026, 'period_month' => 6,
            'employee_ids' => collect($employees)->pluck('id')->all(),
        ]);
        $run = PayrollRun::findOrFail($response->json('data.id'));
        $this->actingAs($this->admin)->postJson("/api/payroll-runs/{$run->id}/proceed-payslip")->assertOk();

        return $run->fresh();
    }

    // ---------- Derivasi per program + NPP ----------

    public function test_bpjs_detail_correctly_derives_each_program_and_npp(): void
    {
        $employee = Employee::factory()->create(['company_id' => $this->company->id]);
        EmployeeBpjsParticipation::create([
            'employee_id' => $employee->id,
            'bpjs_registration_npp_number' => '0009XXXX1234',
        ]);
        $this->makeLockedRunWithEmployees([$employee]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/payroll-reports/bpjs/detail?period_year=2026&period_month=6')
            ->assertOk();

        $row = collect($response->json('data.data'))->firstWhere('employee_id', $employee->id);
        $this->assertEquals('0009XXXX1234', $row['npp_number']);
        $this->assertEquals('60000.00', $row['kesehatan_employee']);
        $this->assertEquals('240000.00', $row['kesehatan_employer']);
        $this->assertEquals('100000.00', $row['jht_employee']);
        $this->assertEquals('220000.00', $row['jht_employer']);
        $this->assertEquals('0.00', $row['jkk_employee']);
        $this->assertEquals('50000.00', $row['jkk_employer']);
        $this->assertEquals('0.00', $row['jkm_employee']);
        $this->assertEquals('30000.00', $row['jkm_employer']);
        $this->assertEquals('160000.00', $row['bpjs_employee_total']);
        $this->assertEquals('540000.00', $row['bpjs_employer_total']);
    }

    public function test_npp_is_null_when_employee_has_no_bpjs_participation_record(): void
    {
        $employee = Employee::factory()->create(['company_id' => $this->company->id]);
        $this->makeLockedRunWithEmployees([$employee]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/payroll-reports/bpjs/detail?period_year=2026&period_month=6')
            ->assertOk();

        $row = collect($response->json('data.data'))->firstWhere('employee_id', $employee->id);
        $this->assertNull($row['npp_number']);
        // Angka BPJS tetap ke-derive normal meski NPP kosong -- leftJoin tidak
        // boleh bikin baris payslip hilang cuma karena belum ada data BPJS participation.
        $this->assertEquals('60000.00', $row['kesehatan_employee']);
    }

    // ---------- Filter company / branch ----------

    public function test_filter_by_branch(): void
    {
        $branchA = Branch::factory()->create(['company_id' => $this->company->id]);
        $branchB = Branch::factory()->create(['company_id' => $this->company->id]);
        $employeeA = Employee::factory()->create(['company_id' => $this->company->id, 'branch_id' => $branchA->id]);
        $employeeB = Employee::factory()->create(['company_id' => $this->company->id, 'branch_id' => $branchB->id]);
        $this->makeLockedRunWithEmployees([$employeeA, $employeeB]);

        $response = $this->actingAs($this->admin)
            ->getJson("/api/payroll-reports/bpjs/detail?period_year=2026&period_month=6&branch_id={$branchA->id}")
            ->assertOk();

        $ids = collect($response->json('data.data'))->pluck('employee_id');
        $this->assertTrue($ids->contains($employeeA->id));
        $this->assertFalse($ids->contains($employeeB->id));
    }

    public function test_company_isolation_does_not_leak_other_company_data(): void
    {
        $ownEmployee = Employee::factory()->create(['company_id' => $this->company->id]);
        $this->makeLockedRunWithEmployees([$ownEmployee]);

        $otherCompany = Company::factory()->create();
        $otherEmployee = Employee::factory()->create(['company_id' => $otherCompany->id]);
        $otherRunResponse = $this->actingAs($this->admin)->postJson('/api/payroll-runs', [
            'company_id' => $otherCompany->id, 'period_year' => 2026, 'period_month' => 6, 'employee_ids' => [$otherEmployee->id],
        ]);
        $this->actingAs($this->admin)->postJson("/api/payroll-runs/{$otherRunResponse->json('data.id')}/proceed-payslip")->assertOk();

        $response = $this->actingAs($this->admin)
            ->getJson("/api/payroll-reports/bpjs/detail?period_year=2026&period_month=6&company_id={$this->company->id}")
            ->assertOk();

        $ids = collect($response->json('data.data'))->pluck('employee_id');
        $this->assertTrue($ids->contains($ownEmployee->id));
        $this->assertFalse($ids->contains($otherEmployee->id));
    }

    // ---------- Summary konsisten dengan SUM Detail ----------

    public function test_summary_totals_match_sum_of_detail_rows(): void
    {
        $employeeA = Employee::factory()->create(['company_id' => $this->company->id]);
        $employeeB = Employee::factory()->create(['company_id' => $this->company->id]);
        $this->makeLockedRunWithEmployees([$employeeA, $employeeB]);

        $detail = $this->actingAs($this->admin)
            ->getJson('/api/payroll-reports/bpjs/detail?period_year=2026&period_month=6')
            ->json('data.data');
        $summary = $this->actingAs($this->admin)
            ->getJson('/api/payroll-reports/bpjs/summary?period_year=2026&period_month=6')
            ->json('data');

        $this->assertEquals(2, $summary['employee_count']);
        $this->assertEquals(
            collect($detail)->sum(fn ($r) => (float) $r['jht_employer']),
            (float) $summary['jht_employer']
        );
        $this->assertEquals(
            collect($detail)->sum(fn ($r) => (float) $r['bpjs_employer_total']),
            (float) $summary['bpjs_employer_total']
        );
    }

    // ---------- Revisi lama tidak ikut ke-hitung ----------

    public function test_only_current_revision_counted_after_recalculate(): void
    {
        $employee = Employee::factory()->create(['company_id' => $this->company->id]);
        $run = $this->makeLockedRunWithEmployees([$employee]);
        $this->actingAs($this->admin)->postJson("/api/payroll-runs/{$run->id}/proceed-payslip")->assertOk();

        $response = $this->actingAs($this->admin)
            ->getJson('/api/payroll-reports/bpjs/detail?period_year=2026&period_month=6')
            ->assertOk();

        $rows = collect($response->json('data.data'))->where('employee_id', $employee->id);
        $this->assertCount(1, $rows);
    }

    // ---------- Export ----------

    public function test_bpjs_detail_excel_export_downloads_successfully(): void
    {
        $employee = Employee::factory()->create(['company_id' => $this->company->id]);
        $this->makeLockedRunWithEmployees([$employee]);

        $response = $this->actingAs($this->admin)
            ->get('/api/payroll-reports/bpjs/detail/export/excel?period_year=2026&period_month=6');

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_bpjs_summary_excel_export_downloads_successfully(): void
    {
        $employee = Employee::factory()->create(['company_id' => $this->company->id]);
        $this->makeLockedRunWithEmployees([$employee]);

        $this->actingAs($this->admin)
            ->get('/api/payroll-reports/bpjs/summary/export/excel?period_year=2026&period_month=6')
            ->assertOk();
    }

    public function test_bpjs_detail_pdf_export_downloads_successfully(): void
    {
        $employee = Employee::factory()->create(['company_id' => $this->company->id]);
        $this->makeLockedRunWithEmployees([$employee]);

        $response = $this->actingAs($this->admin)
            ->get('/api/payroll-reports/bpjs/detail/export/pdf?period_year=2026&period_month=6');

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    // ---------- Authorization ----------

    public function test_unauthorized_user_cannot_access_bpjs_report(): void
    {
        $employee = Employee::factory()->create(['company_id' => $this->company->id]);
        $this->makeLockedRunWithEmployees([$employee]);
        $userWithoutPermission = User::factory()->create();

        $this->actingAs($userWithoutPermission)
            ->getJson('/api/payroll-reports/bpjs/detail?period_year=2026&period_month=6')
            ->assertForbidden();
    }

    public function test_missing_required_filters_returns_422(): void
    {
        $this->actingAs($this->admin)
            ->getJson('/api/payroll-reports/bpjs/detail')
            ->assertStatus(422);
    }
}
