<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per (variant, official|unofficial) - the outlier-resistant
     * "current market price" the rest of the app (recommendation engine,
     * comparison, phone detail) should read, computed from every active
     * phone_prices observation for that pair. phone_prices stays the raw,
     * per-retailer truth; this table is a derived, recalculated cache -
     * see App\Services\PhoneImport\PriceAggregator.
     */
    public function up(): void
    {
        Schema::create('phone_market_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('phone_variant_id')->constrained('phone_variants')->cascadeOnDelete();
            $table->string('price_type');

            $table->decimal('price', 10, 2);
            $table->decimal('price_min', 10, 2)->nullable();
            $table->decimal('price_max', 10, 2)->nullable();

            $table->unsignedSmallInteger('observation_count')->default(0);
            $table->unsignedSmallInteger('retailer_count')->default(0);
            $table->unsignedSmallInteger('outlier_count')->default(0);

            $table->timestamp('calculated_at')->nullable();
            $table->timestamps();

            $table->unique(['phone_variant_id', 'price_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('phone_market_prices');
    }
};
