<?php

namespace Tests\Feature\Payroll;

use App\Modules\Company\Models\Company;
use App\Modules\Employee\Models\Employee;
use App\Modules\Payroll\Models\ThrPolicy;
use App\Modules\Payroll\Services\ThrEligibilityService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Test murni service ThrEligibilityService (bukan lewat HTTP) — fokus ke
 * logic BARU Fase 7 (eligibility & proration), bukan re-test resolver salary/
 * BPJS/tax yang sudah punya cakupan test sendiri di module masing-masing.
 */
class ThrEligibilityTest extends TestCase
{
    use RefreshDatabase;

    private ThrEligibilityService $service;
    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(ThrEligibilityService::class);
        $this->company = Company::factory()->create();
    }

    // 3. THR eligibility bekerja.
    public function test_employee_below_minimum_service_is_not_eligible(): void
    {
        $policy = ThrPolicy::create([
            'company_id' => $this->company->id,
            'name' => 'Default THR Policy',
            'minimum_service_months' => 1,
            'full_service_months' => 12,
            'is_active' => true,
        ]);

        $referenceDate = Carbon::parse('2026-04-01');
        $employee = Employee::factory()->create([
            'company_id' => $this->company->id,
            'join_date' => Carbon::parse('2026-04-15'), // belum genap 1 bulan per 1 April
        ]);

        $this->assertFalse($this->service->isEligible($employee, $policy, $referenceDate));
    }

    public function test_employee_meeting_minimum_service_is_eligible(): void
    {
        $policy = ThrPolicy::create([
            'company_id' => $this->company->id,
            'name' => 'Default THR Policy',
            'minimum_service_months' => 1,
            'full_service_months' => 12,
            'is_active' => true,
        ]);

        $referenceDate = Carbon::parse('2026-04-01');
        $employee = Employee::factory()->create([
            'company_id' => $this->company->id,
            'join_date' => Carbon::parse('2026-02-01'), // 2 bulan masa kerja
        ]);

        $this->assertTrue($this->service->isEligible($employee, $policy, $referenceDate));
    }

    public function test_eligible_employees_excludes_resigned_before_reference_date(): void
    {
        $policy = ThrPolicy::create([
            'company_id' => $this->company->id, 'name' => 'Default', 'minimum_service_months' => 1,
            'full_service_months' => 12, 'is_active' => true,
        ]);

        $referenceDate = Carbon::parse('2026-04-01');
        $stillActive = Employee::factory()->create(['company_id' => $this->company->id, 'join_date' => Carbon::parse('2025-01-01')]);
        Employee::factory()->create([
            'company_id' => $this->company->id,
            'join_date' => Carbon::parse('2025-01-01'),
            'resign_date' => Carbon::parse('2026-02-01'), // resign sebelum referenceDate
        ]);

        $eligible = $this->service->eligibleEmployees($this->company->id, $policy, $referenceDate);

        $this->assertCount(1, $eligible);
        $this->assertEquals($stillActive->id, $eligible->first()->id);
    }

    // 4. THR prorata bekerja.
    public function test_proration_factor_is_full_for_service_at_or_above_full_service_months(): void
    {
        $policy = ThrPolicy::create([
            'company_id' => $this->company->id, 'name' => 'Default', 'minimum_service_months' => 1,
            'full_service_months' => 12, 'is_active' => true,
        ]);

        $referenceDate = Carbon::parse('2026-04-01');
        $employee = Employee::factory()->create(['company_id' => $this->company->id, 'join_date' => Carbon::parse('2024-01-01')]);

        $this->assertEquals('1.000000', $this->service->prorationFactor($employee, $policy, $referenceDate));
    }

    public function test_proration_factor_is_linear_for_partial_service(): void
    {
        $policy = ThrPolicy::create([
            'company_id' => $this->company->id, 'name' => 'Default', 'minimum_service_months' => 1,
            'full_service_months' => 12, 'is_active' => true,
        ]);

        $referenceDate = Carbon::parse('2026-04-01');
        // Join 1 Oktober 2025 -> per 1 April 2026 = 6 bulan masa kerja -> 6/12 = 0.5
        $employee = Employee::factory()->create(['company_id' => $this->company->id, 'join_date' => Carbon::parse('2025-10-01')]);

        $this->assertEquals('0.500000', $this->service->prorationFactor($employee, $policy, $referenceDate));
    }

    public function test_resolve_active_policy_picks_latest_effective_date_not_after_reference(): void
    {
        ThrPolicy::create([
            'company_id' => $this->company->id, 'name' => 'Policy Lama', 'minimum_service_months' => 3,
            'full_service_months' => 12, 'is_active' => true, 'effective_date' => Carbon::parse('2025-01-01'),
        ]);
        $newer = ThrPolicy::create([
            'company_id' => $this->company->id, 'name' => 'Policy Baru', 'minimum_service_months' => 1,
            'full_service_months' => 12, 'is_active' => true, 'effective_date' => Carbon::parse('2026-01-01'),
        ]);

        $resolved = $this->service->resolveActivePolicy($this->company->id, Carbon::parse('2026-04-01'));

        $this->assertEquals($newer->id, $resolved->id);
    }

    public function test_resolve_active_policy_ignores_inactive_policy(): void
    {
        ThrPolicy::create([
            'company_id' => $this->company->id, 'name' => 'Nonaktif', 'minimum_service_months' => 1,
            'full_service_months' => 12, 'is_active' => false,
        ]);

        $resolved = $this->service->resolveActivePolicy($this->company->id, Carbon::parse('2026-04-01'));

        $this->assertNull($resolved);
    }
}
