<?php
 
namespace App\Modules\Training\Notifications;
 
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TrainingReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param array{
     *   training_session_id: int,
     *   training_program_id: int,
     *   program_title: string,
     *   session_name: string,
     *   start_at: string,
     *   remaining_days: int,
     *   milestone: int,
     *   recipient_type: 'user'|'pic'|'role'|'participant',
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
            ->subject("[Training] {$this->data['program_title']} ({$this->data['session_name']}) mulai {$when}")
            ->line("Training \"{$this->data['program_title']}\" -- sesi \"{$this->data['session_name']}\" akan mulai pada {$this->data['start_at']} ({$when}).")
            ->action('Lihat Training', url('/trainings'))
            ->line('Notifikasi ini otomatis dari sistem HRIS.');
    }
}