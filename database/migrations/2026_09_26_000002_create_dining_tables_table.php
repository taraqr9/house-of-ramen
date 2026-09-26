<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dining_tables', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50);
            $table->string('area', 100)->nullable();
            $table->unsignedSmallInteger('capacity')->default(4);
            $table->integer('display_order')->default(0);
            // Occupied/available is never stored here - it's derived from
            // whether an active order holds orders.active_table_id.
            $table->boolean('is_active')->default(true);
            $table->text('remarks')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dining_tables');
    }
};
