<?php

namespace App\Services\WhatsApp;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Sends a free-text WhatsApp message to one fixed number (the
 * restaurant's own WhatsApp) via CallMeBot - an independent free service
 * (callmebot.com), not affiliated with Meta/WhatsApp. It's a one-way,
 * single-recipient notification API intended for exactly this kind of
 * low-volume personal alert, set up once by messaging their bot number
 * from the restaurant's own WhatsApp (see .env.example for the exact
 * steps). No signup, no cost, no queue - this makes one quick HTTP call
 * inline and never throws, so a slow/unavailable CallMeBot never breaks
 * the reservation request itself.
 */
class CallMeBotNotifier
{
    public static function send(string $message): void
    {
        $phone = config('services.callmebot.phone');
        $apiKey = config('services.callmebot.api_key');

        if (! $phone || ! $apiKey) {
            return;
        }

        try {
            Http::timeout(5)->get('https://api.callmebot.com/whatsapp.php', [
                'phone' => $phone,
                'text' => $message,
                'apikey' => $apiKey,
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
