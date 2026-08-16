<?php

namespace Tests\Unit;

use App\Models\User;
use App\Modules\ApprovalFlow\Enums\ApproverType;
use App\Modules\ApprovalFlow\Models\ApprovalFlow;
use App\Modules\ApprovalFlow\Models\ApprovalStep;
use App\Modules\Attendance\Services\ApprovalStepApproverResolver;
use App\Modules\Company\Models\Company;
use App\Modules\Employee\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ApprovalStepApproverResolverRegressionTest extends TestCase
{
    use RefreshDatabase;

    private ApprovalStepApproverResolver $resolver;
    private Company $company;
    private ApprovalFlow $flow;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = app(ApprovalStepApproverResolver::class);
        $this->company = Company::factory()->create();
        $this->flow = ApprovalFlow::create([
            'company_id' => $this->company->id, 'name' => 'Flow', 'code' => 'f-'.uniqid(), 'is_active' => true,
        ]);
    }

    // Employee-based caller (Leave/Attendance/Loan/HiringRequisition) — behavior tidak berubah.
    public function test_direct_manager_resolves_with_employee_subject(): void
    {
        $managerUser = User::factory()->create();
        $manager = Employee::factory()->create(['company_id' => $this->company->id, 'user_id' => $managerUser->id]);
        $subordinate = Employee::factory()->create(['company_id' => $this->company->id, 'manager_id' => $manager->id]);

        $step = ApprovalStep::create([
            'approval_flow_id' => $this->flow->id, 'sequence' => 1, 'name' => 'DM',
            'approver_type' => ApproverType::DirectManager->value, 'is_active' => true,
        ]);

        $result = $this->resolver->resolveApproverUserIds($step, $subordinate);

        $this->assertEquals([$managerUser->id], $result);
    }

    // Payroll-style caller — subject null, DirectManager eksplisit unresolvable.
    public function test_direct_manager_returns_empty_when_subject_is_null(): void
    {
        $step = ApprovalStep::create([
            'approval_flow_id' => $this->flow->id, 'sequence' => 1, 'name' => 'DM',
            'approver_type' => ApproverType::DirectManager->value, 'is_active' => true,
        ]);

        $this->assertSame([], $this->resolver->resolveApproverUserIds($step, null));
    }

    // SpecificRole & SpecificEmployee harus tetap resolve normal walau subject null (Payroll case).
    public function test_specific_role_resolves_regardless_of_subject(): void
    {
        $role = Role::firstOrCreate(['name' => 'reg-test-role', 'guard_name' => 'web']);
        $roleUser = User::factory()->create();
        $roleUser->assignRole($role);

        $step = ApprovalStep::create([
            'approval_flow_id' => $this->flow->id, 'sequence' => 1, 'name' => 'Role Step',
            'approver_type' => ApproverType::SpecificRole->value, 'approver_role_id' => $role->id, 'is_active' => true,
        ]);

        $this->assertEquals([$roleUser->id], $this->resolver->resolveApproverUserIds($step, null));
    }

    public function test_specific_employee_resolves_regardless_of_subject(): void
    {
        $targetUser = User::factory()->create();
        $targetEmployee = Employee::factory()->create(['company_id' => $this->company->id, 'user_id' => $targetUser->id]);

        $step = ApprovalStep::create([
            'approval_flow_id' => $this->flow->id, 'sequence' => 1, 'name' => 'Specific',
            'approver_type' => ApproverType::SpecificEmployee->value,
            'approver_employee_id' => $targetEmployee->id, 'is_active' => true,
        ]);

        $this->assertEquals([$targetUser->id], $this->resolver->resolveApproverUserIds($step, null));
    }
}