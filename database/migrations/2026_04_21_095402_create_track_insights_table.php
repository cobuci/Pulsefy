<?php

use App\Enums\TrackInsightStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('track_insights', function (Blueprint $table) {
            $table->id();
            $table->string('track_id', 100)->unique();
            $table->string('track_name');
            $table->string('artist_name');
            $table->string('album_name')->nullable();
            $table->string('status', 20)->default(TrackInsightStatus::Queued->value);
            $table->text('summary')->nullable();
            $table->string('mood')->nullable();
            $table->text('meaning')->nullable();
            $table->json('themes')->nullable();
            $table->json('trivia')->nullable();
            $table->json('similar')->nullable();
            $table->string('provider')->nullable();
            $table->string('model')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('track_insights');
    }
};
