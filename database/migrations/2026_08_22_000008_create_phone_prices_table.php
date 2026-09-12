<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('phone_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('phone_variant_id')->constrained('phone_variants')->cascadeOnDelete();
            $table->foreignId('store_id')
                ->nullable()
                ->constrained('phone_stores')
                ->nullOnDelete();
            $table->string('price_type')->default('unofficial_bd');
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('BDT');

            $table->foreignId('source_id')
                ->nullable()
                ->constrained('phone_sources')
                ->nullOnDelete();
            $table->unsignedTinyInteger('confidence')->nullable();
            $table->timestamp('collected_at')->nullable();

            $table->boolean('is_active')->default(true);
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamps();

            $table->index(['phone_variant_id', 'store_id', 'price_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('phone_prices');
    }
};
