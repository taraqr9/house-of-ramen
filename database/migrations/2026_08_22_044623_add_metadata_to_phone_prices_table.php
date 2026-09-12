<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('phone_prices', function (Blueprint $table) {
            $table->string('source_url')->nullable()->after('currency');
            $table->string('warranty_type')->nullable()->after('source_url');
            // Distinct from collected_at: collected_at is when this
            // observation's value was last written; last_verified_at is
            // when a source most recently re-confirmed the listing still
            // shows this price, even if the amount itself didn't change.
            $table->timestamp('last_verified_at')->nullable()->after('collected_at');
        });
    }

    public function down(): void
    {
        Schema::table('phone_prices', function (Blueprint $table) {
            $table->dropColumn(['source_url', 'warranty_type', 'last_verified_at']);
        });
    }
};
