<?php

namespace Tests\Feature\Loan;

use App\Modules\Employee\Models\Employee;
use App\Modules\Loan\Enums\LoanInstallmentStatus;
use App\Modules\Loan\Enums\LoanInterestType;
use App\Modules\Loan\Enums\LoanStatus;
use App\Modules\Loan\Exceptions\LoanValidationException;
use App\Modules\Loan\Models\Loan;
use App\Modules\Loan\Models\LoanInstallment;
use App\Modules\Loan\Services\LoanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoanResignationSettlementTest extends TestCase
{
    use RefreshDatabase;

    private function activeLoanWithTwoPaidInstallments(): Loan
    {
        $employee = Employee::factory()->create(['resign_date' => '2027-03-15']);

        $loan = Loan::create([
            'employee_id' => $employee->id,
            'principal' => '1200000.00',
            'interest_rate' => null,
            'interest_type' => LoanInterestType::None->value,
            'tenor' => 6,
            'installment_amount' => '200000.00',
            'total_repayment' => '1200000.00',
            'first_deduction_period_year' => 2027,
            'first_deduction_period_month' => 1,
            'status' => LoanStatus::Active->value,
            'disbursed_at' => now(),
        ]);

        // 6 installment @200.000, Jan-Jun 2027. Jan & Feb sudah Paid duluan
        // (simulasi 2 bulan payroll sudah jalan sebelum resign).
        for ($i = 1; $i <= 6; $i++) {
            LoanInstallment::create([
                'loan_id' => $loan->id,
                'installment_number' => $i,
                'payroll_period_year' => 2027,
                'payroll_period_month' => $i,
                'principal_portion' => '200000.00',
                'interest_portion' => '0.00',
                'original_amount' => '200000.00',
                'amount' => '200000.00',
                'status' => $i <= 2 ? LoanInstallmentStatus::Paid->value : LoanInstallmentStatus::Scheduled->value,
                'paid_at' => $i <= 2 ? now() : null,
            ]);
        }

        return $loan->fresh();
    }

    public function test_settlement_creates_lump_sum_and_supersedes_future_installments_without_deleting_history(): void
    {
        $loan = $this->activeLoanWithTwoPaidInstallments();

        $service = app(LoanService::class);
        $result = $service->settleForResignation($loan, null);

        // Loan MASIH Active — baru Settled setelah Payroll benar2 memproses lump-sum-nya.
        $this->assertSame(LoanStatus::Active, $result->status);

        // History TIDAK dihapus: tetap 6 installment lama + 1 baris lump-sum baru = 7.
        $this->assertSame(7, LoanInstallment::where('loan_id', $loan->id)->count());

        // Installment #1 & #2 (sudah Paid) tidak tersentuh sama sekali.
        $this->assertSame(LoanInstallmentStatus::Paid, LoanInstallment::where('loan_id', $loan->id)->where('installment_number', 1)->first()->status);
        $this->assertSame(LoanInstallmentStatus::Paid, LoanInstallment::where('loan_id', $loan->id)->where('installment_number', 2)->first()->status);

        // Installment #3-#6 (Mar-Jun, >= final period Mar) di-supersede jadi Skipped, bukan dihapus.
        foreach ([3, 4, 5, 6] as $number) {
            $installment = LoanInstallment::where('loan_id', $loan->id)->where('installment_number', $number)->first();
            $this->assertSame(LoanInstallmentStatus::Skipped, $installment->status);
            $this->assertNotNull($installment->loan_settlement_id);
        }

        // Baris lump-sum baru (#7): outstanding = 1.200.000 - (2 x 200.000) = 800.000.
        $lumpSum = LoanInstallment::where('loan_id', $loan->id)->where('installment_number', 7)->first();
        $this->assertNotNull($lumpSum);
        $this->assertSame('800000.00', $lumpSum->principal_portion);
        $this->assertSame('0.00', $lumpSum->interest_portion);
        $this->assertSame(LoanInstallmentStatus::Scheduled, $lumpSum->status);
        $this->assertSame(2027, $lumpSum->payroll_period_year);
        $this->assertSame(3, $lumpSum->payroll_period_month);

        // LoanSettlement audit trail record.
        $settlement = $loan->settlement()->first();
        $this->assertNotNull($settlement);
        $this->assertSame('800000.00', $settlement->outstanding_principal_settled);
        $this->assertSame(4, $settlement->superseded_installment_count);
    }

    public function test_loan_becomes_settled_after_payroll_processes_the_lump_sum(): void
    {
        $loan = $this->activeLoanWithTwoPaidInstallments();

        $service = app(LoanService::class);
        $service->settleForResignation($loan, null);

        $lumpSum = LoanInstallment::where('loan_id', $loan->id)->where('installment_number', 7)->first();

        // Simulasikan Payroll lock() memproses baris lump-sum ini seperti installment biasa.
        $service->markInstallmentPaid($lumpSum);

        $this->assertSame(LoanStatus::Settled, $loan->fresh()->status);
    }

    public function test_settlement_is_rejected_without_explicit_resign_date(): void
    {
        $employee = Employee::factory()->create(); // resign_date null

        $loan = Loan::create([
            'employee_id' => $employee->id,
            'principal' => '600000.00',
            'interest_type' => LoanInterestType::None->value,
            'tenor' => 3,
            'installment_amount' => '200000.00',
            'total_repayment' => '600000.00',
            'first_deduction_period_year' => 2027,
            'first_deduction_period_month' => 1,
            'status' => LoanStatus::Active->value,
        ]);

        $this->expectException(LoanValidationException::class);

        app(LoanService::class)->settleForResignation($loan, null);
    }
}