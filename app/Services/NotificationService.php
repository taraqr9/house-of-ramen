<?php

namespace App\Services;

use App\Notifications\SystemNotification;
use Illuminate\Support\Facades\Notification;

class NotificationService
{
    /**
     * Send a notification to a single notifiable model (e.g. a User).
     */
    public static function send(
        $notifiable,
        string $title,
        string $message,
        ?string $url = null,
        string $icon = 'bx-bell',
        string $type = 'primary',
    ): void {
        if (! $notifiable) {
            return;
        }

        $notifiable->notify(new SystemNotification($title, $message, $url, $icon, $type));
    }

    /**
     * Send the same notification to a collection/iterable of notifiable models.
     */
    public static function sendMany(
        iterable $notifiables,
        string $title,
        string $message,
        ?string $url = null,
        string $icon = 'bx-bell',
        string $type = 'primary',
    ): void {
        Notification::send($notifiables, new SystemNotification($title, $message, $url, $icon, $type));
    }
}
