<?php

/*
|--------------------------------------------------------------------------
| Phone Pricing - Market Price Aggregation
|--------------------------------------------------------------------------
|
| Bangladesh's phone market genuinely has two separate prices for many
| models - an official/authorized-importer price and a cheaper
| unofficial/grey-market price - and within EITHER market, individual
| retailers quote different amounts for the exact same listing. A single
| bad or stale retailer quote must never become "the" price. See
| App\Services\PhoneImport\PriceAggregator, which turns the raw,
| per-retailer observations in phone_prices into one outlier-resistant
| "current market price" per (variant, official|unofficial) pair, stored
| in phone_market_prices.
|
| Every threshold below is a general statistical constant applied the
| same way to every phone - none of it is a hard-coded Taka amount for
| any specific model. It is deliberately scale-invariant: it reasons in
| ratios/deviations relative to each phone's own observations, so it
| behaves the same whether the phone costs 8,000 or 200,000 Taka.
|
*/

return [

    // Median Absolute Deviation (MAD) outlier test. For each observation,
    // a "modified z-score" is computed as 0.6745 * |value - median| / MAD.
    // 3.5 is the threshold recommended by Iglewicz & Hoaglin (1993) for
    // this exact test - a well-established, general-purpose choice, not
    // tuned per phone. Requires >= 3 observations to be meaningful; with
    // fewer, every observation is trusted (there's no reliable way to
    // call one of only two prices "the outlier").
    'outlier_modified_z_threshold' => 3.5,

    // Fallback for the (common) case where MAD is 0 - e.g. 3 retailers
    // agree exactly and a 4th differs. The modified z-score is undefined
    // when MAD=0, so instead flag anything more than this fraction away
    // from the median as an outlier.
    'outlier_fallback_relative_deviation' => 0.20,

    // Ingest-time sanity gate (App\Services\PhoneImport\PriceAggregator::isSane()),
    // separate from outlier detection above. This runs BEFORE a new
    // observation is even allowed to become a retailer's "current" price
    // in phone_prices - it's a circuit breaker against obviously broken
    // data (a missing digit, a decimal-point slip, a unit mix-up), not a
    // judgement about which legitimate retailer price is "right". Generous
    // on purpose: real official-vs-unofficial and retailer-to-retailer
    // gaps in this market are rarely more than 2-3x; anything outside
    // 0.15x-6x the existing market price is almost certainly a data
    // error, not a real quote.
    'sanity_min_ratio' => 0.15,
    'sanity_max_ratio' => 6.0,

    // Absolute floor/ceiling (BDT), checked BEFORE the ratio-to-reference
    // test above and regardless of whether a reference price exists yet.
    // The ratio gate alone does nothing for a phone's very first price
    // observation (no market price on record to compare against), which
    // is exactly when a genuinely malformed extraction is most likely to
    // slip straight into phone_prices unfiltered - an EMI monthly
    // installment ("৳2,999/mo"), a pre-order booking deposit, a discount
    // amount instead of the final price, or a decimal/unit slip. No real
    // smartphone sold in Bangladesh is priced below ~1,000 BDT (even the
    // cheapest feature-adjacent entry phones) or above ~500,000 BDT.
    'absolute_min_bdt' => 1000,
    'absolute_max_bdt' => 500000,

    /*
    |----------------------------------------------------------------
    | Known Bangladesh retailers - starting classification only
    |----------------------------------------------------------------
    | A retailer's phone_stores.type is descriptive metadata for the
    | admin UI - it is NEVER what decides whether a given listing is
    | official or unofficial (that's phone_prices.price_type, set per
    | listing/observation). Several retailers here genuinely sell both
    | (e.g. Apple Gadgets BD lists official stock alongside unofficial
    | imports), so this is only the sensible default applied the first
    | time a new store name is seen during import - always editable
    | afterward from the Data Sources admin screen, never authoritative.
    */
    'known_retailer_types' => [
        'star-tech' => 'authorized',
        'istock-bd' => 'authorized',
        'rio-international' => 'marketplace',
        'sumash-tech' => 'marketplace',
        'dazzle' => 'marketplace',
        'apple-gadgets-bd' => 'marketplace',
    ],

    /*
    |----------------------------------------------------------------
    | Freshness
    |----------------------------------------------------------------
    | A price observation is only as trustworthy as how recently a
    | source actually re-confirmed it (phone_prices.last_verified_at) -
    | an is_active=true row from months ago must not silently keep
    | anchoring "the" current price forever just because nothing has
    | explicitly marked it gone. Two thresholds, not one, because
    | "not fresh enough to trust right now" and "abandoned, stop
    | carrying it at all" are different failure modes:
    |
    | - stale_after_days: how old last_verified_at can be before this
    |   class's recalculate() stops counting the row toward the current
    |   market price. It stays in phone_prices (history is never lost),
    |   it simply isn't "current" anymore. A retailer that resumes being
    |   reachable tomorrow makes it current again on the next fetch -
    |   no explicit re-activation needed.
    | - expire_after_days: how old before App\Console\Commands\
    |   ExpireStalePricesCommand actively flips is_active to false. Set
    |   well beyond stale_after_days on purpose - the "not fresh" gate
    |   above already keeps an old row out of the price shown to users
    |   long before this fires; this is only for admin-screen hygiene
    |   (stop listing a retailer as an active price source once nothing
    |   has been able to re-verify it for a long time).
    */
    'stale_after_days' => 14,
    'expire_after_days' => 45,

];
