<?php

namespace App\Services;

use App\Models\SubscriptionPackage;
use Stripe\StripeClient;

/**
 * Keeps a SubscriptionPackage's price mirrored in Stripe (FR-A15) so admins
 * never have to create Prices by hand in the Stripe Dashboard.
 *
 * Stripe Price objects are immutable once created (the amount can't be
 * edited), so a price change creates a new Price under the package's
 * existing Product and archives the old one, rather than updating in place.
 */
class StripePackageSync
{
    private StripeClient $stripe;

    public function __construct()
    {
        $this->stripe = new StripeClient(config('services.stripe.secret'));
    }

    public function syncOnCreate(SubscriptionPackage $package): void
    {
        $product = $this->stripe->products->create([
            'name' => $package->name_en ?: $package->name_ar,
            'metadata' => ['package_id' => (string) $package->id],
        ]);

        $price = $this->createPrice($product->id, $package);

        $package->updateQuietly([
            'stripe_product_id' => $product->id,
            'stripe_price_id' => $price->id,
        ]);
    }

    public function syncPriceChange(SubscriptionPackage $package): void
    {
        if (! $package->stripe_product_id) {
            $this->syncOnCreate($package);

            return;
        }

        if ($package->stripe_price_id) {
            $this->stripe->prices->update($package->stripe_price_id, ['active' => false]);
        }

        $price = $this->createPrice($package->stripe_product_id, $package);

        $package->updateQuietly(['stripe_price_id' => $price->id]);
    }

    private function createPrice(string $productId, SubscriptionPackage $package)
    {
        return $this->stripe->prices->create([
            'product' => $productId,
            'unit_amount' => (int) round(((float) $package->price_usd) * 100),
            'currency' => 'usd',
            'recurring' => [
                'interval' => 'day',
                'interval_count' => max(1, (int) $package->duration_days),
            ],
        ]);
    }
}
