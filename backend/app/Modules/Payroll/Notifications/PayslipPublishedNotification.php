<?php

namespace App\Modules\Payroll\Notifications;

use App\Modules\Payroll\Models\Payslip;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Mengikuti pola persis AnnouncementPublishedNotification /
 * ContractProbationReminderNotification — instance baru dari infrastruktur
 * Laravel Notification yang sama, bukan sistem notifikasi kedua.
 */
class PayslipPublishedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private Payslip $payslip)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'payslip_id' => $this->payslip->id,
            'period_year' => $this->payslip->payrollRun->period_year,
            'period_month' => $this->payslip->payrollRun->period_month,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $period = "{$this->payslip->payrollRun->period_month}/{$this->payslip->payrollRun->period_year}";

        return (new MailMessage())
            ->subject("Payslip Periode {$period} Sudah Tersedia")
            ->line("Payslip Anda untuk periode {$period} sudah dipublish dan bisa dilihat.")
            ->action('Lihat Payslip', url('/my-payslips'))
            ->line('Notifikasi ini otomatis dari sistem HRIS.');
    }
}
