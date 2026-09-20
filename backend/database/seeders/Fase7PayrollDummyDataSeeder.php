<?php

namespace Database\Seeders;

use App\Modules\Company\Models\Company;
use App\Modules\Employee\Models\Employee;
use App\Modules\Payroll\Enums\EmployeeNonRegularInputStatus;
use App\Modules\Payroll\Models\EmployeeNonRegularInput;
use App\Modules\Payroll\Models\NonRegularPayrollComponent;
use App\Modules\Payroll\Models\ThrPolicy;
use Illuminate\Database\Seeder;

/**
 * Dummy data development Fase 7 (THR & Non-Regular Payroll). Seeder ini
 * SENGAJA tidak dipanggil dari DatabaseSeeder::run() (tidak mengubah
 * behavior `php artisan db:seed` default) — jalankan manual saat butuh:
 *
 *   php artisan db:seed --class=Fase7PayrollDummyDataSeeder
 */
class Fase7PayrollDummyDataSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::first() ?? Company::factory()->create(['name' => 'PT Contoh Sejahtera']);

        $policy = ThrPolicy::firstOrCreate(
            ['company_id' => $company->id, 'name' => 'THR Policy Standar'],
            [
                'minimum_service_months' => 1,
                'full_service_months' => 12,
                'include_in_bpjs_base' => false,
                'is_active' => true,
                'effective_date' => now()->startOfYear(),
            ]
        );

        $components = [
            ['code' => 'BONUS-TAHUNAN', 'name' => 'Bonus Tahunan', 'category' => 'bonus', 'is_taxable' => true, 'include_in_bpjs_base' => false],
            ['code' => 'INSENTIF-SALES', 'name' => 'Insentif Sales', 'category' => 'incentive', 'is_taxable' => true, 'include_in_bpjs_base' => false],
            ['code' => 'KOMISI-PENJUALAN', 'name' => 'Komisi Penjualan', 'category' => 'commission', 'is_taxable' => true, 'include_in_bpjs_base' => false],
            ['code' => 'EARNING-SEKALI-BAYAR', 'name' => 'Tunjangan Khusus (Sekali Bayar)', 'category' => 'one_time_earning', 'is_taxable' => true, 'include_in_bpjs_base' => false],
            ['code' => 'POTONGAN-GANTI-RUGI', 'name' => 'Potongan Ganti Rugi Aset', 'category' => 'one_time_deduction', 'is_taxable' => false, 'include_in_bpjs_base' => false],
        ];

        $created = [];

        foreach ($components as $data) {
            $created[$data['code']] = NonRegularPayrollComponent::firstOrCreate(
                ['company_id' => $company->id, 'code' => $data['code']],
                [
                    'name' => $data['name'],
                    'category' => $data['category'],
                    'is_taxable' => $data['is_taxable'],
                    'include_in_bpjs_base' => $data['include_in_bpjs_base'],
                    'is_active' => true,
                ]
            );
        }

        $sampleEmployees = Employee::where('company_id', $company->id)->limit(2)->get();
        $period = ['year' => now()->year, 'month' => now()->month];

        foreach ($sampleEmployees as $index => $employee) {
            EmployeeNonRegularInput::firstOrCreate(
                [
                    'employee_id' => $employee->id,
                    'non_regular_payroll_component_id' => $created['BONUS-TAHUNAN']->id,
                    'payroll_period_year' => $period['year'],
                    'payroll_period_month' => $period['month'],
                ],
                [
                    'amount' => '2000000.00',
                    'is_addition' => true,
                    'note' => 'Contoh dummy data development — Bonus Tahunan',
                    'status' => EmployeeNonRegularInputStatus::Ready->value,
                ]
            );

            if ($index === 0) {
                EmployeeNonRegularInput::firstOrCreate(
                    [
                        'employee_id' => $employee->id,
                        'non_regular_payroll_component_id' => $created['POTONGAN-GANTI-RUGI']->id,
                        'payroll_period_year' => $period['year'],
                        'payroll_period_month' => $period['month'],
                    ],
                    [
                        'amount' => '150000.00',
                        'is_addition' => false,
                        'note' => 'Contoh dummy data development — Potongan Ganti Rugi',
                        'status' => EmployeeNonRegularInputStatus::Draft->value,
                    ]
                );
            }
        }

        $this->command?->info("Fase 7 dummy data selesai dibuat untuk company #{$company->id} ({$company->name}). THR Policy #{$policy->id}, ".count($created).' component, '.$sampleEmployees->count().' employee sample input.');
    }
}
