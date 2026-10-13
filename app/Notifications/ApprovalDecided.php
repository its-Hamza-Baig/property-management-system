<?php

namespace App\Notifications;

use App\Models\ApprovalRequest;
use App\Models\NotificationPreference;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ApprovalDecided extends Notification
{
    use Queueable;
    use RespectsPreferences;

    public function __construct(public readonly ApprovalRequest $request)
    {
    }

    protected function preferenceType(): string
    {
        return NotificationPreference::TYPE_APPROVAL_DECISION;
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'approval_decision',
            'title' => $this->request->actionLabel().' '.strtolower($this->request->statusLabel()),
            'message' => sprintf(
                '%s %s your request for %s.',
                $this->request->approver?->name ?? 'An approver',
                $this->request->isApproved() ? 'approved' : 'rejected',
                $this->request->actionLabel(),
            ),
            'url' => route('approvals.show', $this->request),
            'approval_request_id' => $this->request->id,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->request->actionLabel().' '.strtolower($this->request->statusLabel()))
            ->greeting('Hello '.$notifiable->name)
            ->line(sprintf(
                'Your request for %s was %s.',
                $this->request->actionLabel(),
                $this->request->isApproved() ? 'approved' : 'rejected',
            ))
            ->when(filled($this->request->decision_reason), fn (MailMessage $mail) => $mail->line('Reason: '.$this->request->decision_reason))
            ->action('Open the request', route('approvals.show', $this->request));
    }
}
