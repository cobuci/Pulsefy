<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discovery_liked_tracks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('spotify_id');
            $table->string('name');
            $table->string('artist_name');
            $table->string('album_name');
            $table->string('image_url')->nullable();
            $table->timestamp('liked_at');
            $table->timestamps();

            $table->unique(['user_id', 'spotify_id']);
            $table->index(['user_id', 'liked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discovery_liked_tracks');
    }
};
