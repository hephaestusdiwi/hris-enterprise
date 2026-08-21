<?php

namespace Tests\Feature\Payroll;

use App\Modules\ApprovalFlow\Models\ApprovalFlow;
use App\Modules\ApprovalFlow\Models\ApprovalFlowAssignment;
use App\Modules\ApprovalFlow\Services\ApprovalFlowResolver;
use App\Modules\Branch\Models\Branch;
use App\Modules\Company\Models\Company;
use App\Modules\Employee\Models\Employee;
use App\Modules\JobLevel\Models\JobLevel;
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
            'company_id' => $this->company->id, 'name' => 'Company Default', 'code' => 'cd-'.uniqid(),
            'approval_type' => 'hiring_requisition', 'is_active' => true,
        ]);
        $employee = Employee::factory()->create(['company_id' => $this->company->id]);

        $resolved = $this->resolver->resolveFor($employee, 'hiring_requisition');

        $this->assertEquals($flow->id, $resolved?->id);
    }

    public function test_branch_scoped_flow_wins_over_company_default(): void
    {
        ApprovalFlow::create(['company_id' => $this->company->id, 'name' => 'Default', 'code' => 'd-'.uniqid(), 'approval_type' => 'hiring_requisition', 'is_active' => true]);

        $branch = Branch::factory()->create(['company_id' => $this->company->id]);
        $employee = Employee::factory()->create(['company_id' => $this->company->id, 'branch_id' => $branch->id]);
        $branchFlow = ApprovalFlow::create([
            'company_id' => $this->company->id, 'branch_id' => $branch->id,
            'name' => 'Branch Flow', 'code' => 'b-'.uniqid(), 'approval_type' => 'hiring_requisition', 'is_active' => true,
        ]);

        $resolved = $this->resolver->resolveFor($employee, 'hiring_requisition');

        $this->assertEquals($branchFlow->id, $resolved?->id);
    }

    public function test_employee_assignment_wins_over_everything(): void
    {
        ApprovalFlow::create(['company_id' => $this->company->id, 'name' => 'Default', 'code' => 'd2-'.uniqid(), 'approval_type' => 'hiring_requisition', 'is_active' => true]);

        $branch = Branch::factory()->create(['company_id' => $this->company->id]);
        $employee = Employee::factory()->create(['company_id' => $this->company->id, 'branch_id' => $branch->id]);
        ApprovalFlow::create([
            'company_id' => $this->company->id, 'branch_id' => $branch->id,
            'name' => 'Branch', 'code' => 'b2-'.uniqid(), 'approval_type' => 'hiring_requisition', 'is_active' => true,
        ]);
        $assignedFlow = ApprovalFlow::create([
            // job_level_id di sini cuma buat menghindari tabrakan scope sama
            // flow "Default" (company-wide) yang udah dibikin di atas — jalur
            // resolusi Assignment di resolver sama sekali gak peduli scope
            // kolom flow yang di-assign, dia lookup langsung dari
            // ApprovalFlowAssignment.employee_id. Job Level dipakai (bukan
            // Branch) biar gak numplek/gak nabrak juga sama flow "Branch"
            // yang udah ada di test ini.
            'company_id' => $this->company->id,
            'job_level_id' => JobLevel::create([
                'company_id' => $this->company->id, 'name' => 'Manager', 'code' => 'jl-'.uniqid(),
                'level_order' => 1, 'is_active' => true,
            ])->id,
            'name' => 'Assigned', 'code' => 'a-'.uniqid(), 'approval_type' => 'hiring_requisition', 'is_active' => true,
        ]);
        ApprovalFlowAssignment::create([
            'approval_flow_id' => $assignedFlow->id, 'employee_id' => $employee->id, 'is_active' => true,
        ]);

        $resolved = $this->resolver->resolveFor($employee, 'hiring_requisition');

        $this->assertEquals($assignedFlow->id, $resolved?->id);
    }

    public function test_returns_null_when_no_flow_configured_at_all(): void
    {
        $employee = Employee::factory()->create(['company_id' => $this->company->id]);

        $this->assertNull($this->resolver->resolveFor($employee, 'hiring_requisition'));
    }
}