<?php

namespace App\Modules\Payroll\Models;

use App\Modules\Payroll\Enums\PayslipLineSource;
use App\Modules\Payroll\Enums\PayslipLineType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayslipLine extends Model
{
    protected $fillable = ['payslip_id', 'type', 'source', 'label', 'amount', 'reference_id'];

    protected function casts(): array
    {
        return [
            'type' => PayslipLineType::class,
            'source' => PayslipLineSource::class,
            'amount' => 'decimal:2',
        ];
    }

    public function payslip(): BelongsTo
    {
        return $this->belongsTo(Payslip::class);
    }
}