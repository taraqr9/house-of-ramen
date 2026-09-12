<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('phone_network_bands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('phone_id')->constrained('phones')->cascadeOnDelete();
            $table->string('network_type');
            $table->text('bands')->nullable();
            $table->foreignId('source_id')
                ->nullable()
                ->constrained('phone_sources')
                ->nullOnDelete();
            $table->timestamps();

            $table->unique(['phone_id', 'network_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('phone_network_bands');
    }
};
