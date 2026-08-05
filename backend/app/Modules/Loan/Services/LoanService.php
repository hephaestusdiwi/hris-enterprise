<?php

namespace App\Modules\Loan\Services;

use App\Models\User;
use App\Modules\Employee\Models\Employee;
use App\Modules\Loan\Enums\LoanInstallmentStatus;
use App\Modules\Loan\Enums\LoanStatus;
use App\Modules\Loan\Exceptions\LoanValidationException;
use App\Modules\Loan\Models\Loan;
use App\Modules\Loan\Models\LoanInstallment;
use App\Modules\Loan\Support\LoanMath;
use Carbon\Carbon;

class LoanService
{
    public function __construct(private LoanApprovalService $approvalService)
    {
    }

    /**
     * @param array{principal: string|float, interest_rate?: string|float|null, tenor: int,
     *              first_deduction_date: string, purpose?: ?string} $data
     */
    public function create(Employee $employee, array $data, ?User $actor): Loan
    {
        $tenor = (int) $data['tenor'];

        if ($tenor < 1) {
            throw new LoanValidationException('Tenor minimal 1 kali cicilan.');
        }

        $firstDeductionDate = Carbon::parse($data['first_deduction_date']);
        $plan = $this->calculateInstallmentPlan((string) $data['principal'], $this->nullableToString($data['interest_rate'] ?? null), $tenor);

        return Loan::create([
            'employee_id' => $employee->id,
            'principal' => $data['principal'],
            'interest_rate' => $data['interest_rate'] ?? null,
            'tenor' => $tenor,
            'installment_amount' => $plan['installment_amount'],
            'total_repayment' => $plan['total_repayment'],
            'first_deduction_period_year' => $firstDeductionDate->year,
            'first_deduction_period_month' => $firstDeductionDate->month,
            'purpose' => $data['purpose'] ?? null,
            'status' => LoanStatus::Draft->value,
            'created_by_user_id' => $actor?->id,
        ]);
    }

    public function update(Loan $loan, array $data): Loan
    {
        if (! $loan->isEditable()) {
            throw new LoanValidationException('Loan yang sudah disubmit tidak dapat diubah. Batalkan lalu buat baru bila perlu.');
        }

        $principal = (string) ($data['principal'] ?? $loan->principal);
        $interestRate = array_key_exists('interest_rate', $data) ? $data['interest_rate'] : $loan->interest_rate;
        $tenor = (int) ($data['tenor'] ?? $loan->tenor);

        if ($tenor < 1) {
            throw new LoanValidationException('Tenor minimal 1 kali cicilan.');
        }

        $firstDeductionDate = isset($data['first_deduction_date'])
            ? Carbon::parse($data['first_deduction_date'])
            : Carbon::createFromDate((int) $loan->first_deduction_period_year, (int) $loan->first_deduction_period_month, 1);

        $plan = $this->calculateInstallmentPlan($principal, $this->nullableToString($interestRate), $tenor);

        $loan->update([
            'principal' => $principal,
            'interest_rate' => $interestRate,
            'tenor' => $tenor,
            'installment_amount' => $plan['installment_amount'],
            'total_repayment' => $plan['total_repayment'],
            'first_deduction_period_year' => $firstDeductionDate->year,
            'first_deduction_period_month' => $firstDeductionDate->month,
            'purpose' => $data['purpose'] ?? $loan->purpose,
        ]);

        return $loan->fresh();
    }

    public function submit(Loan $loan): Loan
    {
        if ($loan->status !== LoanStatus::Draft) {
            throw new LoanValidationException('Hanya loan berstatus Draft yang bisa disubmit.');
        }

        $loan->update([
            'status' => LoanStatus::Pending->value,
            'requested_at' => now(),
        ]);

        $this->approvalService->initiate($loan);

        return $loan->fresh();
    }

    public function disburse(Loan $loan): Loan
    {
        if ($loan->status !== LoanStatus::Approved) {
            throw new LoanValidationException('Hanya loan berstatus Approved yang bisa dicairkan.');
        }

        $this->generateInstallments($loan);

        $loan->update([
            'status' => LoanStatus::Active->value,
            'disbursed_at' => now(),
        ]);

        return $loan->fresh();
    }

