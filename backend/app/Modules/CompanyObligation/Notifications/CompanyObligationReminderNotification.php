<?php

namespace App\Modules\CompanyObligation\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Reminder H-30/H-14/H-7/H-1/H-0 untuk CompanyObligation (Sewa Ruko, MOU
 * Legal, Jatuh Tempo Piutang -- lihat CompanyObligation::TYPES).
 *
 * Channel: database (in-app, ditampilkan di dalam module ini saja --
 * TIDAK ada global Inbox/Bell) + mail (queued, ShouldQueue).
 *
 * $data disimpan apa adanya di kolom notifications.data, dipakai juga
 * sebagai kunci dedup (company_obligation_id + milestone + recipient)
 * oleh CompanyObligationReminderService -- pola persis
 * ContractProbationReminderNotification, bukan bikin tabel dedup baru.
 */
class CompanyObligationReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param array{
     *   company_obligation_id: int,
     *   title: string,
     *   type: string,
     *   type_label: string,
     *   due_date: string,
     *   remaining_days: int,
     *   milestone: int,
     *   recipient_type: 'user'|'pic'|'role',
     *   recipient_role: string|null,
     * } $data
     */
    public function __construct(public array $data)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toArray(object $notifiable): array
    {
        return $this->data;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $days = $this->data['remaining_days'];
        $when = $days === 0 ? 'HARI INI' : "dalam {$days} hari";

        return (new MailMessage())
            ->subject("[{$this->data['type_label']}] {$this->data['title']} jatuh tempo {$when}")
            ->line("Kewajiban perusahaan \"{$this->data['title']}\" ({$this->data['type_label']}) jatuh tempo pada {$this->data['due_date']} ({$when}).")
            ->action('Lihat Company Obligations', url('/company-obligations'))
            ->line('Notifikasi ini otomatis dari sistem HRIS.');
    }
}