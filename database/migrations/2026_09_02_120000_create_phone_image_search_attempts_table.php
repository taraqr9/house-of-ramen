<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per phone - the persistent memory that makes manual/agent-driven
 * image enrichment resumable across sessions without re-searching a phone
 * that was already confidently exhausted (see ImageSearchAttemptStatusEnum).
 * Mirrors the existing phone_retailer_match_attempts pattern for the same
 * reason: "no verified image yet" is not enough signal on its own to know
 * whether a phone has never been looked at or was already searched and
 * genuinely has no trustworthy source - conflating those two meant every
 * fresh session re-attempted phones a prior session had already spent a
 * capped effort on and correctly given up on.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('phone_image_search_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('phone_id')->constrained('phones')->cascadeOnDelete();
            $table->string('status')->default('pending'); // ImageSearchAttemptStatusEnum
            $table->text('unresolved_reason')->nullable();
            $table->json('sources_tried')->nullable();
            $table->unsignedTinyInteger('candidates_viewed')->default(0);
            $table->timestamp('last_attempted_at')->nullable();
            $table->timestamps();

            $table->unique('phone_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('phone_image_search_attempts');
    }
};
