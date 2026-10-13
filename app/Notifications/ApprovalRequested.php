<?php

namespace App\Notifications;

use App\Models\ApprovalRequest;
use App\Models\NotificationPreference;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ApprovalRequested extends Notification
{
    use Queueable;
    use RespectsPreferences;

    public function __construct(public readonly ApprovalRequest $request)
    {
    }

    protected function preferenceType(): string
    {
        return NotificationPreference::TYPE_APPROVAL_REQUEST;
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'approval_request',
            'title' => 'Approval needed: '.$this->request->actionLabel(),
            'message' => sprintf(
                '%s requested approval for %s.',
                $this->request->requester?->name ?? 'A colleague',
                $this->request->actionLabel(),
            ),
            'url' => route('approvals.show', $this->request),
            'approval_request_id' => $this->request->id,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Approval needed: '.$this->request->actionLabel())
            ->greeting('Hello '.$notifiable->name)
            ->line(sprintf(
                '%s has requested approval for %s.',
                $this->request->requester?->name ?? 'A colleague',
                $this->request->actionLabel(),
            ))
            ->action('Review the request', route('approvals.show', $this->request))
            ->line('No financial record changes until you make a decision.');
    }
}
