<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recommended_tracks', function (Blueprint $table) {
            $table->string('artist_name')->default('')->after('track_id');
        });

        Schema::table('discovery_liked_tracks', function (Blueprint $table) {
            $table->string('artist_name')->default('')->after('track_id');
        });
    }

    public function down(): void
    {
        Schema::table('recommended_tracks', function (Blueprint $table) {
            $table->dropColumn('artist_name');
        });

        Schema::table('discovery_liked_tracks', function (Blueprint $table) {
            $table->dropColumn('artist_name');
        });
    }
};
