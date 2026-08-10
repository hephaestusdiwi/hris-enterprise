<?php

namespace App\Console\Commands;

use App\Modules\EmployeeMovement\Services\EmployeeMovementService;
use Illuminate\Console\Command;

class ApplyDueEmployeeMovements extends Command
{
    protected $signature = 'employee-movements:apply-due';

    protected $description = 'Menerapkan Employee Movement (approved) yang effective_date-nya sudah tiba ke current state Employee';

    public function handle(EmployeeMovementService $service): int
    {
        $count = $service->applyDueMovements();

        $this->info("Berhasil menerapkan {$count} employee movement.");

        return self::SUCCESS;
    }
}
