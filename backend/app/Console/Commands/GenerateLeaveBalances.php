<?php

namespace App\Console\Commands;

use App\Modules\Employee\Models\Employee;
use App\Modules\LeaveBalance\Services\LeaveBalanceGenerationService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GenerateLeaveBalances extends Command
{
    protected $signature = 'leave-balance:generate
        {--employee= : Generate untuk 1 employee_id spesifik saja}
        {--date= : Reference date (default: hari ini), format YYYY-MM-DD}';

    protected $description = 'Generate leave balance untuk employee yang belum punya balance di periode berjalan';

    public function handle(LeaveBalanceGenerationService $service): int
    {
        $referenceDate = $this->option('date') ? Carbon::parse($this->option('date')) : now();

        if ($employeeId = $this->option('employee')) {
            $employee = Employee::find($employeeId);

            if (! $employee) {
                $this->error("Employee id={$employeeId} tidak ditemukan.");

                return self::FAILURE;
            }

            $created = $service->generateForEmployee($employee, $referenceDate);
            $this->info("Employee {$employee->employee_number}: ".count($created)." balance baru dibuat.");

            return self::SUCCESS;
        }

        $this->info('Generating leave balance untuk semua employee aktif, periode '.$referenceDate->year.'...');
        $count = $service->generateForAllEmployees($referenceDate);
        $this->info("Selesai. Total {$count} balance baru dibuat.");

        return self::SUCCESS;
    }
}
