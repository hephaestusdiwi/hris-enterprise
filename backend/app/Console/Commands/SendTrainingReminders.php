<?php

namespace App\Console\Commands;

use App\Modules\Training\Services\TrainingReminderService;
use Illuminate\Console\Command;

class SendTrainingReminders extends Command
{
    protected $signature = 'trainings:send-reminders';

    protected $description = 'Kirim reminder Training (H-30/H-14/H-7/H-1/H-0 sebelum sesi mulai) ke peserta terdaftar & recipient tambahan';

    public function handle(TrainingReminderService $service): int
    {
        $count = $service->sendDueReminders();

        $this->info("Berhasil mengirim {$count} reminder Training.");

        return self::SUCCESS;
    }
}