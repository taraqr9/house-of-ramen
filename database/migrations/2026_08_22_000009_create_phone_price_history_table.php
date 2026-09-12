<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('phone_price_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('phone_variant_id')->constrained('phone_variants')->cascadeOnDelete();
            $table->foreignId('store_id')
                ->nullable()
                ->constrained('phone_stores')
                ->nullOnDelete();
            $table->string('price_type')->default('unofficial_bd');
            $table->decimal('amount', 10, 2);
            $table->decimal('previous_amount', 10, 2)->nullable();
            $table->string('currency', 3)->default('BDT');

            $table->foreignId('source_id')
                ->nullable()
                ->constrained('phone_sources')
                ->nullOnDelete();
            $table->timestamp('changed_at');
            $table->timestamps();

            $table->index(['phone_variant_id', 'store_id', 'price_type', 'changed_at'], 'phone_price_history_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('phone_price_history');
    }
};
