<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            // Filled right after insert from the id (see OrderService::open)
            // so it's unique without a separate counter table.
            $table->string('order_number', 30)->nullable()->unique();
            $table->string('order_type', 20);
            $table->foreignId('dining_table_id')->nullable()->constrained()->nullOnDelete();
            // Equals dining_table_id while the order is open/bill-requested,
            // null once completed/cancelled - the unique index is what
            // guarantees one active order per table at the DB level (MySQL
            // has no partial unique indexes; multiple NULLs are allowed).
            $table->unsignedBigInteger('active_table_id')->nullable()->unique();
            $table->string('table_name', 50)->nullable();
            $table->unsignedSmallInteger('guest_count')->nullable();
            $table->string('status', 20)->index();

            $table->decimal('subtotal', 12, 2)->default(0);
            $table->string('discount_type', 10)->nullable();
            $table->decimal('discount_value', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('vat_percent', 5, 2)->default(0);
            $table->decimal('vat', 12, 2)->default(0);
            $table->decimal('service_charge_percent', 5, 2)->default(0);
            $table->decimal('service_charge', 12, 2)->default(0);
            $table->decimal('grand_total', 12, 2)->default(0);
            $table->decimal('paid_total', 12, 2)->default(0);

            $table->text('general_note')->nullable();
            $table->foreignId('opened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('bill_requested_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable()->index();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancellation_reason')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
