<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restaurant_menu_item_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_menu_item_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->integer('display_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_menu_item_images');
    }
};
