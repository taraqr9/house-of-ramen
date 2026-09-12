<?php

namespace Database\Factories;

use App\Models\Phone;
use App\Models\PhoneSpec;
use Illuminate\Database\Eloquent\Factories\Factory;

class PhoneSpecFactory extends Factory
{
    protected $model = PhoneSpec::class;

    public function definition(): array
    {
        return [
            'phone_id' => Phone::factory(),
            'processor' => 'Qualcomm Snapdragon 7 Gen 3',
            'chipset_manufacturer' => 'Qualcomm',
            'display_size' => 6.6,
            'display_resolution' => '1080 x 2400',
            'display_panel_type' => 'AMOLED',
            'display_refresh_rate' => 120,
            'main_camera' => '50 MP, f/1.8, OIS',
            'camera_has_ois' => true,
            'front_camera' => '16 MP, f/2.4',
            'battery_capacity_mah' => 5000,
            'charging_speed_w' => 33,
            'network_5g' => true,
            'nfc' => true,
            'weight_g' => 190,
            'build_materials' => 'Glass front, plastic frame and back',
            'os' => 'Android 14',
            'os_update_years' => 3,
            'security_update_years' => 4,
        ];
    }
}
