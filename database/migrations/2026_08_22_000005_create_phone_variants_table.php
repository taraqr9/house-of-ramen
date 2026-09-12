<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('phone_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('phone_id')->constrained('phones')->cascadeOnDelete();
            $table->string('slug')->unique();

            $table->unsignedSmallInteger('ram_gb')->nullable();
            $table->unsignedInteger('storage_gb')->nullable();
            $table->string('storage_type')->nullable();
            $table->boolean('expandable_storage')->default(false);
            $table->unsignedInteger('expandable_storage_max_gb')->nullable();

            $table->string('color')->nullable();
            $table->string('region')->nullable();
            $table->string('sku')->nullable();

            $table->boolean('is_official_bd')->default(false);
            $table->string('status')->default('available');

            $table->foreignId('source_id')
                ->nullable()
                ->constrained('phone_sources')
                ->nullOnDelete();
            $table->timestamp('collected_at')->nullable();

            $table->boolean('is_active')->default(true);
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['phone_id', 'ram_gb', 'storage_gb', 'color', 'region'], 'phone_variants_unique_combo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('phone_variants');
    }
};