    public function cancel(Loan $loan, string $reason): Loan
    {
        if (! in_array($loan->status, [LoanStatus::Draft, LoanStatus::Pending, LoanStatus::Approved], true)) {
            throw new LoanValidationException('Loan berstatus Active/Completed/Cancelled tidak bisa dibatalkan lewat aksi ini.');
        }

        $this->approvalService->cancelApprovalIfAny($loan);

        $loan->update([
            'status' => LoanStatus::Cancelled->value,
            'cancelled_at' => now(),
            'cancel_reason' => $reason,
        ]);

        return $loan->fresh();
    }

    /**
     * Hook untuk Payroll Generator (belum dibangun) — dipanggil setelah cicilan
     * ini beneran dipotong dari payroll run. Loan otomatis Completed begitu
     * tidak ada installment Scheduled yang tersisa.
     */
    public function markInstallmentPaid(LoanInstallment $installment, ?int $employeeDeductionId = null): LoanInstallment
    {
        if ($installment->status !== LoanInstallmentStatus::Scheduled) {
            throw new LoanValidationException('Installment ini bukan berstatus Scheduled.');
        }

        $installment->update([
            'status' => LoanInstallmentStatus::Paid->value,
            'paid_at' => now(),
            'employee_deduction_id' => $employeeDeductionId,
        ]);

        $loan = $installment->loan;
        $stillOutstanding = $loan->installments()->where('status', LoanInstallmentStatus::Scheduled->value)->exists();

        if (! $stillOutstanding && $loan->status === LoanStatus::Active) {
            $loan->update(['status' => LoanStatus::Completed->value, 'completed_at' => now()]);
        }

        return $installment->fresh();
    }

    /**
     * @return array{rows: array<int, array{installment_number:int, principal_portion:string, interest_portion:string, amount:string}>,
     *               installment_amount: string, total_repayment: string}
     */
    public function calculateInstallmentPlan(string $principal, ?string $interestRate, int $tenor): array
    {
        $interestRate = $interestRate ?? '0';
        $rateFraction = LoanMath::div($interestRate, '100', 6);
        $totalInterest = LoanMath::mul($principal, $rateFraction);
        $totalRepayment = LoanMath::add($principal, $totalInterest);

        $principalPerPeriod = LoanMath::div($principal, (string) $tenor, 2);
        $amountPerPeriod = LoanMath::div($totalRepayment, (string) $tenor, 2);

        $rows = [];
        $principalAccumulated = '0.00';
        $amountAccumulated = '0.00';

        for ($i = 1; $i <= $tenor; $i++) {
            if ($i < $tenor) {
                $principalPortion = $principalPerPeriod;
                $amount = $amountPerPeriod;
            } else {
                $principalPortion = LoanMath::sub($principal, $principalAccumulated);
                $amount = LoanMath::sub($totalRepayment, $amountAccumulated);
            }

            $rows[] = [
                'installment_number' => $i,
                'principal_portion' => $principalPortion,
                'interest_portion' => LoanMath::sub($amount, $principalPortion),
                'amount' => $amount,
            ];

            $principalAccumulated = LoanMath::add($principalAccumulated, $principalPortion);
            $amountAccumulated = LoanMath::add($amountAccumulated, $amount);
        }

        return [
            'rows' => $rows,
            'installment_amount' => $amountPerPeriod,
            'total_repayment' => $totalRepayment,
        ];
    }

    private function generateInstallments(Loan $loan): void
    {
        $plan = $this->calculateInstallmentPlan(
            (string) $loan->principal,
            $this->nullableToString($loan->interest_rate),
            $loan->tenor,
        );

        $year = (int) $loan->first_deduction_period_year;
        $month = (int) $loan->first_deduction_period_month;

        foreach ($plan['rows'] as $row) {
            LoanInstallment::create([
                'loan_id' => $loan->id,
                'installment_number' => $row['installment_number'],
                'payroll_period_year' => $year,
                'payroll_period_month' => $month,
                'principal_portion' => $row['principal_portion'],
                'interest_portion' => $row['interest_portion'],
                'original_amount' => $row['amount'],
                'amount' => $row['amount'],
                'status' => LoanInstallmentStatus::Scheduled->value,
            ]);

            $month++;
            if ($month > 12) {
                $month = 1;
                $year++;
            }
        }
    }

    private function nullableToString(string|int|float|null $value): ?string
    {
        return $value === null ? null : (string) $value;
    }
}