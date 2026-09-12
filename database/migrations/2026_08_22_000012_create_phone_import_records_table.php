<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('phone_import_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_run_id')->constrained('phone_import_runs')->cascadeOnDelete();
            $table->foreignId('source_id')->constrained('phone_sources')->cascadeOnDelete();
            $table->foreignId('phone_id')
                ->nullable()
                ->constrained('phones')
                ->nullOnDelete();
            $table->foreignId('phone_variant_id')
                ->nullable()
                ->constrained('phone_variants')
                ->nullOnDelete();

            $table->string('external_ref');
            $table->string('match_status')->default('new');
            $table->unsignedTinyInteger('confidence_score')->nullable();

            $table->json('raw_payload')->nullable();
            $table->json('normalized_payload')->nullable();
            $table->text('error_message')->nullable();

            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['source_id', 'external_ref']);
            $table->index(['import_run_id', 'match_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('phone_import_records');
    }
};
