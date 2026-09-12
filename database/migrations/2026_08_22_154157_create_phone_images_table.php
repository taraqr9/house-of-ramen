<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('phone_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('phone_id')->constrained('phones')->cascadeOnDelete();
            $table->foreignId('phone_variant_id')
                ->nullable()
                ->constrained('phone_variants')
                ->nullOnDelete();

            $table->boolean('is_primary')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);

            // Where the file actually lives once collected/optimized. Nullable
            // because a rejected/needs-review candidate may only have the
            // external_url populated (never downloaded/served publicly).
            $table->string('disk')->default('public');
            $table->string('path')->nullable();
            $table->text('external_url')->nullable();

            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedInteger('file_size_bytes')->nullable();
            $table->string('mime_type')->nullable();

            // Provenance: which phone_sources row this came from (e.g. the
            // Wikimedia Commons adapter, or an admin upload), plus the
            // specific page/file it was collected from and the license
            // string required for attribution.
            $table->foreignId('source_id')
                ->nullable()
                ->constrained('phone_sources')
                ->nullOnDelete();
            $table->text('source_url')->nullable();
            $table->string('license')->nullable();
            $table->text('attribution')->nullable();

            $table->unsignedTinyInteger('match_confidence')->nullable();
            $table->string('status')->default('needs_review');

            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamps();

            $table->index(['phone_id', 'is_primary']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('phone_images');
    }
};
