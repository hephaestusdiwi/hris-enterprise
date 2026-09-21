<?php

namespace App\Console\Commands;

use App\Modules\CompanyObligation\Services\CompanyObligationReminderService;
use Illuminate\Console\Command;

class SendCompanyObligationReminders extends Command
{
    protected $signature = 'company-obligations:send-reminders';

    protected $description = 'Kirim reminder Company Obligations (Sewa Ruko/MOU Legal/Jatuh Tempo Piutang) yang jatuh di milestone H-30/H-14/H-7/H-1/H-0 hari ini';

    public function handle(CompanyObligationReminderService $service): int
    {
        $count = $service->sendDueReminders();

        $this->info("Berhasil mengirim {$count} reminder Company Obligation.");

        return self::SUCCESS;
    }
}