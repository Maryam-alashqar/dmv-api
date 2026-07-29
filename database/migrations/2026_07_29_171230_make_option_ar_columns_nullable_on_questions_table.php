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
            $table->text('option_a_ar')->nullable()->change();
            $table->text('option_b_ar')->nullable()->change();
            $table->text('option_c_ar')->nullable()->change();
            $table->text('option_d_ar')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->text('option_a_ar')->nullable(false)->change();
            $table->text('option_b_ar')->nullable(false)->change();
            $table->text('option_c_ar')->nullable(false)->change();
            $table->text('option_d_ar')->nullable(false)->change();
        });
    }
};
