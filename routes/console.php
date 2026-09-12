<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * The only queued work in the app is ForgotPasswordMail (QUEUE_CONNECTION
 * defaults to the database driver - see .env.example) - not worth a
 * permanent `queue:work` process on shared hosting for one occasional
 * mailable. `--stop-when-empty` makes this a short-lived burst that exits
 * on its own rather than a long-running worker, so a single one-minute
 * `schedule:run` cron entry is enough to keep the queue drained (a
 * password-reset email goes out within about a minute of being requested).
 */
Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=3')
    ->everyMinute()
    ->withoutOverlapping();
