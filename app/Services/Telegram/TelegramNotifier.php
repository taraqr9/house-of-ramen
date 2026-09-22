<?php

namespace App\Services\Telegram;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Sends a free-text message to one fixed Telegram username (the
 * restaurant's own Telegram) via CallMeBot's Telegram bridge - an
 * independent free service (callmebot.com), not affiliated with Telegram.
 * It's a one-way, single-recipient notification API intended for exactly
 * this kind of low-volume personal alert, set up once by messaging their
 * bot from the restaurant's own Telegram account (see .env.example for the
 * exact steps). No signup, no cost, no queue - this makes one quick HTTP
 * call inline and never throws, so a slow/unavailable CallMeBot never
 * breaks the reservation request itself.
 */
class TelegramNotifier
{
    public static function send(string $message): void
    {
        $username = config('services.telegram.username');

        if (! $username) {
            return;
        }

        try {
            Http::timeout(5)->get('https://api.callmebot.com/text.php', [
                'user' => $username,
                'text' => $message,
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
