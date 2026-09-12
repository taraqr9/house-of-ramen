<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('phone_import_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_id')
                ->nullable()
                ->constrained('phone_sources')
                ->nullOnDelete();
            $table->string('type', 100)->default('manual');
            $table->string('status', 100)->default('pending');

            $table->json('cursor')->nullable();

            $table->unsignedInteger('total_discovered')->default(0);
            $table->unsignedInteger('total_created')->default(0);
            $table->unsignedInteger('total_updated')->default(0);
            $table->unsignedInteger('total_skipped')->default(0);
            $table->unsignedInteger('total_failed')->default(0);
            $table->unsignedInteger('total_conflicts')->default(0);
            $table->unsignedInteger('total_flagged')->default(0);

            $table->text('error_message')->nullable();
            $table->foreignId('triggered_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('phone_import_runs');
    }
};
