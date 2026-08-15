<?php

namespace App\Modules\Employee\Services;

use App\Models\User;
use App\Modules\Employee\Contracts\ContractProbationServiceInterface;
use App\Modules\Employee\Models\Employee;
use App\Modules\Employee\Notifications\ContractProbationReminderNotification;
use App\Modules\ContractProbationSetting\Models\ContractProbationSetting;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Orkestrasi kirim reminder: milestone check (dedup utama) + tentuin
 * recipient (HR/admin + direct manager, dari hierarchy existing) + dedup
 * final lewat tabel notifications (bukan tabel dedup baru).
 *
 * Dipisah dari ContractProbationService supaya Service itu tetap murni
 * "deteksi data", tidak ikut campur soal notifikasi/recipient.
 */
class ContractProbationReminderService
{
    public function __construct(private ContractProbationServiceInterface $contractProbationService)
    {
    }

    public function sendDueReminders(): int
    {
        $setting = ContractProbationSetting::current();
        $milestones = config('contract_probation.reminder_milestones', [30, 14, 7]);
        $maxThreshold = max($milestones);

        // Ambil dengan threshold SEBESAR milestone terjauh, baru difilter ketat
        // ke remaining_days yang PERSIS match salah satu milestone di bawah —
        // itu mekanisme dedup utamanya (bukan range, harus pas).
        $items = $this->contractProbationService->upcomingUnscoped($maxThreshold, $maxThreshold);

        $sentCount = 0;

        foreach ($items as $item) {
            if (! in_array($item['remaining_days'], $milestones, true)) {
                continue;
            }

            foreach ($this->recipientsFor($item['employee'], $setting) as [$recipient, $recipientRole]) {
                if ($this->alreadySent($recipient, $item, $recipientRole)) {
                    continue;
                }

                $recipient->notify(new ContractProbationReminderNotification([
                    'employee_id' => $item['employee']->id,
                    'employee_name' => trim("{$item['employee']->first_name} {$item['employee']->last_name}"),
                    'item_type' => $item['type'],
                    'end_date' => $item['end_date']->toDateString(),
                    'remaining_days' => $item['remaining_days'],
                    'milestone' => $item['remaining_days'],
                    'recipient_role' => $recipientRole,
                    'email_enabled' => $setting->email_reminder_enabled,
                ]));

                $sentCount++;
            }
        }

        return $sentCount;
    }

    /**
     * HR/admin (semua, company-wide — sesuai wewenang mereka yang memang
     * full-visibility) + direct manager employee ini (dari hierarchy
     * existing, BUKAN whole chain — biar tidak spam ke semua level), KECUALI
     * manager_reminder_enabled di-nonaktifkan lewat setting.
     *
     * @return array<int, array{0: User, 1: string}>
     */
    private function recipientsFor(Employee $employee, ContractProbationSetting $setting): array
    {
        $recipients = [];

        foreach (User::role(['admin', 'hr'])->get() as $user) {
            $recipients[] = [$user, 'hr_admin'];
        }

        if ($setting->manager_reminder_enabled && $employee->manager && $employee->manager->user) {
            $recipients[] = [$employee->manager->user, 'manager'];
        }

        return $recipients;
    }

    /**
     * @param array{employee: Employee, type: string, remaining_days: int} $item
     */
    private function alreadySent(User $recipient, array $item, string $recipientRole): bool
    {
        return DatabaseNotification::query()
            ->where('notifiable_type', User::class)
            ->where('notifiable_id', $recipient->id)
            ->where('type', ContractProbationReminderNotification::class)
            ->where('data->employee_id', $item['employee']->id)
            ->where('data->item_type', $item['type'])
            ->where('data->milestone', $item['remaining_days'])
            ->where('data->recipient_role', $recipientRole)
            ->exists();
    }
}
