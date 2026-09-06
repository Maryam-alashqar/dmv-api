<?php

use App\Dto\VerifiedAppleTransaction;
use App\Models\AppleIapTransaction;
use App\Models\Payment;
use App\Models\SubscriptionPackage;
use App\Models\User;
use App\Models\UserSubscription;
use App\Services\AppleAppStoreService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Mockery\MockInterface;

beforeEach(function () {
    Carbon::setTestNow('2026-09-06 12:00:00 UTC');

    $this->createdUserIds = [];

    $this->user = User::create([
        'full_name' => 'Apple IAP User',
        'phone_number' => '07' . rand(10000000, 99999999),
        'email' => 'appleiap' . uniqid() . '@example.com',
        'password' => Hash::make('password'),
        'verification_status' => true,
        'account_status' => 'active',
        'apple_app_account_token' => '11111111-1111-4111-8111-111111111111',
    ]);
    $this->createdUserIds[] = $this->user->id;

    $this->token = $this->user->createToken('test', ['access'])->plainTextToken;
    $this->package = SubscriptionPackage::create([
        'name_ar' => 'شهري', 'name_en' => 'Monthly',
        'duration_days' => 30, 'price_usd' => 4.99,
        'apple_product_id' => 'com.dmv.us.monthly.' . uniqid(),
        'is_active' => true,
    ]);
});

afterEach(function () {
    Carbon::setTestNow();
    User::whereIn('id', $this->createdUserIds)->delete();
    $this->package->delete();
});

function verifiedAppleTransaction(array $overrides = []): VerifiedAppleTransaction
{
    return new VerifiedAppleTransaction(...array_merge([
        'transactionId' => '2000000000000001-' . uniqid(),
        'originalTransactionId' => '2000000000000001',
        'productId' => 'com.dmv.us.monthly',
        'purchaseDate' => Carbon::now()->utc(),
        'environment' => 'sandbox',
        'appAccountToken' => '11111111-1111-4111-8111-111111111111',
        'revocationDate' => null,
        'signedTransactionInfo' => 'header.payload.signature',
    ], $overrides));
}

function fakeAppleVerification(VerifiedAppleTransaction $transaction): void
{
    test()->mock(AppleAppStoreService::class, function (MockInterface $mock) use ($transaction) {
        $mock->shouldReceive('verifyTransaction')
            ->once()
            ->with($transaction->transactionId)
            ->andReturn($transaction);
    });
}

test('a verified Apple non-renewing purchase activates the matching package', function () {
    $transaction = verifiedAppleTransaction(['productId' => $this->package->apple_product_id]);
    fakeAppleVerification($transaction);

    $this->withToken($this->token)
        ->postJson('/api/subscriptions/verify', ['transaction_id' => $transaction->transactionId])
        ->assertOk()
        ->assertJsonPath('data.already_processed', false)
        ->assertJsonPath('data.product_id', $this->package->apple_product_id)
        ->assertJsonPath('data.environment', 'sandbox');

    expect(Payment::where('user_id', $this->user->id)->count())->toBe(1)
        ->and(UserSubscription::where('user_id', $this->user->id)->count())->toBe(1)
        ->and(AppleIapTransaction::where('user_id', $this->user->id)->count())->toBe(1)
        ->and(UserSubscription::where('user_id', $this->user->id)->first()->expiry_date->equalTo(Carbon::now()->addDays(30)))->toBeTrue()
        ->and(UserSubscription::where('user_id', $this->user->id)->first()->auto_renewal)->toBeFalse();
});

test('repeating the same transaction is idempotent for the same user', function () {
    $transaction = verifiedAppleTransaction(['productId' => $this->package->apple_product_id]);
    fakeAppleVerification($transaction);
    $this->withToken($this->token)->postJson('/api/subscriptions/verify', ['transaction_id' => $transaction->transactionId])->assertOk();

    fakeAppleVerification($transaction);
    $this->withToken($this->token)
        ->postJson('/api/subscriptions/verify', ['transaction_id' => $transaction->transactionId])
        ->assertOk()
        ->assertJsonPath('data.already_processed', true);

    expect(Payment::where('user_id', $this->user->id)->count())->toBe(1)
        ->and(UserSubscription::where('user_id', $this->user->id)->count())->toBe(1)
        ->and(AppleIapTransaction::where('user_id', $this->user->id)->count())->toBe(1);
});

