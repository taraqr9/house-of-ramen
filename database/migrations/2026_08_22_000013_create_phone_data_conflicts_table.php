<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('phone_data_conflicts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('phone_id')->constrained('phones')->cascadeOnDelete();
            $table->foreignId('phone_variant_id')
                ->nullable()
                ->constrained('phone_variants')
                ->cascadeOnDelete();

            $table->string('table_name');
            $table->string('field');

            $table->string('existing_value')->nullable();
            $table->foreignId('existing_source_id')
                ->nullable()
                ->constrained('phone_sources')
                ->nullOnDelete();

            $table->string('new_value')->nullable();
            $table->foreignId('new_source_id')
                ->nullable()
                ->constrained('phone_sources')
                ->nullOnDelete();

            $table->foreignId('import_record_id')
                ->nullable()
                ->constrained('phone_import_records')
                ->nullOnDelete();

            $table->string('status')->default('open');
            $table->string('resolved_value')->nullable();
            $table->text('resolution_note')->nullable();
            $table->foreignId('resolved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();

            $table->timestamps();

            $table->index(['phone_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('phone_data_conflicts');
    }
};
