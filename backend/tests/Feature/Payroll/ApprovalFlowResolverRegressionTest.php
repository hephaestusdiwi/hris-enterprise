<?php

namespace Tests\Unit\ApprovalFlow;

use App\Modules\ApprovalFlow\Models\ApprovalFlow;
use App\Modules\ApprovalFlow\Models\ApprovalFlowAssignment;
use App\Modules\ApprovalFlow\Services\ApprovalFlowResolver;
use App\Modules\Company\Models\Company;
use App\Modules\Employee\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Item 15 (bagian ApprovalFlow) — buktiin resolveFor(Employee) hasilnya
 * IDENTIK kayak sebelum refactor buat tiap tier cascading (Assignment,
 * JobLevel, Department, Branch, Company-default). Ini menyasar kode yang
 * benar-benar berubah (ApprovalFlowResolver), dipakai Leave/Attendance/Loan/
 * HiringRequisition — bukan nulis ulang test HTTP tiap domain itu satu-satu.
 */
class ApprovalFlowResolverRegressionTest extends TestCase
{
    use RefreshDatabase;

    private ApprovalFlowResolver $resolver;
    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = app(ApprovalFlowResolver::class);
        $this->company = Company::factory()->create();
    }

    public function test_resolves_company_default_when_nothing_more_specific(): void
    {
        $flow = ApprovalFlow::create([
            'company_id' => $this->company->id, 'name' => 'Company Default', 'code' => 'cd-'.uniqid(), 'is_active' => true,
        ]);
        $employee = Employee::factory()->create(['company_id' => $this->company->id]);

        $resolved = $this->resolver->resolveFor($employee);

        $this->assertEquals($flow->id, $resolved?->id);
    }

    public function test_branch_scoped_flow_wins_over_company_default(): void
    {
        ApprovalFlow::create(['company_id' => $this->company->id, 'name' => 'Default', 'code' => 'd-'.uniqid(), 'is_active' => true]);
        $employee = Employee::factory()->create(['company_id' => $this->company->id]);
        $branchFlow = ApprovalFlow::create([
            'company_id' => $this->company->id, 'branch_id' => $employee->branch_id,
            'name' => 'Branch Flow', 'code' => 'b-'.uniqid(), 'is_active' => true,
        ]);

        $resolved = $this->resolver->resolveFor($employee);

        $this->assertEquals($branchFlow->id, $resolved?->id);
    }

    public function test_employee_assignment_wins_over_everything(): void
    {
        ApprovalFlow::create(['company_id' => $this->company->id, 'name' => 'Default', 'code' => 'd2-'.uniqid(), 'is_active' => true]);
        $employee = Employee::factory()->create(['company_id' => $this->company->id]);
        ApprovalFlow::create([
            'company_id' => $this->company->id, 'branch_id' => $employee->branch_id,
            'name' => 'Branch', 'code' => 'b2-'.uniqid(), 'is_active' => true,
        ]);
        $assignedFlow = ApprovalFlow::create([
            'company_id' => $this->company->id, 'name' => 'Assigned', 'code' => 'a-'.uniqid(), 'is_active' => true,
        ]);
        ApprovalFlowAssignment::create([
            'approval_flow_id' => $assignedFlow->id, 'employee_id' => $employee->id, 'is_active' => true,
        ]);

        $resolved = $this->resolver->resolveFor($employee);

        $this->assertEquals($assignedFlow->id, $resolved?->id);
    }

    public function test_returns_null_when_no_flow_configured_at_all(): void
    {
        $employee = Employee::factory()->create(['company_id' => $this->company->id]);

        $this->assertNull($this->resolver->resolveFor($employee));
    }
}