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
        Schema::table('questions', function (Blueprint $table) {
            $table->string('option_a_image')->nullable()->after('option_d_en');
            $table->string('option_b_image')->nullable()->after('option_a_image');
            $table->string('option_c_image')->nullable()->after('option_b_image');
            $table->string('option_d_image')->nullable()->after('option_c_image');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropColumn(['option_a_image', 'option_b_image', 'option_c_image', 'option_d_image']);
        });
    }
};
