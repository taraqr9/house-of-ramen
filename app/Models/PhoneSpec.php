<?php

namespace App\Models;

use App\Traits\HasUserStamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class PhoneSpec extends Model
{
    use HasFactory, HasUserStamps, LogsActivity;

    protected $fillable = [
        'phone_id',
        'processor',
        'chipset_manufacturer',
        'cpu',
        'gpu',
        'display_size',
        'display_resolution',
        'display_panel_type',
        'display_refresh_rate',
        'display_protection',
        'display_brightness_nits',
        'main_camera',
        'ultrawide_camera',
        'telephoto_camera',
        'macro_camera',
        'front_camera',
        'camera_has_ois',
        'video_recording',
        'battery_capacity_mah',
        'charging_speed_w',
        'wireless_charging_w',
        'reverse_charging',
        'network_4g',
        'network_5g',
        'wifi',
        'bluetooth_version',
        'nfc',
        'usb_type',
        'sim_config',
        'height_mm',
        'width_mm',
        'thickness_mm',
        'weight_g',
        'build_materials',
        'ip_rating',
        'os',
        'current_os',
        'os_update_years',
        'security_update_years',
        'estimated_eol_date',
        'source_id',
        'collected_at',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'display_size' => 'decimal:1',
            'display_refresh_rate' => 'integer',
            'display_brightness_nits' => 'integer',
            'camera_has_ois' => 'boolean',
            'battery_capacity_mah' => 'integer',
            'charging_speed_w' => 'integer',
            'wireless_charging_w' => 'integer',
            'reverse_charging' => 'boolean',
            'network_4g' => 'boolean',
            'network_5g' => 'boolean',
            'nfc' => 'boolean',
            'height_mm' => 'decimal:2',
            'width_mm' => 'decimal:2',
            'thickness_mm' => 'decimal:2',
            'weight_g' => 'integer',
            'os_update_years' => 'integer',
            'security_update_years' => 'integer',
            'estimated_eol_date' => 'date',
            'collected_at' => 'datetime',
        ];
    }

    public function phone(): BelongsTo
    {
        return $this->belongsTo(Phone::class);
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(PhoneSource::class, 'source_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName(strtolower(class_basename($this)))
            ->logAll()
            ->logOnlyDirty()
            ->setDescriptionForEvent(fn (string $eventName) => class_basename($this)." {$eventName}<br>".
                '<strong>Table:</strong> '.$this->getTable());
    }
}
