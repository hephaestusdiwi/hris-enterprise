<?php

namespace App\Modules\Bpjs\Models;

use App\Modules\Bpjs\Enums\BpjsCostBearer;
use App\Modules\Company\Models\Company;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyBpjsSetting extends Model
{
    protected $fillable = [
        'company_id',
        'default_health_cost_bearer',
        'default_jht_cost_bearer',
    ];

    protected function casts(): array
    {
        return [
            'default_health_cost_bearer' => BpjsCostBearer::class,
            'default_jht_cost_bearer' => BpjsCostBearer::class,
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}