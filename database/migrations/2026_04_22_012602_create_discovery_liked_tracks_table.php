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
            $table->foreignId('track_id')->constrained('tracks')->cascadeOnDelete();
            $table->timestamp('liked_at');
            $table->timestamps();

            $table->unique(['user_id', 'track_id']);
            $table->index(['user_id', 'liked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discovery_liked_tracks');
    }
};
