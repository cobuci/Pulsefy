<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('playlists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('folder_id')->nullable()->constrained('library_folders')->nullOnDelete();
            $table->string('spotify_id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('images')->nullable();
            $table->string('owner_spotify_id')->nullable();
            $table->string('owner_name')->nullable();
            $table->boolean('is_public')->default(false);
            $table->boolean('is_collaborative')->default(false);
            $table->unsignedInteger('tracks_total')->default(0);
            $table->string('snapshot_id')->nullable();
            $table->string('uri')->nullable();
            $table->json('external_urls')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'folder_id']);
            $table->unique(['user_id', 'spotify_id']);
            $table->index(['user_id', 'updated_at']);
            $table->index(['expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('playlists');
    }
};
