<?php

namespace App\Notifications;

use App\Models\BackupRun;
use App\Models\NotificationPreference;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BackupFailed extends Notification
{
    use Queueable;
    use RespectsPreferences;

    public function __construct(public readonly BackupRun $run)
    {
    }

    protected function preferenceType(): string
    {
        return NotificationPreference::TYPE_BACKUP_FAILED;
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'backup_failed',
            'title' => 'Backup failed',
            'message' => 'The backup started at '.$this->run->started_at->format('d M Y H:i').' did not complete.',
            'url' => route('admin.backups.index'),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Backup failed')
            ->greeting('Hello '.$notifiable->name)
            ->line('The scheduled database backup did not complete.')
            ->line('Error: '.$this->run->error)
            ->action('Open the backup log', route('admin.backups.index'));
    }
}
