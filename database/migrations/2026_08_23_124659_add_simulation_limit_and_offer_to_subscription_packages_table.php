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
        Schema::table('subscription_packages', function (Blueprint $table) {
            // Null = unlimited simulation exam attempts for this package.
            $table->unsignedInteger('simulation_limit')->nullable()->after('duration_days');
            // Null = no active offer; when set (and higher than price_usd),
            // the mobile app shows it struck through next to the real price.
            $table->decimal('original_price_usd', 8, 2)->nullable()->after('price_usd');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscription_packages', function (Blueprint $table) {
            $table->dropColumn(['simulation_limit', 'original_price_usd']);
        });
    }
};
