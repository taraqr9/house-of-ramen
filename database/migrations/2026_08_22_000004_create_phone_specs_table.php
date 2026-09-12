<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('phone_specs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('phone_id')->unique()->constrained('phones')->cascadeOnDelete();

            // Hardware
            $table->string('processor')->nullable();
            $table->string('chipset_manufacturer')->nullable();
            $table->string('cpu')->nullable();
            $table->string('gpu')->nullable();

            // Display
            $table->decimal('display_size', 3, 1)->nullable();
            $table->string('display_resolution')->nullable();
            $table->string('display_panel_type')->nullable();
            $table->unsignedSmallInteger('display_refresh_rate')->nullable();
            $table->string('display_protection')->nullable();
            $table->unsignedSmallInteger('display_brightness_nits')->nullable();

            // Camera
            $table->string('main_camera')->nullable();
            $table->string('ultrawide_camera')->nullable();
            $table->string('telephoto_camera')->nullable();
            $table->string('macro_camera')->nullable();
            $table->string('front_camera')->nullable();
            $table->boolean('camera_has_ois')->default(false);
            $table->string('video_recording')->nullable();

            // Battery
            $table->unsignedInteger('battery_capacity_mah')->nullable();
            $table->unsignedSmallInteger('charging_speed_w')->nullable();
            $table->unsignedSmallInteger('wireless_charging_w')->nullable();
            $table->boolean('reverse_charging')->default(false);

            // Connectivity
            $table->boolean('network_4g')->default(true);
            $table->boolean('network_5g')->default(false);
            $table->string('wifi')->nullable();
            $table->string('bluetooth_version')->nullable();
            $table->boolean('nfc')->default(false);
            $table->string('usb_type')->nullable();
            $table->string('sim_config')->nullable();

            // Physical
            $table->decimal('height_mm', 6, 2)->nullable();
            $table->decimal('width_mm', 6, 2)->nullable();
            $table->decimal('thickness_mm', 6, 2)->nullable();
            $table->unsignedSmallInteger('weight_g')->nullable();
            $table->string('build_materials')->nullable();
            $table->string('ip_rating')->nullable();

            // Software
            $table->string('os')->nullable();
            $table->string('current_os')->nullable();
            $table->unsignedTinyInteger('os_update_years')->nullable();
            $table->unsignedTinyInteger('security_update_years')->nullable();
            $table->date('estimated_eol_date')->nullable();

            $table->foreignId('source_id')
                ->nullable()
                ->constrained('phone_sources')
                ->nullOnDelete();
            $table->timestamp('collected_at')->nullable();

            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('phone_specs');
    }
};
