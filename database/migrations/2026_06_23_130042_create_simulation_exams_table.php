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
        Schema::create('simulation_exams', function (Blueprint $table) {
            $table->id();
            $table->string('title_ar');
            $table->foreignId('state_id')->constrained('states')->cascadeOnDelete();
            $table->unsignedInteger('total_questions');
            $table->unsignedInteger('passing_score');
            $table->boolean('is_published')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('simulation_exams');
    }
};
