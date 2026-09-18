<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurant_menu_items', function (Blueprint $table) {
            // Separate from is_featured - lets an admin flag a freshly
            // added dish for the homepage "New on the Menu" section
            // without also pulling it into "Featured on the Menu".
            $table->boolean('is_new')->default(false)->after('is_featured');
        });
    }

    public function down(): void
    {
        Schema::table('restaurant_menu_items', function (Blueprint $table) {
            $table->dropColumn('is_new');
        });
    }
};
