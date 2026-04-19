<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('playlist_tracks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('playlist_id')->constrained()->cascadeOnDelete();
            $table->foreignId('track_id')->nullable()->constrained('tracks')->nullOnDelete();
            $table->string('spotify_track_id');
            $table->unsignedInteger('position');
            $table->timestamp('added_at')->nullable();
            $table->string('added_by_spotify_id')->nullable();
            $table->timestamps();

            $table->unique(['playlist_id', 'position']);
            $table->index(['playlist_id', 'spotify_track_id']);
            $table->index(['track_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('playlist_tracks');
    }
};
