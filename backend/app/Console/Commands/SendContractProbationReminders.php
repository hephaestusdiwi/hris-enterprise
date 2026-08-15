<?php

namespace App\Console\Commands;

use App\Modules\Employee\Services\ContractProbationReminderService;
use Illuminate\Console\Command;

class SendContractProbationReminders extends Command
{
    protected $signature = 'contract-probation:send-reminders';

    protected $description = 'Kirim reminder Contract & Probation yang jatuh di milestone hari ini (H-30/H-14/H-7) ke HR/admin dan manager terkait';

    public function handle(ContractProbationReminderService $service): int
    {
        $count = $service->sendDueReminders();

        $this->info("Berhasil mengirim {$count} reminder Contract & Probation.");

        return self::SUCCESS;
    }
}
