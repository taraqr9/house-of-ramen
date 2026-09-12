<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('phone_availability', function (Blueprint $table) {
            $table->id();
            $table->foreignId('phone_variant_id')->constrained('phone_variants')->cascadeOnDelete();
            $table->foreignId('store_id')
                ->nullable()
                ->constrained('phone_stores')
                ->nullOnDelete();
            $table->string('status')->default('in_stock');

            $table->foreignId('source_id')
                ->nullable()
                ->constrained('phone_sources')
                ->nullOnDelete();
            $table->unsignedTinyInteger('confidence')->nullable();
            $table->timestamp('collected_at')->nullable();

            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamps();

            $table->unique(['phone_variant_id', 'store_id'], 'phone_availability_variant_store_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('phone_availability');
    }
};
