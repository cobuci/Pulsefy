<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lyric_pronunciations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lyric_id')->constrained()->cascadeOnDelete();
            $table->string('track_id')->index();
            $table->string('status', 20)->default('queued');
            $table->json('romanized_lines')->nullable();
            $table->string('provider', 50)->nullable();
            $table->string('model', 120)->nullable();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'lyric_id']);
            $table->index(['user_id', 'track_id']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lyric_pronunciations');
    }
};
