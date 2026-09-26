<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            // POS billing rates, edited from Restaurant Settings. Each order
            // snapshots them when it's opened (see orders.vat_percent) so a
            // later settings change never rewrites an old bill.
            $table->decimal('vat_percent', 5, 2)->default(0)->after('delivery_platforms');
            $table->decimal('service_charge_percent', 5, 2)->default(0)->after('vat_percent');
        });
    }

    public function down(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->dropColumn(['vat_percent', 'service_charge_percent']);
        });
    }
};
