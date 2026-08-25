<?php

namespace App\Modules\Payroll\Models;

use App\Modules\Company\Models\Company;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyBankSetting extends Model
{
    protected $fillable = [
        'company_id', 'bank_name', 'account_number', 'account_holder_name',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
