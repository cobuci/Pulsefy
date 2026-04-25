<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Fresh installs already have the correct index from the original migration.
        // This migration only fixes environments where the stale index name persists.
        if (! Schema::hasIndex('discovery_liked_tracks', 'discovery_liked_tracks_user_id_track_id_unique')) {
            Schema::table('discovery_liked_tracks', function (Blueprint $table) {
                if (Schema::hasIndex('discovery_liked_tracks', 'discovery_liked_tracks_user_id_spotify_id_unique')) {
                    $table->dropUnique('discovery_liked_tracks_user_id_spotify_id_unique');
                }

                $table->unique(['user_id', 'track_id']);
            });
        }
    }

    public function down(): void
    {
        //
    }
};
