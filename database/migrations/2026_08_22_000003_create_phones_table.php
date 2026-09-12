<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('phones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained('brands')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('model_number')->nullable();
            $table->date('announced_date')->nullable();
            $table->date('release_date')->nullable();
            $table->string('status')->default('available');
            $table->string('category')->nullable();

            $table->text('summary')->nullable();
            $table->boolean('is_ai_generated_summary')->default(false);

            $table->unsignedTinyInteger('identity_confidence')->nullable();
            $table->unsignedTinyInteger('spec_confidence')->nullable();
            $table->unsignedTinyInteger('software_confidence')->nullable();
            $table->unsignedTinyInteger('overall_confidence')->nullable();
            $table->timestamp('confidence_calculated_at')->nullable();

            $table->foreignId('primary_source_id')
                ->nullable()
                ->constrained('phone_sources')
                ->nullOnDelete();
            $table->timestamp('collected_at')->nullable();
            $table->timestamp('last_verified_at')->nullable();

            $table->boolean('is_active')->default(true);
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['brand_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('phones');
    }
};
