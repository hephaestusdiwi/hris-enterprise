<?php

namespace Tests\Feature\Report;

use App\Modules\ApprovalFlow\Enums\ApproverType;
use App\Modules\ApprovalFlow\Models\ApprovalFlow;
use App\Modules\ApprovalFlow\Models\ApprovalStep;
use App\Modules\CashAdvance\Enums\CashAdvanceApprovalRequestStatus;
use App\Modules\CashAdvance\Enums\CashAdvanceApprovalStepDecisionStatus;
use App\Modules\CashAdvance\Models\CashAdvanceApprovalRequest;
use App\Modules\CashAdvance\Models\CashAdvanceApprovalStepDecision;
use App\Modules\Company\Models\Company;
use App\Modules\Employee\Models\Employee;
use App\Modules\Expense\Enums\ExpenseClaimApprovalRequestStatus;
use App\Modules\Expense\Enums\ExpenseClaimApprovalStepDecisionStatus;
use App\Modules\Expense\Models\ExpenseClaimApprovalRequest;
use App\Modules\Expense\Models\ExpenseClaimApprovalStepDecision;
use App\Modules\Loan\Enums\LoanApprovalRequestStatus;
use App\Modules\Loan\Enums\LoanApprovalStepDecisionStatus;
use App\Modules\Loan\Models\LoanApprovalRequest;
use App\Modules\Loan\Models\LoanApprovalStepDecision;
use App\Modules\Reimbursement\Enums\ReimbursementApprovalRequestStatus;
use App\Modules\Reimbursement\Enums\ReimbursementApprovalStepDecisionStatus;
use App\Modules\Reimbursement\Models\ReimbursementApprovalRequest;
use App\Modules\Reimbursement\Models\ReimbursementApprovalStepDecision;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceApprovalAgingReportTest extends TestCase
{
    use RefreshDatabase;

    private function hrActor(Company $company): Employee
    {
        $hr = Employee::factory()->create(['company_id' => $company->id]);
        $hr->user->assignRole('hr');

        return $hr;
    }

    private function makeFlow(Company $company, ApproverType $approverType, ?Employee $approverEmployee = null): ApprovalStep
    {
        $flow = ApprovalFlow::create([
            'company_id' => $company->id,
            'name' => 'Flow Testing',
            'code' => 'FLOW-'.uniqid(),
            'is_active' => true,
        ]);

        return ApprovalStep::create([
            'approval_flow_id' => $flow->id,
            'sequence' => 1,
            'name' => 'Approval Manager',
            'approver_type' => $approverType->value,
            'approver_employee_id' => $approverEmployee?->id,
            'is_active' => true,
        ]);
    }

    public function test_aging_report_shows_pending_loan_with_direct_manager_approver(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $company = Company::factory()->create();

        $manager = Employee::factory()->create(['company_id' => $company->id]);
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'manager_employee_id' => $manager->id,
        ]);

        $step = $this->makeFlow($company, ApproverType::DirectManager);

        $approvalRequest = LoanApprovalRequest::create([
            'loan_id' => 999,
            'employee_id' => $employee->id,
            'approval_flow_id' => $step->approval_flow_id,
            'status' => LoanApprovalRequestStatus::Pending->value,
            'current_step_sequence' => 1,
            'requested_at' => now()->subDays(4),
        ]);

        LoanApprovalStepDecision::create([
            'loan_approval_request_id' => $approvalRequest->id,
            'approval_step_id' => $step->id,
            'sequence' => 1,
            'status' => LoanApprovalStepDecisionStatus::Pending->value,
        ]);

        $hr = $this->hrActor($company);

        $response = $this->actingAs($hr->user)->getJson('/api/reports/finance/approval-aging?source=loan');

        $response->assertOk();
        $response->assertJsonCount(1, 'data.rows');
        $this->assertSame(4, $response->json('data.rows.0.aging_days'));
        $this->assertSame($manager->user->name, $response->json('data.rows.0.approver'));
        $this->assertSame('LOAN-999', $response->json('data.rows.0.reference'));
    }

    public function test_aging_report_shows_pending_cash_advance_reimbursement_expense_with_specific_employee_approver(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $company = Company::factory()->create();
        $employee = Employee::factory()->create(['company_id' => $company->id]);
        $approver = Employee::factory()->create(['company_id' => $company->id]);

        $step = $this->makeFlow($company, ApproverType::SpecificEmployee, $approver);

        $caRequest = CashAdvanceApprovalRequest::create([
            'cash_advance_request_id' => 111,
            'employee_id' => $employee->id,
            'approval_flow_id' => $step->approval_flow_id,
            'status' => CashAdvanceApprovalRequestStatus::Pending->value,
            'current_step_sequence' => 1,
            'requested_at' => now()->subDays(2),
        ]);
        CashAdvanceApprovalStepDecision::create([
            'cash_advance_approval_request_id' => $caRequest->id,
            'approval_step_id' => $step->id,
            'sequence' => 1,
            'status' => CashAdvanceApprovalStepDecisionStatus::Pending->value,
        ]);

        $reimbRequest = ReimbursementApprovalRequest::create([
            'reimbursement_request_id' => 222,
            'employee_id' => $employee->id,
            'approval_flow_id' => $step->approval_flow_id,
            'status' => ReimbursementApprovalRequestStatus::Pending->value,
            'current_step_sequence' => 1,
            'requested_at' => now()->subDays(1),
        ]);
        ReimbursementApprovalStepDecision::create([
            'reimbursement_approval_request_id' => $reimbRequest->id,
            'approval_step_id' => $step->id,
            'sequence' => 1,
            'status' => ReimbursementApprovalStepDecisionStatus::Pending->value,
        ]);

        $expenseRequest = ExpenseClaimApprovalRequest::create([
            'expense_claim_id' => 333,
            'employee_id' => $employee->id,
            'approval_flow_id' => $step->approval_flow_id,
            'status' => ExpenseClaimApprovalRequestStatus::Pending->value,
            'current_step_sequence' => 1,
            'requested_at' => now()->subDays(6),
        ]);
        ExpenseClaimApprovalStepDecision::create([
            'expense_claim_approval_request_id' => $expenseRequest->id,
            'approval_step_id' => $step->id,
            'sequence' => 1,
            'status' => ExpenseClaimApprovalStepDecisionStatus::Pending->value,
        ]);

        $hr = $this->hrActor($company);

        $response = $this->actingAs($hr->user)->getJson('/api/reports/finance/approval-aging');

        $response->assertOk();
        $response->assertJsonCount(3, 'data.rows');
        foreach ($response->json('data.rows') as $row) {
            $this->assertSame($approver->user->name, $row['approver']);
        }
    }

    public function test_aging_report_excludes_already_decided_requests(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $company = Company::factory()->create();
        $employee = Employee::factory()->create(['company_id' => $company->id]);
        $approver = Employee::factory()->create(['company_id' => $company->id]);
        $step = $this->makeFlow($company, ApproverType::SpecificEmployee, $approver);

        LoanApprovalRequest::create([
            'loan_id' => 1,
            'employee_id' => $employee->id,
            'approval_flow_id' => $step->approval_flow_id,
            'status' => LoanApprovalRequestStatus::Approved->value,
            'current_step_sequence' => 1,
            'requested_at' => now()->subDays(10),
            'decided_at' => now()->subDays(9),
        ]);
        LoanApprovalRequest::create([
            'loan_id' => 2,
            'employee_id' => $employee->id,
            'approval_flow_id' => $step->approval_flow_id,
            'status' => LoanApprovalRequestStatus::Rejected->value,
            'current_step_sequence' => 1,
            'requested_at' => now()->subDays(10),
            'decided_at' => now()->subDays(9),
        ]);

        $hr = $this->hrActor($company);

        $response = $this->actingAs($hr->user)->getJson('/api/reports/finance/approval-aging?source=loan');

        $response->assertOk();
        $response->assertJsonCount(0, 'data.rows');
    }

    public function test_aging_report_filters_by_aging_range(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $company = Company::factory()->create();
        $employee = Employee::factory()->create(['company_id' => $company->id]);
        $approver = Employee::factory()->create(['company_id' => $company->id]);
        $step = $this->makeFlow($company, ApproverType::SpecificEmployee, $approver);

        foreach ([2, 10, 20] as $index => $days) {
            $request = LoanApprovalRequest::create([
                'loan_id' => 100 + $index,
                'employee_id' => $employee->id,
                'approval_flow_id' => $step->approval_flow_id,
                'status' => LoanApprovalRequestStatus::Pending->value,
                'current_step_sequence' => 1,
                'requested_at' => now()->subDays($days),
            ]);
            LoanApprovalStepDecision::create([
                'loan_approval_request_id' => $request->id,
                'approval_step_id' => $step->id,
                'sequence' => 1,
                'status' => LoanApprovalStepDecisionStatus::Pending->value,
            ]);
        }

        $hr = $this->hrActor($company);

        $response = $this->actingAs($hr->user)
            ->getJson('/api/reports/finance/approval-aging?source=loan&aging_min=5&aging_max=15');

        $response->assertOk();
        $response->assertJsonCount(1, 'data.rows');
        $this->assertSame(10, $response->json('data.rows.0.aging_days'));
    }

    public function test_employee_without_permission_cannot_view_approval_aging_report(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $employee = Employee::factory()->create();
        $employee->user->assignRole('employee');

        $this->actingAs($employee->user)
            ->getJson('/api/reports/finance/approval-aging')
            ->assertForbidden();
    }
}