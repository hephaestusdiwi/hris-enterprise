<?php

namespace App\Modules\Training\Services;

use App\Models\User;
use App\Modules\Training\Enums\TrainingRecipientType;
use App\Modules\Training\Enums\TrainingSessionStatus;
use App\Modules\Training\Models\TrainingSession;
use App\Modules\Training\Notifications\TrainingReminderNotification;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Orkestrasi kirim reminder harian sebelum TrainingSession mulai --
 * pola persis CompanyObligationReminderService, dengan 1 tambahan:
 * peserta terdaftar (TrainingParticipant) OTOMATIS jadi recipient,
 * di luar recipient tambahan (User/Pic/Role) yang dikonfigurasi di
 * level TrainingProgram.
 */
class TrainingReminderService
{
    public function sendDueReminders(): int
    {
        $milestones = config('training.reminder_milestones', [30, 14, 7, 1, 0]);
        $maxThreshold = max($milestones);

        // Sesi yang masih Scheduled & mulai dalam rentang 0..$maxThreshold
        // hari ke depan, baru difilter ketat ke remaining_days yang PERSIS
        // match salah satu milestone -- itu mekanisme dedup utamanya.
        $sessions = TrainingSession::query()
            ->where('status', TrainingSessionStatus::Scheduled)
            ->whereDate('start_at', '>=', now()->toDateString())
            ->whereDate('start_at', '<=', now()->addDays($maxThreshold)->toDateString())
            ->with(['program.recipients', 'program.pic.user', 'participants.employee.user'])
            ->get();

        $sentCount = 0;

        foreach ($sessions as $session) {
            $remainingDays = (int) now()->startOfDay()->diffInDays($session->start_at->copy()->startOfDay());

            if (! in_array($remainingDays, $milestones, true)) {
                continue;
            }

            foreach ($this->recipientsFor($session) as [$recipient, $recipientType, $recipientRole]) {
                if ($this->alreadySent($recipient, $session, $remainingDays, $recipientType, $recipientRole)) {
                    continue;
                }

                $recipient->notify(new TrainingReminderNotification([
                    'training_session_id' => $session->id,
                    'training_program_id' => $session->training_program_id,
                    'program_title' => $session->program->title,
                    'session_name' => $session->name,
                    'start_at' => $session->start_at->toDateTimeString(),
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
     * Kumpulkan recipient unik (User) dari 2 sumber:
     * - Peserta terdaftar di sesi ini (status bukan Cancelled) -- SELALU
     *   otomatis, tidak perlu dikonfigurasi.
     * - Recipient tambahan di level program (Pic/User/Role, Role
     *   di-scope ke company program ybs).
     *
     * @return array<int, array{0: User, 1: string, 2: string|null}>
     */
    private function recipientsFor(TrainingSession $session): array
    {
        $recipients = [];
        $seen = [];

        $addRecipient = function (?User $user, string $type, ?string $role = null) use (&$recipients, &$seen) {
            if (! $user || isset($seen[$user->id.':'.$type.':'.$role])) {
                return;
            }

            $seen[$user->id.':'.$type.':'.$role] = true;
            $recipients[] = [$user, $type, $role];
        };

        foreach ($session->participants as $participant) {
            if (! $participant->isActive()) {
                continue;
            }

            $addRecipient($participant->employee?->user, 'participant');
        }

        foreach ($session->program->recipients as $recipientRow) {
            match ($recipientRow->recipient_type) {
                TrainingRecipientType::Pic => $addRecipient($session->program->pic?->user, 'pic'),
                TrainingRecipientType::User => $addRecipient($recipientRow->user, 'user'),
                TrainingRecipientType::Role => $this->usersForRole($recipientRow->role, $session->program->company_id)
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
        TrainingSession $session,
        int $remainingDays,
        string $recipientType,
        ?string $recipientRole,
    ): bool {
        return DatabaseNotification::query()
            ->where('notifiable_type', User::class)
            ->where('notifiable_id', $recipient->id)
            ->where('type', TrainingReminderNotification::class)
            ->where('data->training_session_id', $session->id)
            ->where('data->milestone', $remainingDays)
            ->where('data->recipient_type', $recipientType)
            // recipient_role NULL buat type participant/pic/user -- lihat
            // catatan yang sama di CompanyObligationReminderService.
            ->when(
                $recipientRole === null,
                fn ($q) => $q->whereNull('data->recipient_role'),
                fn ($q) => $q->where('data->recipient_role', $recipientRole),
            )
            ->exists();
    }
}