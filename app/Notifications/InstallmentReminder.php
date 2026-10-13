<?php

namespace App\Notifications;

use App\Models\Installment;
use App\Models\NotificationPreference;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InstallmentReminder extends Notification
{
    use Queueable;
    use RespectsPreferences;

    public function __construct(
        public readonly Installment $installment,
        public readonly bool $overdue,
    ) {
    }

    protected function preferenceType(): string
    {
        return $this->overdue
            ? NotificationPreference::TYPE_INSTALLMENT_OVERDUE
            : NotificationPreference::TYPE_INSTALLMENT_DUE;
    }

    private function summary(): string
    {
        return sprintf(
            'Installment %d of %s for %s is %s on %s. %s is still outstanding.',
            $this->installment->installment_no,
            $this->installment->sale->property->reference,
            $this->installment->sale->buyer->name,
            $this->overdue ? 'overdue since' : 'due',
            $this->installment->due_date->format('d M Y'),
            $this->installment->remaining()->format(),
        );
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => $this->overdue ? 'installment_overdue' : 'installment_due',
            'title' => $this->overdue ? 'Installment overdue' : 'Installment due',
            'message' => $this->summary(),
            'url' => route('sales.show', $this->installment->sale_id),
            'installment_id' => $this->installment->id,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->overdue ? 'Buyer installment overdue' : 'Buyer installment due')
            ->greeting('Hello '.$notifiable->name)
            ->line($this->summary())
            ->action('Open the sale', route('sales.show', $this->installment->sale_id));
    }
}
