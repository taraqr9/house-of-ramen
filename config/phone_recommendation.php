<?php

/*
|--------------------------------------------------------------------------
| Phone Recommendation Engine Strategy
|--------------------------------------------------------------------------
|
| Every scoring rule the recommendation engine uses lives here so tuning
| never requires touching the scoring code itself. Scores are heuristic
| (there is no benchmark database) but are deliberately grounded in the
| spec fields the data foundation actually collects, and are documented
| below so the "why" behind a number is always traceable.
|
*/

return [

    // How many ranked results the engine returns by default.
    'result_count' => 3,

    // Importance sliders use a 1 (not important) .. 5 (very high) scale.
    // A dimension the user never set falls back to this - the midpoint,
    // so an unanswered slider is neutral rather than silently zero.
    'default_importance' => 3,

    // Importance is converted to a composite weight via importance^exponent
    // rather than used literally. With 9 scoreable dimensions, anything
    // below exponent 5 still lets a phone that's simply strong across the
    // other 8 dimensions beat the pool's best pick on the ONE dimension a
    // user rated "very important" (verified against real catalogue data:
    // a phone with clearly the best software-support score still lost to
    // an all-round stronger phone at exponent 3). Exponent 5 makes "very
    // important" (5) contribute ~3125x a "not important" (1) dimension,
    // which is what it takes for a user's top priority to actually decide
    // the winner rather than just nudge the margin.
    'importance_weight_exponent' => 5,

    // The 9 scoreable dimensions, in the order they should be presented.
    // Each must have a matching `importance_{dimension}` request field and
    // a matching method on PhoneScorer.
    'dimensions' => [
        'performance', 'gaming', 'camera', 'battery',
        'display', 'software', 'build', 'charging', 'value',
    ],

    /*
    |----------------------------------------------------------------
    | Performance (chipset tier heuristic)
    |----------------------------------------------------------------
    | Matched case-insensitively against phone_specs.processor, most
    | specific pattern first. Falls back to a category baseline (and a
    | small RAM nudge) when nothing matches - e.g. a chipset the map
    | hasn't been taught about yet.
    */
    'chipset_tiers' => [
        'snapdragon 8 gen 3' => 96, 'snapdragon 8 gen 2' => 92, 'snapdragon 8+ gen 1' => 90,
        'snapdragon 8 gen 1' => 86, 'snapdragon 7+ gen 3' => 80, 'snapdragon 7 gen 3' => 74,
        'snapdragon 7s gen 2' => 68, 'snapdragon 7+ gen 2' => 78, 'snapdragon 7 gen 1' => 70,
        'snapdragon 6 gen 1' => 58, 'snapdragon 695' => 50, 'snapdragon 685' => 45,
        'snapdragon 680' => 42, 'snapdragon 662' => 38,
        'dimensity 9300' => 95, 'dimensity 9200' => 93, 'dimensity 8300' => 82, 'dimensity 8200' => 80,
        'dimensity 8020' => 76, 'dimensity 7200' => 70, 'dimensity 7050' => 66, 'dimensity 6300' => 52,
        'dimensity 6080' => 48,
        'exynos 2400' => 90, 'exynos 1480' => 62, 'exynos 1380' => 55,
        'helio g99' => 48, 'helio g88' => 42, 'helio g85' => 38,
        'unisoc' => 25,
        'apple a17 pro' => 97, 'apple a17' => 96, 'apple a16' => 93, 'apple a15' => 88,
    ],
    'category_performance_baseline' => [
        'flagship' => 78, 'midrange' => 55, 'budget' => 40, 'entry' => 25,
    ],
    'performance_default_baseline' => 45,

    /*
    |----------------------------------------------------------------
    | Gaming - blend of performance, refresh rate, and charging speed
    |----------------------------------------------------------------
    */
    'gaming_weights' => ['performance' => 0.55, 'refresh_rate' => 0.30, 'charging' => 0.15],

    'refresh_rate_scores' => [
        144 => 100, 120 => 90, 90 => 70, 60 => 50,
    ],
    'refresh_rate_default' => 55,

    /*
    |----------------------------------------------------------------
    | Camera - megapixel tier + lens variety + OIS
    |----------------------------------------------------------------
    | The 108 tier used to be the top of the scale at 85, so any
    | 200MP flagship sensor matched the same "108+" tier as an
    | ordinary midrange phone, and once OIS/ultrawide/macro/front
    | bonuses stacked on top, both clamped to the same 100 - a
    | ~27k midrange phone and a ~190k flagship scored an identical
    | "excellent" camera (verified against the real catalogue: 26
    | phones spanning 22,999-189,999 all scored 98-100, with no
    | headroom left for a genuine periscope telephoto lens to
    | separate a true flagship camera system from a plain 108MP
    | sensor). Added a distinct 200MP tier and lowered the base/
    | bonus values so a common OIS+ultrawide midrange stack lands
    | well under 100, leaving room for telephoto - a real optical
    | zoom lens most midrange phones simply don't have - to be the
    | dimension that actually separates a flagship-camera phone
    | from a merely good one.
    */
    'camera_mp_tiers' => [
        200 => 80, 108 => 68, 64 => 58, 50 => 52, 12 => 38,
    ],
    'camera_mp_default' => 28,
    'camera_ois_bonus' => 8,
    'camera_ultrawide_bonus' => 5,
    'camera_telephoto_bonus' => 10,
    'camera_macro_bonus' => 2,
    'camera_front_bonus' => 3,
    'camera_front_bonus_threshold_mp' => 16,

    /*
    |----------------------------------------------------------------
    | Battery - capacity curve + charging-speed offset
    |----------------------------------------------------------------
    | Interpolated (not tiered): the real catalogue clusters heavily
    | around 5000mAh (30 of 44 seeded phones), and a handful of coarse
    | tiers gave every one of them an identical score - a battery-focused
    | user's priority then had nothing left to differentiate on. Linear
    | interpolation between these anchor points means every 100mAh
    | difference is reflected, not just crossing a tier boundary.
    */
    'battery_capacity_curve' => [
        2000 => 25, 3000 => 40, 3500 => 52, 4000 => 62, 4500 => 72,
        5000 => 80, 5500 => 88, 6000 => 93, 6500 => 97, 7000 => 100,
    ],
    'battery_capacity_default' => 45,
    'battery_charging_offset_max' => 5,
    'battery_charging_offset_divisor' => 100,

    /*
    |----------------------------------------------------------------
    | Display - refresh rate + panel type + resolution
    |----------------------------------------------------------------
    */
    'display_weights' => ['refresh_rate' => 0.4, 'panel' => 0.5, 'resolution' => 0.1],
    'panel_type_scores' => [
        'dynamic amoled' => 98, 'super amoled' => 95, 'amoled' => 90, 'oled' => 88,
        'ips lcd' => 60, 'lcd' => 55,
    ],
    'panel_type_default' => 62,
    'resolution_bonus' => [
        '1440' => 100, '1220' => 85, '1200' => 85, '1080' => 75,
    ],
    'resolution_default' => 55,

    /*
    |----------------------------------------------------------------
    | Software - remaining promised OS + security update years
    |----------------------------------------------------------------
    */
    'software_base' => 50,
    'software_os_year_weight' => 10,
    'software_security_year_weight' => 5,
    'software_no_data_score' => 50,
    'software_expired_floor' => 20,

    /*
    |----------------------------------------------------------------
    | Build quality - materials + IP rating
    |----------------------------------------------------------------
    */
    'build_glass_metal_score' => 90,
    'build_glass_only_score' => 75,
    'build_metal_frame_score' => 65,
    'build_plastic_score' => 45,
    'build_default_score' => 55,
    'ip_rating_bonus' => [
        'ip68' => 10, 'ip67' => 7, 'ip65' => 4, 'ip54' => 4,
    ],

    /*
    |----------------------------------------------------------------
    | Charging speed
    |----------------------------------------------------------------
    */
    'charging_speed_tiers' => [
        100 => 95, 65 => 88, 45 => 78, 33 => 68, 25 => 58, 18 => 48,
    ],
    'charging_speed_default' => 35,
    'wireless_charging_bonus' => 5,

    /*
    |----------------------------------------------------------------
    | Value for money - computed relative to the eligible candidate pool,
    | not in isolation (a phone can only be "good value" compared to its
    | alternatives). Raw quality-per-taka is min-max rescaled into this
    | range so the worst option in a curated pool still reads as
    | reasonable rather than a false "0".
    |----------------------------------------------------------------
    */
    'value_score_min' => 40,
    'value_score_max' => 100,
    'value_score_flat_when_equal' => 70,

    // How much of the pool's price range gets subtracted from quality to
    // form the raw value score, as a fraction of the 0-100 quality scale.
    // At 0.5, the priciest phone in an eligible pool takes up to a 50
    // point deduction - comparable in magnitude to quality itself, so
    // price matters but a merely-cheap, low-quality phone can't win value
    // purely by being cheap (see ValueScorer for why this is additive
    // rather than a quality/price ratio).
    'value_price_penalty_weight' => 0.5,

    /*
    |----------------------------------------------------------------
    | Lifecycle - a gentle multiplier on the overall match score based
    | on age and remaining software support. Deliberately mild: a
    | genuinely better older phone must still be able to outrank a
    | newer, worse-value one.
    |----------------------------------------------------------------
    */
    'lifecycle_age_factors' => [
        // years_since_release => factor
        1 => 1.02, 2 => 1.00, 3 => 0.97, 4 => 0.93,
    ],
    'lifecycle_age_factor_beyond' => 0.90,
    'lifecycle_support_ended_penalty' => 0.05,

    /*
    |----------------------------------------------------------------
    | Confidence - a small nudge, never a dominant factor. Range is the
    | multiplier applied at 0 and 100 confidence respectively.
    |----------------------------------------------------------------
    */
    'confidence_multiplier_min' => 0.92,
    'confidence_multiplier_max' => 1.00,
    'confidence_default' => 60,

    // Flat point bonus applied to the composite score (before lifecycle/
    // confidence multipliers) when a phone's brand is in the user's
    // preferred list. A soft nudge, not a filter - excluded brands are
    // the only brand-based hard requirement.
    'preferred_brand_bonus' => 4,

];
