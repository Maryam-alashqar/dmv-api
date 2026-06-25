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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('package_id')->constrained('subscription_packages')->cascadeOnDelete();
            $table->decimal('amount_usd', 8, 2);
            $table->enum('payment_method', [
                'credit_card',
                'debit_card',
                'apple_pay',
                'google_pay',
                'apple_iap'
                ]);
            $table->string('stripe_payment_intent_id')->nullable();
            $table->string('apple_transaction_id')->nullable();
            $table->enum('payment_status', [
                'pending',
                'confirmed',
                'failed',
                'refunded'
                ])->default('pending');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
