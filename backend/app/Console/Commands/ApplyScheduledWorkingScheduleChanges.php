<?php

namespace App\Console\Commands;

use App\Modules\WorkingSchedule\Services\WorkingScheduleChangeService;
use Illuminate\Console\Command;

class ApplyScheduledWorkingScheduleChanges extends Command
{
    protected $signature = 'working-schedule:apply-scheduled-changes';

    protected $description = 'Menerapkan Working Schedule Scheduled Change yang effective_date-nya sudah tiba';

    public function handle(WorkingScheduleChangeService $service): int
    {
        $count = $service->applyDueChanges();

        $this->info("Berhasil menerapkan {$count} scheduled change.");

        return self::SUCCESS;
    }
}