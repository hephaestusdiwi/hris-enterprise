<?php

namespace Tests\Feature\Report;

use App\Modules\CashAdvance\Enums\CashAdvanceRequestStatus;
use App\Modules\CashAdvance\Models\CashAdvanceCategory;
use App\Modules\CashAdvance\Models\CashAdvancePolicy;
use App\Modules\CashAdvance\Models\CashAdvanceRequest;
use App\Modules\CashAdvance\Models\CashAdvanceRequestItem;
use App\Modules\Company\Models\Company;
use App\Modules\Department\Models\Department;
use App\Modules\Employee\Models\Employee;
use App\Modules\Expense\Enums\ExpenseClaimStatus;
use App\Modules\Expense\Models\ExpenseCategory;
use App\Modules\Expense\Models\ExpenseClaim;
use App\Modules\Expense\Models\ExpensePolicy;
use App\Modules\Expense\Models\ExpensePolicyAssignment;
use App\Modules\Reimbursement\Enums\ReimbursementBalanceStatus;
use App\Modules\Reimbursement\Enums\ReimbursementRequestStatus;
use App\Modules\Reimbursement\Models\ReimbursementBalance;
use App\Modules\Reimbursement\Models\ReimbursementBenefit;
use App\Modules\Reimbursement\Models\ReimbursementPolicy;
use App\Modules\Reimbursement\Models\ReimbursementRequest;
use App\Modules\Reimbursement\Models\ReimbursementRequestItem;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceSpendingReportTest extends TestCase
{
    use RefreshDatabase;

    private function makeEmployee(Company $company, ?Department $department = null): Employee
    {
        return Employee::factory()->create([
            'company_id' => $company->id,
            'department_id' => $department?->id,
        ]);
    }

    private function makeExpenseClaim(Employee $employee, ExpenseCategory $category, string $amount, string $date): ExpenseClaim
    {
        // Reuse assignment kalau employee ini sudah pernah dibikinkan satu
        // (helper ini bisa dipanggil >1x untuk employee yang sama dalam
        // 1 test) -- assignment baru dengan effective_date yang sama persis
        // bakal nabrak unique constraint (employee_id, effective_date).
        $assignment = ExpensePolicyAssignment::where('employee_id', $employee->id)->first();

        if (! $assignment) {
            $policy = ExpensePolicy::create([
                'company_id' => $employee->company_id,
                'name' => 'Kebijakan Expense',
                'effective_date' => now()->subYear()->toDateString(),
                'is_active' => true,
            ]);

            $assignment = ExpensePolicyAssignment::create([
                'employee_id' => $employee->id,
                'expense_policy_id' => $policy->id,
                'effective_date' => now()->subYear()->toDateString(),
                'is_active' => true,
            ]);
        }

        return ExpenseClaim::create([
            'employee_id' => $employee->id,
            'expense_policy_assignment_id' => $assignment->id,
            'expense_category_id' => $category->id,
            'expense_date' => $date,
            'amount' => $amount,
            'status' => ExpenseClaimStatus::Approved->value,
        ]);
    }

    private function makeCashAdvanceItem(Employee $employee, CashAdvanceCategory $category, string $amount, string $date): CashAdvanceRequestItem
    {
        $policy = CashAdvancePolicy::create([
            'name' => 'Kebijakan CA',
            'effective_date' => now()->subYear()->toDateString(),
            'settlement_due_days' => 30,
            'is_active' => true,
        ]);

        $request = CashAdvanceRequest::create([
            'employee_id' => $employee->id,
            'cash_advance_policy_id' => $policy->id,
            'purpose' => 'Testing',
            'date_of_use' => $date,
            'total_amount' => $amount,
            'status' => CashAdvanceRequestStatus::Approved->value,
        ]);

        return CashAdvanceRequestItem::create([
            'cash_advance_request_id' => $request->id,
            'cash_advance_category_id' => $category->id,
            'name' => 'Item Testing',
            'amount' => $amount,
        ]);
    }

    private function makeReimbursementItem(Employee $employee, ReimbursementBenefit $benefit, string $amount, string $date): ReimbursementRequestItem
    {
        $policy = ReimbursementPolicy::create([
            'name' => 'Kebijakan Reimbursement',
            'effective_date' => now()->subYear()->toDateString(),
            'is_active' => true,
        ]);

        $balance = ReimbursementBalance::create([
            'employee_id' => $employee->id,
            'reimbursement_policy_id' => $policy->id,
            'assigned_amount' => 10000000,
            'effective_date' => now()->subYear()->toDateString(),
            'status' => ReimbursementBalanceStatus::Active->value,
        ]);

        $request = ReimbursementRequest::create([
            'employee_id' => $employee->id,
            'reimbursement_policy_id' => $policy->id,
            'reimbursement_balance_id' => $balance->id,
            'transaction_date' => $date,
            'total_amount' => $amount,
            'status' => ReimbursementRequestStatus::Approved->value,
        ]);

        return ReimbursementRequestItem::create([
            'reimbursement_request_id' => $request->id,
            'reimbursement_benefit_id' => $benefit->id,
            'amount' => $amount,
        ]);
    }

    public function test_spending_report_aggregates_all_three_sources(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $company = Company::factory()->create();
        $employee = $this->makeEmployee($company);

        $expenseCategory = ExpenseCategory::create([
            'company_id' => $company->id,
            'name' => 'Transportasi',
            'code' => 'TRANSPORT-'.uniqid(),
            'is_active' => true,
        ]);
        $cashAdvanceCategory = CashAdvanceCategory::create([
            'name' => 'Konsumsi',
            'code' => 'KONSUMSI-'.uniqid(),
            'is_active' => true,
        ]);
        $benefit = ReimbursementBenefit::create([
            'reimbursement_policy_id' => ReimbursementPolicy::create([
                'name' => 'Policy Dummy',
                'effective_date' => now()->subYear()->toDateString(),
                'is_active' => true,
            ])->id,
            'name' => 'Kesehatan',
            'is_active' => true,
        ]);

        $this->makeExpenseClaim($employee, $expenseCategory, '100000.00', '2026-01-10');
        $this->makeCashAdvanceItem($employee, $cashAdvanceCategory, '80000.00', '2026-01-15');
        $this->makeReimbursementItem($employee, $benefit, '70000.00', '2026-01-20');

        $hr = Employee::factory()->create(['company_id' => $company->id]);
        $hr->user->assignRole('hr');

        $response = $this->actingAs($hr->user)
            ->getJson('/api/reports/finance/spending');

        $response->assertOk();
        $response->assertJsonCount(3, 'data.rows');
        $this->assertSame('250000.00', $response->json('data.summary.total_amount'));
        $this->assertSame(3, $response->json('data.summary.total_transactions'));
    }

    public function test_spending_report_filters_by_source_type_and_category(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $company = Company::factory()->create();
        $employee = $this->makeEmployee($company);

        $expenseCategory = ExpenseCategory::create([
            'company_id' => $company->id,
            'name' => 'Transportasi',
            'code' => 'TRANSPORT-'.uniqid(),
            'is_active' => true,
        ]);
        $cashAdvanceCategory = CashAdvanceCategory::create([
            'name' => 'Transportasi',
            'code' => 'CA-TRANSPORT-'.uniqid(),
            'is_active' => true,
        ]);

        $this->makeExpenseClaim($employee, $expenseCategory, '100000.00', '2026-01-10');
        $this->makeCashAdvanceItem($employee, $cashAdvanceCategory, '50000.00', '2026-01-12');

        $hr = Employee::factory()->create(['company_id' => $company->id]);
        $hr->user->assignRole('hr');

        $bySourceType = $this->actingAs($hr->user)
            ->getJson('/api/reports/finance/spending?source_type=cash_advance');
        $bySourceType->assertOk();
        $bySourceType->assertJsonCount(1, 'data.rows');
        $this->assertSame('50000.00', $bySourceType->json('data.summary.total_amount'));

        $byCategory = $this->actingAs($hr->user)
            ->getJson('/api/reports/finance/spending?category=Transportasi');
        $byCategory->assertOk();
        $byCategory->assertJsonCount(2, 'data.rows');
        $this->assertSame('150000.00', $byCategory->json('data.summary.total_amount'));
    }

    public function test_spending_report_filters_by_company_department_and_date_range(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();
        $deptFinance = Department::create([
            'company_id' => $companyA->id,
            'name' => 'Finance',
            'code' => 'FIN-'.uniqid(),
            'is_active' => true,
        ]);

        $employeeA = $this->makeEmployee($companyA, $deptFinance);
        $employeeB = $this->makeEmployee($companyB);

        $category = ExpenseCategory::create([
            'company_id' => $companyA->id,
            'name' => 'Umum',
            'code' => 'UMUM-A-'.uniqid(),
            'is_active' => true,
        ]);
        $categoryB = ExpenseCategory::create([
            'company_id' => $companyB->id,
            'name' => 'Umum',
            'code' => 'UMUM-B-'.uniqid(),
            'is_active' => true,
        ]);

        $this->makeExpenseClaim($employeeA, $category, '100000.00', '2026-01-10');
        $this->makeExpenseClaim($employeeA, $category, '200000.00', '2025-06-01'); // di luar range tanggal
        $this->makeExpenseClaim($employeeB, $categoryB, '999999.00', '2026-01-10'); // company lain

        $hr = Employee::factory()->create(['company_id' => $companyA->id]);
        $hr->user->assignRole('hr');

        $response = $this->actingAs($hr->user)->getJson(
            '/api/reports/finance/spending?'
            .'company_id='.$companyA->id
            .'&department_id='.$deptFinance->id
            .'&date_from=2026-01-01&date_to=2026-01-31'
        );

        $response->assertOk();
        $response->assertJsonCount(1, 'data.rows');
        $this->assertSame('100000.00', $response->json('data.summary.total_amount'));
    }

    public function test_employee_without_permission_cannot_view_spending_report(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $employee = Employee::factory()->create();
        $employee->user->assignRole('employee');

        $this->actingAs($employee->user)
            ->getJson('/api/reports/finance/spending')
            ->assertForbidden();
    }
}