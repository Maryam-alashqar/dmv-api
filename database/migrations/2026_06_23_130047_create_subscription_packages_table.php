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
        Schema::create('subscription_packages', function (Blueprint $table) {
            $table->id();
             $table->string('name_ar');
             $table->string('name_en');
             $table->unsignedInteger('duration_days')->default(30);
             $table->decimal('price_usd', 8, 2);
             $table->string('stripe_price_id')->nullable();
             $table->string('apple_product_id')->nullable();
             $table->json('features')->nullable();
             $table->boolean('is_active')->default(true);
             $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscription_packages');
    }
};
