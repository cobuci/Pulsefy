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
        Schema::create('albums', function (Blueprint $table) {
            $table->id();
            $table->string('spotify_id')->unique();
            $table->string('name');
            $table->string('album_type')->nullable();
            $table->string('release_date')->nullable();
            $table->json('images')->nullable();
            $table->unsignedInteger('total_tracks')->default(0);
            $table->timestamp('metadata_synced_at')->nullable();
            $table->timestamps();

            $table->index('metadata_synced_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('albums');
    }
};
