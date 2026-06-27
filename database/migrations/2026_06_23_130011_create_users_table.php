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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('full_name')->nullable();
            $table->string('email')->unique()->nullable();
            $table->string('phone_number')->unique()->nullable();
            $table->string('password')->nullable();
            $table->string('profile_photo')->nullable();
            $table->enum('preferred_language', ['ar', 'en'])->default('ar');
            $table->foreignId('selected_state_id')->nullable()->constrained('states')
            ->nullOnDelete();
            $table->boolean('verification_status')->default(false);
            $table->enum('account_status', ['active', 'disabled'])->default('active');
            $table->unsignedInteger('free_questions_used')->default(0);
            $table->string('stripe_customer_id')->nullable();
            $table->string('apple_uid')->nullable();
            $table->string('google_uid')->nullable();
            $table->enum('role', ['user', 'admin'])->default('user');
            $table->timestamp('last_login')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
