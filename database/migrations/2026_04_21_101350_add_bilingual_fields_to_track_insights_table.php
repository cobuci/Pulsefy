<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('track_insights', function (Blueprint $table) {
            $table->text('summary_pt')->nullable()->after('summary');
            $table->string('mood_pt')->nullable()->after('mood');
            $table->text('meaning_pt')->nullable()->after('meaning');
            $table->json('themes_pt')->nullable()->after('themes');
            $table->json('trivia_pt')->nullable()->after('trivia');
            $table->json('similar_pt')->nullable()->after('similar');
        });
    }

    public function down(): void
    {
        Schema::table('track_insights', function (Blueprint $table) {
            $table->dropColumn(['summary_pt', 'mood_pt', 'meaning_pt', 'themes_pt', 'trivia_pt', 'similar_pt']);
        });
    }
};
