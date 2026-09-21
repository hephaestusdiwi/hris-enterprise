<?php

namespace App\Modules\CompanyObligation\Services;

use App\Models\User;
use App\Modules\CompanyObligation\Enums\CompanyObligationRecipientType;
use App\Modules\CompanyObligation\Enums\CompanyObligationStatus;
use App\Modules\CompanyObligation\Models\CompanyObligation;
use App\Modules\CompanyObligation\Notifications\CompanyObligationReminderNotification;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Orkestrasi kirim reminder harian: milestone check (dedup utama, pola
 * ContractProbationReminderService) + tentuin recipient dari
 * CompanyObligationRecipient (User/Pic/Role) + dedup final lewat tabel
 * `notifications` bawaan Laravel (bukan tabel dedup baru).
 *
 * Dipisah dari Controller/Model supaya keduanya tetap murni "data",
 * tidak ikut campur soal notifikasi/recipient.
 */
class CompanyObligationReminderService
{
    public function sendDueReminders(): int
    {
        $milestones = config('company_obligation.reminder_milestones', [30, 14, 7, 1, 0]);
        $maxThreshold = max($milestones);

        // Ambil obligation aktif yang due_date-nya masih di rentang
        // 0..$maxThreshold hari ke depan, baru difilter ketat ke
        // remaining_days yang PERSIS match salah satu milestone di bawah
        // -- itu mekanisme dedup utamanya (bukan range, harus pas).
        $obligations = CompanyObligation::query()
            ->where('status', CompanyObligationStatus::Active)
            ->whereDate('due_date', '>=', now()->toDateString())
            ->whereDate('due_date', '<=', now()->addDays($maxThreshold)->toDateString())
            ->with(['recipients', 'pic.user', 'company'])
            ->get();

        $sentCount = 0;

        foreach ($obligations as $obligation) {
            $remainingDays = (int) now()->startOfDay()->diffInDays($obligation->due_date->copy()->startOfDay());

            if (! in_array($remainingDays, $milestones, true)) {
                continue;
            }

            foreach ($this->recipientsFor($obligation) as [$recipient, $recipientType, $recipientRole]) {
                if ($this->alreadySent($recipient, $obligation, $remainingDays, $recipientType, $recipientRole)) {
                    continue;
                }

                $recipient->notify(new CompanyObligationReminderNotification([
                    'company_obligation_id' => $obligation->id,
                    'title' => $obligation->title,
                    'type' => $obligation->type,
                    'type_label' => CompanyObligation::TYPES[$obligation->type] ?? $obligation->type,
                    'due_date' => $obligation->due_date->toDateString(),
                    'remaining_days' => $remainingDays,
                    'milestone' => $remainingDays,
                    'recipient_type' => $recipientType,
                    'recipient_role' => $recipientRole,
                ]));

                $sentCount++;
            }
        }

        return $sentCount;
    }

    /**
     * Kumpulkan recipient unik (User) dari 3 sumber:
     * - type=Pic: PIC obligation ybs (kalau punya akun User).
     * - type=User: user_id spesifik di baris recipient.
     * - type=Role: SEMUA user dengan role itu, di-scope ke company yang
     *   sama dengan obligation (supaya HR/admin company lain tidak ikut
     *   di-spam reminder obligation company lain).
     *
     * @return array<int, array{0: User, 1: string, 2: string|null}>
     */
    private function recipientsFor(CompanyObligation $obligation): array
    {
        $recipients = [];
        $seenUserIds = [];

        $addRecipient = function (?User $user, string $type, ?string $role = null) use (&$recipients, &$seenUserIds) {
            if (! $user || isset($seenUserIds[$user->id.':'.$type.':'.$role])) {
                return;
            }

            $seenUserIds[$user->id.':'.$type.':'.$role] = true;
            $recipients[] = [$user, $type, $role];
        };

        foreach ($obligation->recipients as $recipientRow) {
            match ($recipientRow->recipient_type) {
                CompanyObligationRecipientType::Pic => $addRecipient($obligation->pic?->user, 'pic'),
                CompanyObligationRecipientType::User => $addRecipient($recipientRow->user, 'user'),
                CompanyObligationRecipientType::Role => $this->usersForRole($recipientRow->role, $obligation->company_id)
                    ->each(fn (User $user) => $addRecipient($user, 'role', $recipientRow->role)),
            };
        }

        return $recipients;
    }

    private function usersForRole(string $role, int $companyId)
    {
        return User::role($role)
            ->whereHas('employee', fn ($q) => $q->where('company_id', $companyId))
            ->get();
    }

    private function alreadySent(
        User $recipient,
        CompanyObligation $obligation,
        int $remainingDays,
        string $recipientType,
        ?string $recipientRole,
    ): bool {
        return DatabaseNotification::query()
            ->where('notifiable_type', User::class)
            ->where('notifiable_id', $recipient->id)
            ->where('type', CompanyObligationReminderNotification::class)
            ->where('data->company_obligation_id', $obligation->id)
            ->where('data->milestone', $remainingDays)
            ->where('data->recipient_type', $recipientType)
            // recipient_role NULL buat type pic/user -- where(..., null) di
            // Postgres jadi "= NULL" yang selalu falsy, jadi harus dipisah
            // eksplisit pakai whereNull, BUKAN disamakan seperti kolom lain.
            ->when(
                $recipientRole === null,
                fn ($q) => $q->whereNull('data->recipient_role'),
                fn ($q) => $q->where('data->recipient_role', $recipientRole),
            )
            ->exists();
    }
}