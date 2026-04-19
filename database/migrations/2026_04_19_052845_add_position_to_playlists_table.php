<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('playlists', function (Blueprint $table) {
            $table->unsignedInteger('position')->default(0)->after('folder_id');
            $table->index(['user_id', 'folder_id', 'position']);
        });

        DB::table('playlists')->update([
            'position' => DB::raw('id'),
        ]);
    }

    public function down(): void
    {
        Schema::table('playlists', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'folder_id', 'position']);
            $table->dropColumn('position');
        });
    }
};
