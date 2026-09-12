<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('phone_data_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('phone_id')
                ->nullable()
                ->constrained('phones')
                ->cascadeOnDelete();
            $table->foreignId('phone_variant_id')
                ->nullable()
                ->constrained('phone_variants')
                ->cascadeOnDelete();
            $table->foreignId('import_record_id')
                ->nullable()
                ->constrained('phone_import_records')
                ->nullOnDelete();

            $table->string('reason', 100);
            $table->foreignId('matched_phone_id')
                ->nullable()
                ->constrained('phones')
                ->nullOnDelete();
            $table->unsignedTinyInteger('similarity_score')->nullable();

            $table->string('status', 100)->default('pending');
            $table->json('details')->nullable();

            $table->foreignId('reviewed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('resolution_note')->nullable();

            $table->timestamps();

            $table->index(['status', 'reason']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('phone_data_reviews');
    }
};
