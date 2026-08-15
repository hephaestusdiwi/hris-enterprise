<?php

namespace Database\Factories;

use App\Modules\Payroll\Enums\PayrollRunStatus;
use App\Modules\Payroll\Models\PayrollRun;
use Illuminate\Database\Eloquent\Factories\Factory;

class PayrollRunFactory extends Factory
{
    protected $model = PayrollRun::class;

    public function definition(): array
    {
        return [
            'company_id' => CompanyFactory::new(),
            'period_year' => now()->year,
            'period_month' => now()->month,
            'status' => PayrollRunStatus::Draft->value,
            'current_revision' => 0,
        ];
    }
}