<?php

namespace App\Notifications;

use App\Models\NotificationPreference;

/**
 * Honours the per-user notification settings.
 *
 * In-app notices are on by default and email is opt-in, so a new workspace
 * does not start mailing people before anyone has chosen to receive it.
 */
trait RespectsPreferences
{
    abstract protected function preferenceType(): string;

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        $preference = NotificationPreference::where('user_id', $notifiable->getKey())
            ->where('type', $this->preferenceType())
            ->first();

        $channels = [];

        if (! $preference || $preference->in_app) {
            $channels[] = 'database';
        }

        if ($preference?->email && filled($notifiable->email)) {
            $channels[] = 'mail';
        }

        return $channels;
    }
}
