<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('playlists', function (Blueprint $table): void {
            $table->boolean('is_liked_playlist')->default(false)->after('is_hidden');
            $table->index(['user_id', 'is_liked_playlist']);
        });
    }

    public function down(): void
    {
        Schema::table('playlists', function (Blueprint $table): void {
            $table->dropIndex(['user_id', 'is_liked_playlist']);
            $table->dropColumn('is_liked_playlist');
        });
    }
};
