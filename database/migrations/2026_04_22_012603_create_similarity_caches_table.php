<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('similarity_caches', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20);
            $table->string('key');
            $table->json('payload');
            $table->timestamp('fetched_at');
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->unique(['type', 'key']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('similarity_caches');
    }
};
