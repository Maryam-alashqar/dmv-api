<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('subscription_packages')->where('apple_product_id', '')->update(['apple_product_id' => null]);
        DB::table('payments')->where('apple_transaction_id', '')->update(['apple_transaction_id' => null]);

        $duplicateProduct = DB::table('subscription_packages')
            ->select('apple_product_id')
            ->whereNotNull('apple_product_id')
            ->groupBy('apple_product_id')
            ->havingRaw('COUNT(*) > 1')
            ->value('apple_product_id');
        $duplicateTransaction = DB::table('payments')
            ->select('apple_transaction_id')
            ->whereNotNull('apple_transaction_id')
            ->groupBy('apple_transaction_id')
            ->havingRaw('COUNT(*) > 1')
            ->value('apple_transaction_id');

        if ($duplicateProduct !== null || $duplicateTransaction !== null) {
            throw new RuntimeException('Duplicate Apple product or transaction IDs must be resolved before running this migration.');
        }

        Schema::table('users', function (Blueprint $table) {
            $table->uuid('apple_app_account_token')->nullable()->unique()->after('apple_uid');
        });

        Schema::table('subscription_packages', function (Blueprint $table) {
            $table->unique('apple_product_id', 'subscription_packages_apple_product_id_unique');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->unique('apple_transaction_id', 'payments_apple_transaction_id_unique');
        });

        Schema::table('user_subscriptions', function (Blueprint $table) {
            $table->index(['user_id', 'status', 'expiry_date'], 'user_subscriptions_active_lookup_index');
        });

        Schema::create('apple_iap_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->string('product_id');
            $table->string('transaction_id')->unique();
            $table->string('original_transaction_id')->nullable()->index();
            $table->uuid('app_account_token')->nullable()->index();
            $table->dateTime('purchase_date');
            $table->dateTime('expires_date');
            $table->enum('environment', ['sandbox', 'production']);
            $table->dateTime('revocation_date')->nullable();
            $table->longText('signed_transaction_info');
            $table->dateTime('verified_at');
            $table->timestamps();

            $table->index(['user_id', 'expires_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('apple_iap_transactions');

        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique('payments_apple_transaction_id_unique');
        });

        Schema::table('user_subscriptions', function (Blueprint $table) {
            $table->dropIndex('user_subscriptions_active_lookup_index');
        });

        Schema::table('subscription_packages', function (Blueprint $table) {
            $table->dropUnique('subscription_packages_apple_product_id_unique');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['apple_app_account_token']);
            $table->dropColumn('apple_app_account_token');
        });
    }
};
