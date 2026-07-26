<?php

namespace App\Console\Commands;

use App\Modules\LeaveBalance\Services\LeaveBalanceGenerationService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SyncLeaveBalances extends Command
{
    protected $signature = 'leave-balance:sync';

    protected $description = 'Generate Leave Balance untuk seluruh employee yang eligible pada periode berjalan (idempotent)';

    public function handle(LeaveBalanceGenerationService $service): int
    {
        $count = $service->generateForAllEmployees(Carbon::now());

        $this->info("Berhasil generate {$count} leave balance baru.");

        return self::SUCCESS;
    }
}