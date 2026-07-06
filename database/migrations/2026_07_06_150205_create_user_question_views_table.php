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
       Schema::create('user_question_views', function (Blueprint $table) {
        $table->id();

       $table->foreignId('user_id')->constrained()->cascadeOnDelete();
       $table->foreignId('question_id')->constrained()->cascadeOnDelete();
       $table->timestamp('viewed_at')->nullable();
       $table->timestamps();
       $table->unique(['user_id', 'question_id']);
       });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_question_views');
    }
};
