<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('restaurant_menu_item_id')->nullable()->constrained()->nullOnDelete();
            // Snapshots - an order must keep showing what was actually sold
            // even after the menu item/category is renamed, repriced, or
            // (soft-)deleted.
            $table->unsignedBigInteger('restaurant_menu_category_id')->nullable()->index();
            $table->string('item_name');
            $table->string('category_name')->nullable();
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 10, 2);
            $table->decimal('line_total', 12, 2);
            $table->string('note')->nullable();
            $table->unsignedSmallInteger('round_no')->default(1);
            $table->string('kitchen_status', 20);

            $table->timestamp('sent_at')->nullable();
            $table->timestamp('preparing_at')->nullable();
            $table->timestamp('ready_at')->nullable();
            $table->timestamp('ready_acknowledged_at')->nullable();
            $table->timestamp('served_at')->nullable();
            $table->foreignId('served_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cancellation_reason')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamps();

            // Kitchen/Ready-to-Serve polling reads "items in status X changed
            // since T".
            $table->index(['kitchen_status', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
