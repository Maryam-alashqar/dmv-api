<?php

namespace App\Filament\Resources\SubscriptionPackages\Pages;

use App\Filament\Resources\SubscriptionPackages\SubscriptionPackageResource;
use App\Services\StripePackageSync;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Throwable;

class CreateSubscriptionPackage extends CreateRecord
{
    protected static string $resource = SubscriptionPackageResource::class;

    protected function afterCreate(): void
    {
        try {
            app(StripePackageSync::class)->syncOnCreate($this->record);
        } catch (Throwable $exception) {
            report($exception);

            Notification::make()
                ->warning()
                ->title('Saved locally, but Stripe sync failed')
                ->body('The package was created, but its Stripe Product/Price could not be created automatically. Check the Stripe API key and try editing the package again.')
                ->send();
        }
    }
}
