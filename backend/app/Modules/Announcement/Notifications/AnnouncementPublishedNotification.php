<?php

namespace App\Modules\Announcement\Notifications;

use App\Modules\Announcement\Models\Announcement;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Mengikuti pola persis ContractProbationReminderNotification (Contract &
 * Probation Phase 3) — bukan notification system kedua, ini instance baru
 * dari infrastruktur Laravel Notification yang sama.
 */
class AnnouncementPublishedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private Announcement $announcement)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'announcement_id' => $this->announcement->id,
            'title' => $this->announcement->title,
            'category' => $this->announcement->category?->name,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject("Pengumuman Baru: {$this->announcement->title}")
            ->line($this->announcement->title)
            ->action('Lihat Pengumuman', url("/announcements/{$this->announcement->id}"))
            ->line('Notifikasi ini otomatis dari sistem HRIS.');
    }
}
