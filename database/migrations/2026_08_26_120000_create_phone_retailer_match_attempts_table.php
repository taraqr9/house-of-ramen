<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per (phone, retailer source, price_type lane) - the
 * persistent memory that makes catalogue-wide retailer discovery
 * idempotent and resumable without re-probing every candidate URL on
 * every run. See App\Services\PhoneImport\Sources\Retailers\
 * RetailerListingSource. price_type is part of the key (not just an
 * attribute) because a marketplace retailer's official-channel listing
 * and its grey-import listing are discovered independently - see
 * RetailerListingSource::candidateLanes() - and must be tracked (and
 * retried) independently too.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('phone_retailer_match_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('phone_id')->constrained('phones')->cascadeOnDelete();
            $table->string('source_key'); // matches phone_sources.key
            $table->string('price_type'); // PriceTypeEnum - which lane this attempt is for
            $table->string('status'); // RetailerMatchStatusEnum
            $table->string('candidate_url')->nullable();
            $table->foreignId('matched_variant_id')->nullable()->constrained('phone_variants')->nullOnDelete();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->unsignedInteger('attempts')->default(1);
            $table->text('note')->nullable();
            $table->timestamp('checked_at');
            $table->timestamp('next_check_at');
            $table->timestamps();

            $table->unique(
                ['phone_id', 'source_key', 'price_type'],
                'retailer_match_attempts_unique'
            );
            $table->index('next_check_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('phone_retailer_match_attempts');
    }
};
