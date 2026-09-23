<?php

namespace Tests\Feature\Payroll;

use App\Models\User;
use App\Modules\Bpjs\Contracts\BpjsCalculationEngineInterface;
use App\Modules\Company\Models\Company;
use App\Modules\Employee\Models\Employee;
use App\Modules\EmployeeSalary\Contracts\EmployeeSalaryResolverInterface;
use App\Modules\EmployeeSalary\DataTransferObjects\ResolvedSalaryLine;
use App\Modules\Payroll\Models\EmployeeAnnualTaxReconciliation;
use App\Modules\Payroll\Models\PayrollRun;
use App\Modules\Pph21\Contracts\TaxCalculationEngineInterface;
use App\Modules\Pph21\DataTransferObjects\AnnualReconciliationResult;
use App\Modules\Pph21\DataTransferObjects\MonthlyTaxResult;
use App\Modules\Pph21\Enums\TaxMethod;
use App\Modules\Pph21\Enums\TerCategory;
use App\Modules\SalaryComponent\Enums\SalaryComponentCategory;
use App\Modules\SalaryComponent\Models\SalaryComponent;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fase 8 prasyarat: AnnualReconciliationResult (breakdown lengkap buat BPA1/
 * 1721-A1) SEBELUMNYA cuma dipakai sekali pakai lalu dibuang — test ini
 * membuktikan sekarang PERSIST ke employee_annual_tax_reconciliations tepat
 * di final tax period, TIDAK di periode biasa, dan ikut ter-versioning kalau
 * payroll run direvisi (payslip baru = record reconciliation baru).
 */
