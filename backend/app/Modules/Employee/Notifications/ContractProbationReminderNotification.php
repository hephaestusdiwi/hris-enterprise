<?php

namespace App\Modules\Employee\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * "Contract employee akan berakhir dalam X hari" / probation setara.
 * Channel: database (in-app) + mail (queued — ShouldQueue, JANGAN sync).
 *
 * $data disimpan apa adanya di kolom notifications.data — dipakai juga
 * sebagai kunci dedup (employee_id + type + milestone) oleh
 * ContractProbationReminderService, bukan bikin tabel dedup terpisah.
 */
class ContractProbationReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param array{
     *   employee_id: int,
     *   employee_name: string,
     *   item_type: 'contract'|'probation',
     *   end_date: string,
     *   remaining_days: int,
     *   milestone: int,
     *   recipient_role: 'hr_admin'|'manager',
     * } $data
     */
    public function __construct(public array $data)
    {
    }

    public function via(object $notifiable): array
    {
        $channels = ['database'];

        // email_reminder_enabled dikontrol dari ContractProbationSetting
        // (Admin/HR-editable) — database channel TETAP selalu ada, itu
        // dasar mekanisme dedup-nya, terlepas dari setting email.
        if ($this->data['email_enabled'] ?? true) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toArray(object $notifiable): array
    {
        return $this->data;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $label = $this->data['item_type'] === 'contract' ? 'Contract' : 'Probation';
        $name = $this->data['employee_name'];
        $days = $this->data['remaining_days'];

        return (new MailMessage())
            ->subject("{$label} {$name} akan berakhir dalam {$days} hari")
            ->line("{$label} milik {$name} akan berakhir pada {$this->data['end_date']} ({$days} hari lagi).")
            ->action('Lihat Contract & Probation', url('/employees/contract-probation'))
            ->line('Notifikasi ini otomatis dari sistem HRIS.');
    }
}
