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
        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('state_id')->constrained('states')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
            $table->text('question_text_ar');
            $table->text('question_text_en')->nullable();
            $table->enum('question_type', ['text', 'image'])->default('text');
            $table->string('image_url')->nullable();
            $table->text('option_a_ar');
            $table->text('option_b_ar');
            $table->text('option_c_ar');
            $table->text('option_d_ar');

            $table->text('option_a_en')->nullable();
            $table->text('option_b_en')->nullable();
            $table->text('option_c_en')->nullable();
            $table->text('option_d_en')->nullable();

            $table->enum('correct_answer', ['a', 'b', 'c', 'd']);

            $table->text('explanation_ar');

            $table->enum('difficulty_level', [
                'easy',
                'medium',
                'hard'
                 ])->default('medium');

            $table->enum('source_type', [
                'manual',
                'ai'
                ])->default('manual');

            $table->enum('import_status', [
                'active',
                'pending_review'
                ])->default('active');

            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('questions');
    }
};
