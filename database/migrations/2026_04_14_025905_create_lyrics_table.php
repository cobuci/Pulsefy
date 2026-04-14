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
        Schema::create('lyrics', function (Blueprint $table) {
            $table->id();
            $table->string('track_id')->unique();
            $table->string('artist_name');
            $table->string('track_name');
            $table->longText('synced_lyrics')->nullable();
            $table->longText('plain_lyrics')->nullable();
            $table->boolean('is_synced')->default(false);
            $table->string('source')->default('lrclib');
            $table->timestamp('fetched_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lyrics');
    }
};
