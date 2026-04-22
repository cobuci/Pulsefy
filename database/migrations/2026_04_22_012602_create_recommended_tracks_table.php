<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recommended_tracks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('daily_recommendation_id')->constrained()->cascadeOnDelete();
            $table->string('spotify_id');
            $table->string('name');
            $table->string('artist_name');
            $table->string('album_name');
            $table->string('image_url')->nullable();
            $table->string('preview_url')->nullable();
            $table->unsignedTinyInteger('match_score');
            $table->unsignedTinyInteger('position');
            $table->timestamps();

            $table->index('daily_recommendation_id');
            $table->index('spotify_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recommended_tracks');
    }
};
