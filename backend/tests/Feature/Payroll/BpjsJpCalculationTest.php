<?php

namespace Tests\Feature\Bpjs;

use App\Modules\Bpjs\Contracts\BpjsCalculationEngineInterface;
use App\Modules\Bpjs\Enums\BpjsProgram;
use App\Modules\Bpjs\Models\BpjsRateConfig;
use App\Modules\Bpjs\Models\CompanyBpjsSetting;
use App\Modules\Bpjs\Models\EmployeeBpjsParticipation;
use App\Modules\Company\Models\Company;
use App\Modules\Employee\Models\Employee;
use App\Modules\EmployeeSalary\DataTransferObjects\ResolvedSalaryLine;
use App\Modules\SalaryComponent\Enums\SalaryComponentCategory;
use App\Modules\SalaryComponent\Models\SalaryComponent;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fase 9 — aktivasi BPJS JP (Jaminan Pensiun). Test REAL BpjsCalculationEngine
 * (bukan stub) karena dependency-nya (BpjsRateResolver dkk) murni query DB,
 * tidak butuh fake seperti Payroll engine. Membuktikan calculateFlatProgram()
 * yang SUDAH ADA (dipakai Kesehatan/JHT) benar-benar reuse total buat JP
 * tanpa perubahan sama sekali — cuma orkestrasi di calculateForEmployee()
 * yang nambah ~15 baris, persis seperti janji komentar kode lama.
 */
class BpjsJpCalculationTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
        $this->employee = Employee::factory()->create(['company_id' => $this->company->id]);
    }

    private function wageLines(string $amount): array
    {
        $component = new SalaryComponent([
            'name' => 'Gaji Pokok', 'category' => SalaryComponentCategory::BasicSalary->value,
            'is_addition' => true, 'is_taxable' => true, 'include_in_bpjs_base' => true,
        ]);

        return [new ResolvedSalaryLine($component, $amount, null, null, 'structure')];
    }

    private function seedJpRateConfig(?string $wageBaseCap = '11086300.00'): void
    {
        BpjsRateConfig::create([
            'company_id' => $this->company->id, 'program' => BpjsProgram::Jp->value,
            'effective_date' => '2026-03-01', 'is_active' => true,
            'employee_rate_percentage' => '1.00', 'employer_rate_percentage' => '2.00',
            'wage_base_cap' => $wageBaseCap,
        ]);
    }

    private function seedParticipation(string $jpCostBearer = 'default'): EmployeeBpjsParticipation
    {
        return EmployeeBpjsParticipation::create([
            'employee_id' => $this->employee->id,
            'bpjs_employment_number' => '1234567890',
            'jht_cost_bearer' => 'default',
            'jp_cost_bearer' => $jpCostBearer,
        ]);
    }

    // 1. JP dihitung 1% karyawan + 2% company saat upah di bawah cap.
    public function test_jp_splits_one_percent_employee_two_percent_employer_below_cap(): void
    {
        $this->seedJpRateConfig();
        $this->seedParticipation();

        $engine = app(BpjsCalculationEngineInterface::class);
        $results = $engine->calculateForEmployee($this->employee, Carbon::parse('2026-06-01'), $this->wageLines('8000000.00'));

        $this->assertArrayHasKey('jp', $results);
        $jp = $results['jp'];
        $this->assertEquals('8000000.00', $jp->wageBaseUsed);
        $this->assertEquals('80000.00', $jp->employeeAmount); // 1% x 8jt
        $this->assertEquals('160000.00', $jp->employerAmount); // 2% x 8jt
    }

    // Wage cap diterapkan — upah di atas cap dipotong sampai batas cap.
    public function test_jp_applies_wage_base_cap_above_threshold(): void
    {
        $this->seedJpRateConfig('11086300.00');
        $this->seedParticipation();

        $engine = app(BpjsCalculationEngineInterface::class);
        $results = $engine->calculateForEmployee($this->employee, Carbon::parse('2026-06-01'), $this->wageLines('20000000.00'));

        $jp = $results['jp'];
        $this->assertEquals('11086300.00', $jp->wageBaseUsed);
        $this->assertEquals('110863.00', $jp->employeeAmount); // 1% x cap
        $this->assertEquals('221726.00', $jp->employerAmount); // 2% x cap
    }

    // 3. Company belum seed BpjsRateConfig program=jp -> JP tidak dihitung
    // sama sekali (mekanisme opt-in, konsisten sama program lain).
    public function test_jp_not_calculated_when_no_rate_config_seeded(): void
    {
        $this->seedParticipation(); // tanpa seedJpRateConfig()

        $engine = app(BpjsCalculationEngineInterface::class);
        $results = $engine->calculateForEmployee($this->employee, Carbon::parse('2026-06-01'), $this->wageLines('8000000.00'));

        $this->assertArrayNotHasKey('jp', $results);
    }

    // not_participating -> JP di-skip meski rate config ada.
    public function test_jp_not_calculated_when_employee_opts_out(): void
    {
        $this->seedJpRateConfig();
        $this->seedParticipation('not_participating');

        $engine = app(BpjsCalculationEngineInterface::class);
        $results = $engine->calculateForEmployee($this->employee, Carbon::parse('2026-06-01'), $this->wageLines('8000000.00'));

        $this->assertArrayNotHasKey('jp', $results);
    }

    // company_borne -> porsi karyawan dilipat ke employer, employee jadi 0.
    public function test_jp_company_borne_folds_employee_portion_into_employer(): void
    {
        $this->seedJpRateConfig();
        $this->seedParticipation('company_borne');

        $engine = app(BpjsCalculationEngineInterface::class);
        $results = $engine->calculateForEmployee($this->employee, Carbon::parse('2026-06-01'), $this->wageLines('8000000.00'));

        $jp = $results['jp'];
        $this->assertEquals('0.00', $jp->employeeAmount);
        $this->assertEquals('240000.00', $jp->employerAmount); // 160.000 + 80.000
    }

    // Default -> fallback ke CompanyBpjsSetting.default_jp_cost_bearer.
    public function test_jp_default_falls_back_to_company_setting(): void
    {
        $this->seedJpRateConfig();
        $this->seedParticipation('default');
        CompanyBpjsSetting::create([
            'company_id' => $this->company->id,
            'default_health_cost_bearer' => 'employee_borne',
            'default_jht_cost_bearer' => 'employee_borne',
            'default_jp_cost_bearer' => 'company_borne',
        ]);

        $engine = app(BpjsCalculationEngineInterface::class);
        $results = $engine->calculateForEmployee($this->employee, Carbon::parse('2026-06-01'), $this->wageLines('8000000.00'));

        $jp = $results['jp'];
        $this->assertEquals('0.00', $jp->employeeAmount);
        $this->assertEquals('240000.00', $jp->employerAmount);
    }

    // Regresi: JHT/JKM tetap jalan normal berbarengan dengan JP aktif — tidak
    // saling mengganggu.
    public function test_jht_and_jkm_still_calculate_normally_alongside_jp(): void
    {
        $this->seedJpRateConfig();
        BpjsRateConfig::create([
            'company_id' => $this->company->id, 'program' => 'jht', 'effective_date' => '2020-01-01',
            'is_active' => true, 'employee_rate_percentage' => '2.00', 'employer_rate_percentage' => '3.70',
        ]);
        BpjsRateConfig::create([
            'company_id' => $this->company->id, 'program' => 'jkm', 'effective_date' => '2020-01-01',
            'is_active' => true, 'employer_rate_percentage' => '0.30',
        ]);
        $this->seedParticipation();

        $engine = app(BpjsCalculationEngineInterface::class);
        $results = $engine->calculateForEmployee($this->employee, Carbon::parse('2026-06-01'), $this->wageLines('8000000.00'));

        $this->assertArrayHasKey('jht', $results);
        $this->assertArrayHasKey('jkm', $results);
        $this->assertArrayHasKey('jp', $results);
        $this->assertEquals('160000.00', $results['jht']->employeeAmount); // 2% x 8jt
        $this->assertEquals('24000.00', $results['jkm']->employerAmount); // 0.3% x 8jt
    }
}