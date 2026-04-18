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
        Schema::table('artists', function (Blueprint $table) {
            $table->json('images')->nullable()->after('genres');
            $table->unsignedTinyInteger('popularity')->nullable()->after('images');
            $table->string('uri')->nullable()->after('popularity');
            $table->json('external_urls')->nullable()->after('uri');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('artists', function (Blueprint $table) {
            $table->dropColumn(['images', 'popularity', 'uri', 'external_urls']);
        });
    }
};
