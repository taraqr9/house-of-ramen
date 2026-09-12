<?php

/*
|--------------------------------------------------------------------------
| Phone Kinbo Public Site
|--------------------------------------------------------------------------
|
| Presentation-only settings for the public consumer site. The
| recommendation engine only ever supports a budget CEILING (max_budget)
| - there is no minimum-budget filter - so every bracket here is phrased
| as "under X", never as a min-max range, to stay honest about what the
| backend can actually do. Shared by the homepage's live discovery tiles
| and the Find My Phone budget step so both read from one list.
|
*/

return [

    'price_brackets' => [
        ['label' => 'Under ৳15,000', 'max_budget' => 15000],
        ['label' => 'Under ৳20,000', 'max_budget' => 20000],
        ['label' => 'Under ৳30,000', 'max_budget' => 30000],
        ['label' => 'Under ৳40,000', 'max_budget' => 40000],
        ['label' => 'Under ৳50,000', 'max_budget' => 50000],
        ['label' => 'Under ৳60,000', 'max_budget' => 60000],
        ['label' => 'Under ৳80,000', 'max_budget' => 80000],
        ['label' => 'Under ৳1,00,000', 'max_budget' => 100000],
        ['label' => '৳1,00,000+', 'max_budget' => null],
    ],

];
