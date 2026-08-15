<?php

namespace Tests\Feature\Employee;

use App\Models\User;
use App\Modules\Employee\Models\Employee;
use App\Modules\Employee\Notifications\ContractProbationReminderNotification;
use App\Modules\Employee\Services\ContractProbationReminderService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ContractProbationReminderTest extends TestCase
{
    use RefreshDatabase;

    public function test_reminder_sent_at_exact_milestone_to_hr_admin_and_manager(): void
    {
        Notification::fake();
        $this->seed(RolePermissionSeeder::class);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $managerUser = User::factory()->create();
        $manager = Employee::factory()->create(['user_id' => $managerUser->id]);

        $employee = Employee::factory()->create([
            'manager_employee_id' => $manager->id,
            'contract_end_date' => now()->addDays(30)->toDateString(),
        ]);

        app(ContractProbationReminderService::class)->sendDueReminders();

        Notification::assertSentTo($admin, ContractProbationReminderNotification::class);
        Notification::assertSentTo($managerUser, ContractProbationReminderNotification::class);
    }

    public function test_reminder_not_sent_outside_milestone(): void
    {
        Notification::fake();
        $this->seed(RolePermissionSeeder::class);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Employee::factory()->create([
            'contract_end_date' => now()->addDays(20)->toDateString(), // bukan 30/14/7
        ]);

        app(ContractProbationReminderService::class)->sendDueReminders();

        Notification::assertNothingSent();
    }

    public function test_reminder_not_duplicated_on_second_run_same_day(): void
    {
        // SENGAJA TIDAK pakai Notification::fake() di sini — dedup-nya
        // bergantung ke tabel `notifications` yang beneran ditulis. Aman
        // dijalankan nyata karena MAIL_MAILER=log (tidak benar-benar
        // mengirim email keluar) dan ini database testing terpisah.
        $this->seed(RolePermissionSeeder::class);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Employee::factory()->create([
            'contract_end_date' => now()->addDays(14)->toDateString(),
        ]);

        $service = app(ContractProbationReminderService::class);

        $firstRun = $service->sendDueReminders();
        $secondRun = $service->sendDueReminders();

        $this->assertGreaterThan(0, $firstRun);
        $this->assertSame(0, $secondRun, 'Run kedua di hari yang sama tidak boleh kirim ulang (dedup).');
    }
}
