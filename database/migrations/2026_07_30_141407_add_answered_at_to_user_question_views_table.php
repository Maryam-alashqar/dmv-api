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
        Schema::table('user_question_views', function (Blueprint $table) {
            $table->timestamp('answered_at')->nullable()->after('viewed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_question_views', function (Blueprint $table) {
            $table->dropColumn('answered_at');
        });
    }
};