class EmployeeAnnualTaxReconciliationTest extends TestCase
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

        $this->app->bind(EmployeeSalaryResolverInterface::class, function () {
            return new class implements EmployeeSalaryResolverInterface
            {
                public function resolveComponents($employee, $referenceDate): array
                {
                    $component = new SalaryComponent([
                        'name' => 'Gaji Pokok', 'category' => SalaryComponentCategory::BasicSalary->value,
                        'is_addition' => true, 'is_taxable' => true, 'include_in_bpjs_base' => true,
                    ]);

                    return [new ResolvedSalaryLine($component, '10000000.00', null, null, 'structure')];
                }

                public function resolveActiveVersion(\App\Modules\Employee\Models\Employee $employee, \Carbon\Carbon $referenceDate): ?\App\Modules\EmployeeSalary\Models\EmployeeSalary
                {
                    return null;
                }

                public function resolvePreview(\App\Modules\Employee\Models\Employee $employee, \Carbon\Carbon $referenceDate, string $salaryStructureCode, \Illuminate\Support\Collection $draftOverrides): array
                {
                    return [];
                }
            };
        });

        $this->app->bind(BpjsCalculationEngineInterface::class, fn () => new class implements BpjsCalculationEngineInterface
        {
            public function calculateForEmployee($employee, $referenceDate, array $resolvedSalaryLines): array
            {
                return [];
            }
        });
    }

    private function bindFakeTaxEngine(?AnnualReconciliationResult $annualResult): void
    {
        $this->app->bind(TaxCalculationEngineInterface::class, fn () => new class($annualResult) implements TaxCalculationEngineInterface
        {
            public function __construct(private ?AnnualReconciliationResult $annualResult)
            {
            }

            public function calculateMonthly($employee, $referenceDate, array $resolvedSalaryLines, array $resolvedBpjsContributions = []): ?MonthlyTaxResult
            {
                return new MonthlyTaxResult(
                    taxMethodApplied: TaxMethod::Gross, terCategory: TerCategory::A,
                    taxableGrossIncome: '10000000.00', terRatePercentageUsed: '5.00',
                    pph21Amount: '500000.00', takeHomePayDeduction: '500000.00',
                    grossUpAllowance: '0.00', noTaxIdSurchargeApplied: false, rateSourceId: null,
                );
            }

            public function calculateAnnualReconciliation($employee, $referenceDate, array $priorMonthsInYear, array $resolvedSalaryLines, array $resolvedBpjsContributions = []): ?AnnualReconciliationResult
            {
                return $this->annualResult;
            }
        });
    }

    private function makeAnnualResult(string $finalPeriodAdjustment = '-250000.00'): AnnualReconciliationResult
    {
        return new AnnualReconciliationResult(
            taxMethodApplied: TaxMethod::Gross,
            taxYear: 2026,
            totalGrossAnnual: '120000000.00',
            positionCostDeduction: '6000000.00',
            pensionDeduction: '1200000.00',
            ptkpAmount: '54000000.00',
            netAnnualIncome: '58800000.00',
            pkp: '58800000.00',
            annualTaxPasal17: '5500000.00',
            totalWithheldPriorMonths: '5750000.00',
            finalPeriodAdjustment: $finalPeriodAdjustment,
            grossUpAllowance: '0.00',
            noTaxIdSurchargeApplied: false,
        );
    }

    public function test_december_run_persists_annual_tax_reconciliation(): void
    {
        $this->bindFakeTaxEngine($this->makeAnnualResult());

        $employee = Employee::factory()->create(['company_id' => $this->company->id]);
        $run = PayrollRun::create([
            'company_id' => $this->company->id, 'type' => 'regular',
            'period_year' => 2026, 'period_month' => 12, 'status' => 'draft',
        ]);
        $run->participants()->sync([$employee->id]);

        $this->actingAs($this->admin)->postJson("/api/payroll-runs/{$run->id}/proceed-payslip")->assertOk();

        $this->assertDatabaseCount('employee_annual_tax_reconciliations', 1);
        $reconciliation = EmployeeAnnualTaxReconciliation::first();
        $payslip = \App\Modules\Payroll\Models\Payslip::where('employee_id', $employee->id)->where('payroll_run_id', $run->id)->firstOrFail();

        $this->assertEquals($employee->id, $reconciliation->employee_id);
        $this->assertEquals($payslip->id, $reconciliation->payslip_id);
        $this->assertEquals($run->id, $reconciliation->payroll_run_id);
        $this->assertEquals(2026, $reconciliation->tax_year);
        $this->assertEquals('120000000.00', $reconciliation->total_gross_annual);
        $this->assertEquals('6000000.00', $reconciliation->position_cost_deduction);
        $this->assertEquals('1200000.00', $reconciliation->pension_deduction);
        $this->assertEquals('54000000.00', $reconciliation->ptkp_amount);
        $this->assertEquals('58800000.00', $reconciliation->pkp);
        $this->assertEquals('5500000.00', $reconciliation->annual_tax_pasal17);
        $this->assertEquals('5750000.00', $reconciliation->total_withheld_prior_months);
        $this->assertEquals('-250000.00', $reconciliation->final_period_adjustment);
        $this->assertEquals('lebih_bayar', $reconciliation->status());
    }

    public function test_non_final_period_does_not_persist_reconciliation(): void
    {
        $this->bindFakeTaxEngine($this->makeAnnualResult());

        $employee = Employee::factory()->create(['company_id' => $this->company->id]);
        $run = PayrollRun::create([
            'company_id' => $this->company->id, 'type' => 'regular',
            'period_year' => 2026, 'period_month' => 6, 'status' => 'draft',
        ]);
        $run->participants()->sync([$employee->id]);

        $this->actingAs($this->admin)->postJson("/api/payroll-runs/{$run->id}/proceed-payslip")->assertOk();

        $this->assertDatabaseCount('employee_annual_tax_reconciliations', 0);
    }

    public function test_recalculation_creates_new_reconciliation_tied_to_new_payslip(): void
    {
        $this->bindFakeTaxEngine($this->makeAnnualResult('-250000.00'));

        $employee = Employee::factory()->create(['company_id' => $this->company->id]);
        $run = PayrollRun::create([
            'company_id' => $this->company->id, 'type' => 'regular',
            'period_year' => 2026, 'period_month' => 12, 'status' => 'draft',
        ]);
        $run->participants()->sync([$employee->id]);

        // Panggil proceed-payslip DUA KALI (simulasi recalculate) — sengaja
        // pakai fake tax engine yang SAMA untuk kedua panggilan: mengganti
        // binding di tengah test tidak reliable di sini karena Laravel
        // meng-cache instance controller di Route object untuk sisa test
        // method yang sama (bukan gotcha di kode produksi — di request HTTP
        // sungguhan tiap request memang container/proses baru). Yang mau
        // dibuktikan di sini murni: recalculate bikin PAYSLIP BARU (revisi
        // baru), dan reconciliation-nya ikut baru juga tersambung ke payslip
        // yang baru itu — bukan overwrite baris lama.
        $this->actingAs($this->admin)->postJson("/api/payroll-runs/{$run->id}/proceed-payslip")->assertOk();
        $first = EmployeeAnnualTaxReconciliation::first();

        $this->actingAs($this->admin)->postJson("/api/payroll-runs/{$run->id}/proceed-payslip")->assertOk();

        $this->assertDatabaseCount('employee_annual_tax_reconciliations', 2);
        $all = EmployeeAnnualTaxReconciliation::orderBy('id')->get();
        $second = $all->last();

        $this->assertNotEquals($first->id, $second->id);
        $this->assertNotEquals($first->payslip_id, $second->payslip_id, 'Reconciliation baru harus terikat ke payslip revisi baru, bukan payslip lama.');
        // Payslip lama (revisi 1) sudah tidak ada di payslips table aktif untuk
        // employee ini? Tidak — Payslip lama TETAP ada (histori revisi), begitu
        // juga reconciliation-nya — keduanya immutable, bukan dihapus/di-update.
        $this->assertDatabaseHas('payslips', ['id' => $first->payslip_id]);
        $this->assertDatabaseHas('payslips', ['id' => $second->payslip_id]);
    }
}
