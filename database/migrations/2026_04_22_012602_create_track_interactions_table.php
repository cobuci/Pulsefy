<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('track_interactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('spotify_id');
            $table->string('type', 10);
            $table->timestamp('interacted_at');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'spotify_id', 'type']);
            $table->index(['user_id', 'type', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('track_interactions');
    }
};
