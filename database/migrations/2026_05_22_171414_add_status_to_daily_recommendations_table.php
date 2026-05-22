<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_recommendations', function (Blueprint $table) {
            $table->string('status')->default('processing')->after('generated_at');
            $table->timestamp('started_at')->nullable()->after('status');
            $table->text('error_message')->nullable()->after('started_at');
        });

        DB::table('daily_recommendations')->update(['status' => 'ready']);
    }

    public function down(): void
    {
        Schema::table('daily_recommendations', function (Blueprint $table) {
            $table->dropColumn(['status', 'started_at', 'error_message']);
        });
    }
};
