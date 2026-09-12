<?php

namespace App\Services\Presentation;

use App\Models\Phone;

/**
 * Plain factual highlights for a phone viewed on its own (the public
 * detail page), derived purely from spec thresholds - not the
 * personalised recommendation engine (App\Services\Recommendation),
 * which only ever runs against a specific buyer's stated priorities.
 * Nothing here is comparative or scored; every line traces back to one
 * real spec field, so nothing here is invented.
 */
class PhoneHighlights
{
    /**
     * @return array{pros: list<string>, considerations: list<string>}
     */
    public static function for(Phone $phone): array
    {
        $spec = $phone->spec;

        if (! $spec) {
            return ['pros' => [], 'considerations' => []];
        }

        $pros = [];
        $considerations = [];

        if ($spec->battery_capacity_mah !== null) {
            if ($spec->battery_capacity_mah >= 5000) {
                $pros[] = 'Large '.number_format($spec->battery_capacity_mah).'mAh battery for long daily use.';
            } elseif ($spec->battery_capacity_mah < 4000) {
                $considerations[] = 'Smaller '.number_format($spec->battery_capacity_mah).'mAh battery than most phones today.';
            }
        }

        if ($spec->charging_speed_w !== null) {
            if ($spec->charging_speed_w >= 45) {
                $pros[] = 'Fast '.$spec->charging_speed_w.'W charging.';
            } elseif ($spec->charging_speed_w < 20) {
                $considerations[] = 'Charges relatively slowly at '.$spec->charging_speed_w.'W.';
            }
        }

        if ($spec->display_refresh_rate !== null) {
            if ($spec->display_refresh_rate >= 90) {
                $pros[] = 'Smooth '.$spec->display_refresh_rate.'Hz display.';
            } else {
                $considerations[] = 'Standard 60Hz display - not the smoothest for scrolling or gaming.';
            }
        }

        if ($spec->camera_has_ois) {
            $pros[] = 'Optical image stabilisation for steadier, sharper photos.';
        }

        if ($spec->network_5g) {
            $pros[] = '5G ready.';
        }

        if ($spec->nfc) {
            $pros[] = 'NFC for tap-to-pay and quick device pairing.';
        } else {
            $considerations[] = 'No NFC.';
        }

        if ($spec->os_update_years !== null) {
            if ($spec->os_update_years >= 3) {
                $pros[] = $spec->os_update_years.' years of promised OS updates.';
            } elseif ($spec->os_update_years <= 1) {
                $considerations[] = 'Limited long-term software update commitment.';
            }
        }

        if ($spec->ip_rating) {
            $pros[] = 'Dust and water resistant ('.$spec->ip_rating.').';
        }

        if ($phone->variants->contains('is_official_bd', true)) {
            $pros[] = 'Available through official Bangladesh channels, with warranty.';
        } else {
            $considerations[] = 'Currently only available through unofficial/grey-market import in Bangladesh.';
        }

        return [
            'pros' => array_slice($pros, 0, 5),
            'considerations' => array_slice($considerations, 0, 4),
        ];
    }
}
