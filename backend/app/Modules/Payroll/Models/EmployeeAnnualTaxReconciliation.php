<?php

namespace App\Modules\Payroll\Models;

use App\Modules\Employee\Models\Employee;
use App\Modules\Pph21\Enums\TaxMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeAnnualTaxReconciliation extends Model
{
    protected $fillable = [
        'employee_id',
        'payslip_id',
        'payroll_run_id',
        'tax_year',
        'tax_method_applied',
        'total_gross_annual',
        'position_cost_deduction',
        'pension_deduction',
        'ptkp_amount',
        'net_annual_income',
        'pkp',
        'annual_tax_pasal17',
        'total_withheld_prior_months',
        'final_period_adjustment',
        'gross_up_allowance',
        'no_tax_id_surcharge_applied',
    ];

    protected function casts(): array
    {
        return [
            'tax_year' => 'integer',
            'tax_method_applied' => TaxMethod::class,
            'no_tax_id_surcharge_applied' => 'boolean',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function payslip(): BelongsTo
    {
        return $this->belongsTo(Payslip::class);
    }

    public function payrollRun(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class);
    }

    /**
     * Status akhir rekonsiliasi — dipakai label di BPA1 & report.
     * Positif = Kurang Bayar (masih harus dipotong/ditagih), negatif =
     * Lebih Bayar (wajib dikembalikan employer ke karyawan sesuai
     * ketentuan terbaru), nol = Nihil.
     */
    public function status(): string
    {
        $adjustment = (float) $this->final_period_adjustment;

        return match (true) {
            $adjustment > 0 => 'kurang_bayar',
            $adjustment < 0 => 'lebih_bayar',
            default => 'nihil',
        };
    }
}
