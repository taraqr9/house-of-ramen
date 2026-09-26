<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            // Client-generated key per "send round" (web cart or Android).
            // Checked under the order row lock, so a retry - even hours
            // later from an offline phone queue - never sends the round
            // twice. Not unique: every item in one round shares it.
            $table->string('submission_key', 64)->nullable()->after('round_no');
            $table->index(['order_id', 'submission_key']);
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropIndex(['order_id', 'submission_key']);
            $table->dropColumn('submission_key');
        });
    }
};