test('the same transaction cannot be claimed by another user', function () {
    $transaction = verifiedAppleTransaction(['productId' => $this->package->apple_product_id, 'appAccountToken' => null]);
    fakeAppleVerification($transaction);
    $this->withToken($this->token)->postJson('/api/subscriptions/verify', ['transaction_id' => $transaction->transactionId])->assertOk();

    $otherUser = User::create([
        'full_name' => 'Other User',
        'phone_number' => '07' . rand(10000000, 99999999),
        'email' => 'otherappleiap' . uniqid() . '@example.com',
        'password' => Hash::make('password'),
        'verification_status' => true,
        'account_status' => 'active',
    ]);
    $this->createdUserIds[] = $otherUser->id;
    $otherToken = $otherUser->createToken('test', ['access'])->plainTextToken;

    fakeAppleVerification($transaction);

    // Laravel's Auth manager caches the resolved user on the guard instance
    // for the lifetime of the test's shared app container, so a second
    // in-test request with a different bearer token would otherwise still
    // resolve to the first request's user — forget the guard so Sanctum
    // re-resolves fresh from this request's own Authorization header, the
    // way two genuinely separate production requests always would.
    \Illuminate\Support\Facades\Auth::forgetGuards();

    $this->withToken($otherToken)
        ->postJson('/api/subscriptions/verify', ['transaction_id' => $transaction->transactionId])
        ->assertStatus(409);

    expect(Payment::where('user_id', $this->user->id)->count())->toBe(1);
});

test('the verified product must be configured and cannot be selected by the client', function () {
    $transaction = verifiedAppleTransaction(['productId' => 'com.dmv.us.unknown.' . uniqid()]);
    fakeAppleVerification($transaction);

    $this->withToken($this->token)
        ->postJson('/api/subscriptions/verify', [
            'transaction_id' => $transaction->transactionId,
            'product_id' => $this->package->apple_product_id,
        ])
        ->assertStatus(422);

    expect(Payment::where('user_id', $this->user->id)->count())->toBe(0);
});

test('a purchase made during an active period extends from the current expiry', function () {
    $payment = Payment::create([
        'user_id' => $this->user->id,
        'package_id' => $this->package->id,
        'amount_usd' => 4.99,
        'payment_method' => 'apple_iap',
        'apple_transaction_id' => 'old-transaction-' . uniqid(),
        'payment_status' => 'confirmed',
    ]);
    $current = UserSubscription::create([
        'user_id' => $this->user->id,
        'package_id' => $this->package->id,
        'payment_id' => $payment->id,
        'activation_date' => Carbon::now()->subDays(20),
        'expiry_date' => Carbon::now()->addDays(10),
        'status' => 'active',
        'auto_renewal' => false,
    ]);

    $transaction = verifiedAppleTransaction(['productId' => $this->package->apple_product_id]);
    fakeAppleVerification($transaction);
    $this->withToken($this->token)
        ->postJson('/api/subscriptions/verify', ['transaction_id' => $transaction->transactionId])
        ->assertOk();

    expect($current->fresh()->status)->toBe('expired')
        ->and(UserSubscription::where('user_id', $this->user->id)->latest('id')->first()->expiry_date->equalTo(Carbon::now()->addDays(40)))->toBeTrue();
});

test('an old unprocessed transaction does not grant a fresh period from today', function () {
    $transaction = verifiedAppleTransaction([
        'productId' => $this->package->apple_product_id,
        'purchaseDate' => Carbon::now()->subDays(60),
    ]);
    fakeAppleVerification($transaction);

    $this->withToken($this->token)
        ->postJson('/api/subscriptions/verify', ['transaction_id' => $transaction->transactionId])
        ->assertOk();

    $subscription = UserSubscription::where('user_id', $this->user->id)->first();
    expect($subscription->status)->toBe('expired')
        ->and($subscription->expiry_date->equalTo(Carbon::now()->subDays(30)))->toBeTrue();
});

test('expired subscriptions are marked expired by the scheduled command', function () {
    $payment = Payment::create([
        'user_id' => $this->user->id,
        'package_id' => $this->package->id,
        'amount_usd' => 4.99,
        'payment_method' => 'apple_iap',
        'apple_transaction_id' => 'expired-transaction-' . uniqid(),
        'payment_status' => 'confirmed',
    ]);
    $subscription = UserSubscription::create([
        'user_id' => $this->user->id,
        'package_id' => $this->package->id,
        'payment_id' => $payment->id,
        'activation_date' => Carbon::now()->subDays(31),
        'expiry_date' => Carbon::now()->subMinute(),
        'status' => 'active',
        'auto_renewal' => false,
    ]);

    $this->artisan('subscriptions:expire')->assertSuccessful();

    expect($subscription->fresh()->status)->toBe('expired');
});

test('the app account token endpoint creates and reuses one stable UUID', function () {
    $this->user->forceFill(['apple_app_account_token' => null])->save();

    $first = $this->withToken($this->token)->getJson('/api/subscriptions/apple-account-token')->assertOk();
    $second = $this->withToken($this->token)->getJson('/api/subscriptions/apple-account-token')->assertOk();

    expect($first->json('data.app_account_token'))->toBe($second->json('data.app_account_token'))
        ->and(Str::isUuid($first->json('data.app_account_token')))->toBeTrue();
});
