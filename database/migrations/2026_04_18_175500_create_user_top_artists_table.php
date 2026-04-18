<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('user_top_artists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('artist_model_id')->constrained('artists')->cascadeOnDelete();
            $table->string('time_range');
            $table->unsignedSmallInteger('rank');
            $table->unsignedInteger('score')->default(0);
            $table->timestamp('synced_at');
            $table->timestamps();

            $table->unique(['user_id', 'artist_model_id', 'time_range']);
            $table->index(['user_id', 'time_range', 'rank']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_top_artists');
    }
};
