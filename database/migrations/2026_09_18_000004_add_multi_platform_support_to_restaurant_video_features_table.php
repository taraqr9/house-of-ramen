<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurant_video_features', function (Blueprint $table) {
            // Renamed from youtube_url now that Facebook and Instagram
            // links are accepted too, not just YouTube.
            $table->renameColumn('youtube_url', 'video_url');
        });

        Schema::table('restaurant_video_features', function (Blueprint $table) {
            // Facebook/Instagram have no public, keyless thumbnail
            // endpoint the way YouTube does (img.youtube.com), so for
            // those platforms (or to override YouTube's auto thumbnail)
            // the admin can upload one directly.
            $table->string('thumbnail_path')->nullable()->after('video_url');
        });
    }

    public function down(): void
    {
        Schema::table('restaurant_video_features', function (Blueprint $table) {
            $table->dropColumn('thumbnail_path');
        });

        Schema::table('restaurant_video_features', function (Blueprint $table) {
            $table->renameColumn('video_url', 'youtube_url');
        });
    }
};
