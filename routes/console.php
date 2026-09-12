<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * Nightly phone data update, 12:01 AM Bangladesh time. Runs every
 * enabled source (see config/phone_sources.php) through the same
 * chunked, resumable pipeline as a manual import. Shared hosting only
 * needs a single cron entry running `php artisan schedule:run` every
 * minute - Laravel's scheduler dispatches this at the right time from
 * there, no supervisor or long-running worker required.
 */
Schedule::command('phones:import --type=nightly')
    ->dailyAt('00:01')
    ->timezone('Asia/Dhaka')
    ->withoutOverlapping();

/*
 * Nightly review-queue triage, 00:20 (after the import above, before the
 * price cleanup/image collection below) - auto-resolves PhoneDataReview
 * rows that are stale or already well-evidenced (see
 * App\Console\Commands\ResolvePendingReviewsCommand's own docblock for
 * exactly which evidence-based rules it applies), so the queue doesn't
 * silently regrow to hundreds of rows between manual audits.
 */
Schedule::command('phones:resolve-reviews')
    ->dailyAt('00:20')
    ->timezone('Asia/Dhaka')
    ->withoutOverlapping();

/*
 * Nightly stale-price cleanup, 12:45 AM Bangladesh time (after the phone
 * import above finishes, so a phone that got re-verified tonight is
 * never flagged in the same run). Only deactivates phone_prices rows no
 * source has re-verified in a long time (see
 * config('phone_pricing.expire_after_days')) - PriceAggregator already
 * excludes merely-stale-but-still-active rows from the current market
 * price on every recalculation, so this is bookkeeping (keeping
 * is_active meaningful for the admin screens), not what makes prices
 * "fresh" in the first place.
 */
Schedule::command('phones:expire-stale-prices')
    ->dailyAt('00:45')
    ->timezone('Asia/Dhaka')
    ->withoutOverlapping();

/*
 * Nightly image collection, 1:00 AM Bangladesh time (after the phone
 * import above finishes, so newly-imported phones are already in the
 * DB and eligible). Small, rate-limited batch - Wikimedia Commons and
 * Openverse are free public APIs with no bulk/burst allowance, so this
 * deliberately processes a modest slice per night rather than trying
 * the whole catalogue at once. Every candidate still goes through the
 * same confidence-scored matching as a manual run (see
 * PhoneImageCollector) - nothing here bypasses that; it only decides
 * how many phones get attempted per night.
 */
Schedule::command('phones:collect-images --limit=40')
    ->dailyAt('01:00')
    ->timezone('Asia/Dhaka')
    ->withoutOverlapping();

/*
 * The only queued work in the app is ForgotPasswordMail (QUEUE_CONNECTION
 * defaults to the database driver - see .env.example) - not worth a
 * permanent `queue:work` process on shared hosting for one occasional
 * mailable. `--stop-when-empty` makes this a short-lived burst that exits
 * on its own rather than a long-running worker, so the same one-minute
 * `schedule:run` cron entry that drives the import above is enough to
 * keep the queue drained (a password-reset email goes out within about a
 * minute of being requested).
 */
Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=3')
    ->everyMinute()
    ->withoutOverlapping();
