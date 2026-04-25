<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recommended_tracks', function (Blueprint $table) {
            $table->string('spotify_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('recommended_tracks', function (Blueprint $table) {
            $table->string('spotify_id')->nullable(false)->change();
        });
    }
};
