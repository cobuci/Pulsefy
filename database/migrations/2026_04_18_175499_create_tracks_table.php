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
        Schema::create('tracks', function (Blueprint $table) {
            $table->id();
            $table->string('spotify_id')->unique();
            $table->foreignId('album_id')->nullable()->constrained('albums')->nullOnDelete();
            $table->string('name');
            $table->unsignedInteger('duration_ms')->default(0);
            $table->boolean('explicit')->default(false);
            $table->timestamp('metadata_synced_at')->nullable();
            $table->timestamps();

            $table->index(['album_id', 'metadata_synced_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tracks');
    }
};
