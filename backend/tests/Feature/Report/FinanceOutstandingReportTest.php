<?php

namespace Tests\Feature\Report;

use App\Modules\CashAdvance\Enums\CashAdvanceRequestStatus;
use App\Modules\CashAdvance\Models\CashAdvancePolicy;
use App\Modules\CashAdvance\Models\CashAdvanceRequest;
use App\Modules\Company\Models\Company;
use App\Modules\Employee\Models\Employee;
use App\Modules\Expense\Enums\ExpenseClaimStatus;
use App\Modules\Expense\Models\ExpenseCategory;
use App\Modules\Expense\Models\ExpenseClaim;
use App\Modules\Expense\Models\ExpensePolicy;
use App\Modules\Expense\Models\ExpensePolicyAssignment;
use App\Modules\Loan\Enums\LoanInstallmentStatus;
use App\Modules\Loan\Enums\LoanInterestType;
use App\Modules\Loan\Enums\LoanStatus;
use App\Modules\Loan\Models\Loan;
use App\Modules\Loan\Models\LoanInstallment;
use App\Modules\Reimbursement\Enums\ReimbursementBalanceStatus;
use App\Modules\Reimbursement\Enums\ReimbursementRequestStatus;
use App\Modules\Reimbursement\Models\ReimbursementBalance;
use App\Modules\Reimbursement\Models\ReimbursementPolicy;
use App\Modules\Reimbursement\Models\ReimbursementRequest;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceOutstandingReportTest extends TestCase
{
    use RefreshDatabase;

    private function hrActor(Company $company): Employee
    {
        $hr = Employee::factory()->create(['company_id' => $company->id]);
        $hr->user->assignRole('hr');

        return $hr;
    }

    private function makeCashAdvance(Employee $employee, string $amount, CashAdvanceRequestStatus $status, ?\Carbon\Carbon $disbursedAt): CashAdvanceRequest
    {
        $policy = CashAdvancePolicy::create([
            'name' => 'Kebijakan CA',
            'effective_date' => now()->subYear()->toDateString(),
            'settlement_due_days' => 30,
            'is_active' => true,
        ]);

        return CashAdvanceRequest::create([
            'employee_id' => $employee->id,
            'cash_advance_policy_id' => $policy->id,
            'purpose' => 'Testing',
            'date_of_use' => now()->subDays(10)->toDateString(),
            'total_amount' => $amount,
            'status' => $status->value,
            'disbursed_at' => $disbursedAt,
        ]);
    }

    private function makeLoan(Employee $employee, string $principal, LoanStatus $status, ?\Carbon\Carbon $disbursedAt): Loan
    {
        return Loan::create([
            'employee_id' => $employee->id,
            'principal' => $principal,
            'interest_rate' => 0,
            'interest_type' => LoanInterestType::None->value,
            'tenor' => 5,
            'installment_amount' => bcdiv($principal, '5', 2),
            'total_repayment' => $principal,
            'first_deduction_period_year' => now()->year,
            'first_deduction_period_month' => now()->month,
            'purpose' => 'Testing',
            'status' => $status->value,
            'disbursed_at' => $disbursedAt,
        ]);
    }

    private function makeInstallment(Loan $loan, int $number, string $principalPortion, LoanInstallmentStatus $status): LoanInstallment
    {
        return LoanInstallment::create([
            'loan_id' => $loan->id,
            'installment_number' => $number,
            'payroll_period_year' => now()->year,
            'payroll_period_month' => now()->month,
            'principal_portion' => $principalPortion,
            'interest_portion' => '0.00',
            'original_amount' => $principalPortion,
            'amount' => $principalPortion,
            'status' => $status->value,
            'paid_at' => $status === LoanInstallmentStatus::Paid ? now() : null,
        ]);
    }

    private function makeReimbursement(Employee $employee, string $amount, ReimbursementRequestStatus $status, ?\Carbon\Carbon $decidedAt, ?\Carbon\Carbon $disbursedAt): ReimbursementRequest
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

        return ReimbursementRequest::create([
            'employee_id' => $employee->id,
            'reimbursement_policy_id' => $policy->id,
            'reimbursement_balance_id' => $balance->id,
            'transaction_date' => now()->subDays(5)->toDateString(),
            'total_amount' => $amount,
            'status' => $status->value,
            'decided_at' => $decidedAt,
            'disbursed_at' => $disbursedAt,
        ]);
    }

    private function makeExpenseClaim(Employee $employee, string $amount, ExpenseClaimStatus $status, ?\Carbon\Carbon $decidedAt, ?\Carbon\Carbon $paidAt): ExpenseClaim
    {
        $category = ExpenseCategory::create([
            'company_id' => $employee->company_id,
            'name' => 'Umum',
            'code' => 'UMUM-'.uniqid(),
            'is_active' => true,
        ]);

        $assignment = ExpensePolicyAssignment::firstOrCreate(
            ['employee_id' => $employee->id],
            [
                'expense_policy_id' => ExpensePolicy::create([
                    'company_id' => $employee->company_id,
                    'name' => 'Kebijakan Expense',
                    'effective_date' => now()->subYear()->toDateString(),
                    'is_active' => true,
                ])->id,
                'effective_date' => now()->subYear()->toDateString(),
                'is_active' => true,
            ],
        );

        return ExpenseClaim::create([
            'employee_id' => $employee->id,
            'expense_policy_assignment_id' => $assignment->id,
            'expense_category_id' => $category->id,
            'expense_date' => now()->subDays(5)->toDateString(),
            'amount' => $amount,
            'status' => $status->value,
            'decided_at' => $decidedAt,
            'paid_at' => $paidAt,
        ]);
    }

    public function test_outstanding_report_includes_disbursed_unsettled_cash_advance(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $company = Company::factory()->create();
        $employee = Employee::factory()->create(['company_id' => $company->id]);

        $this->makeCashAdvance($employee, '500000.00', CashAdvanceRequestStatus::NeedSettlement, now()->subDays(7));
        // Completed -- sudah beres, TIDAK boleh muncul.
        $this->makeCashAdvance($employee, '999999.00', CashAdvanceRequestStatus::Completed, now()->subDays(30));

        $hr = $this->hrActor($company);

        $response = $this->actingAs($hr->user)->getJson('/api/reports/finance/outstanding?source=cash_advance');

        $response->assertOk();
        $response->assertJsonCount(1, 'data.rows');
        $this->assertSame('500000.00', $response->json('data.rows.0.outstanding_amount'));
        $this->assertNull($response->json('data.rows.0.paid_amount'));
    }

    public function test_outstanding_report_calculates_loan_outstanding_principal_correctly(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $company = Company::factory()->create();
        $employee = Employee::factory()->create(['company_id' => $company->id]);

        $loan = $this->makeLoan($employee, '1000000.00', LoanStatus::Active, now()->subDays(20));
        $this->makeInstallment($loan, 1, '200000.00', LoanInstallmentStatus::Paid);
        $this->makeInstallment($loan, 2, '200000.00', LoanInstallmentStatus::Scheduled);

        // Loan lain yang sudah lunas total -- harus tidak muncul walau
        // statusnya belum sempat dipindah Payroll ke Completed/Settled.
        $paidOffLoan = $this->makeLoan($employee, '100000.00', LoanStatus::Active, now()->subDays(60));
        $this->makeInstallment($paidOffLoan, 1, '100000.00', LoanInstallmentStatus::Paid);

        $hr = $this->hrActor($company);

        $response = $this->actingAs($hr->user)->getJson('/api/reports/finance/outstanding?source=loan');

        $response->assertOk();
        $response->assertJsonCount(1, 'data.rows');
        $this->assertSame('800000.00', $response->json('data.rows.0.outstanding_amount'));
        $this->assertSame('200000.00', $response->json('data.rows.0.paid_amount'));
    }

    public function test_outstanding_report_includes_approved_unpaid_reimbursement_and_expense_claim(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $company = Company::factory()->create();
        $employee = Employee::factory()->create(['company_id' => $company->id]);

        $this->makeReimbursement($employee, '150000.00', ReimbursementRequestStatus::Approved, now()->subDays(3), null);
        // Sudah disbursed -- TIDAK boleh muncul lagi sebagai outstanding.
        $this->makeReimbursement($employee, '999999.00', ReimbursementRequestStatus::Approved, now()->subDays(3), now());

        $this->makeExpenseClaim($employee, '75000.00', ExpenseClaimStatus::Approved, now()->subDays(2), null);
        // Sudah paid -- TIDAK boleh muncul.
        $this->makeExpenseClaim($employee, '999999.00', ExpenseClaimStatus::Approved, now()->subDays(2), now());

        $hr = $this->hrActor($company);

        $response = $this->actingAs($hr->user)->getJson('/api/reports/finance/outstanding');

        $response->assertOk();
        $response->assertJsonCount(2, 'data.rows');
        $this->assertSame('225000.00', $response->json('data.summary.total_outstanding'));
    }

    public function test_outstanding_report_calculates_aging_days(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $company = Company::factory()->create();
        $employee = Employee::factory()->create(['company_id' => $company->id]);

        $this->makeCashAdvance($employee, '200000.00', CashAdvanceRequestStatus::NeedSettlement, now()->subDays(15));

        $hr = $this->hrActor($company);

        $response = $this->actingAs($hr->user)->getJson('/api/reports/finance/outstanding?source=cash_advance');

        $response->assertOk();
        $this->assertSame(15, $response->json('data.rows.0.aging_days'));
    }

    public function test_employee_without_permission_cannot_view_outstanding_report(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $employee = Employee::factory()->create();
        $employee->user->assignRole('employee');

        $this->actingAs($employee->user)
            ->getJson('/api/reports/finance/outstanding')
            ->assertForbidden();
    }
}