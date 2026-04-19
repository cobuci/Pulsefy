<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('playlists', function (Blueprint $table) {
            $table->boolean('is_hidden')->default(false)->after('position');
            $table->index(['user_id', 'is_hidden', 'folder_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::table('playlists', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'is_hidden', 'folder_id', 'position']);
            $table->dropColumn('is_hidden');
        });
    }
};
