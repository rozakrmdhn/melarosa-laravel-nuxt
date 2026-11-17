<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notifikasi status backup, restore, retensi, dan peringatan kesehatan storage untuk Super Admin.
 */
class BackupStatusNotification extends Notification
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        private readonly string $title,
        private readonly string $message,
        private readonly string $severity = 'info',
        private readonly array $context = [],
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        $configured = (array) config('backup.notifications.channels', ['database']);
        $allowed = array_values(array_intersect($configured, ['database', 'mail']));

        return $allowed !== [] ? $allowed : ['database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('[Melarosa Backup] '.$this->title)
            ->line($this->message);

        if ($this->severity === 'error') {
            $mail->error();
        }

        return $mail->line('Silakan periksa panel Backup & Restore Management untuk detail selengkapnya.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'message' => $this->message,
            'severity' => $this->severity,
            'context' => $this->context,
            'created_at' => now()->toIso8601String(),
        ];
    }
}

