<?php

namespace App\Filament\Resources\SubscriptionPackages\Pages;

use App\Filament\Resources\SubscriptionPackages\SubscriptionPackageResource;
use App\Services\StripePackageSync;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Throwable;

class EditSubscriptionPackage extends EditRecord
{
    protected static string $resource = SubscriptionPackageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        if (! $this->record->wasChanged('price_usd')) {
            return;
        }

        try {
            app(StripePackageSync::class)->syncPriceChange($this->record);
        } catch (Throwable $exception) {
            report($exception);

            Notification::make()
                ->warning()
                ->title('Saved locally, but Stripe sync failed')
                ->body('The new price was saved, but Stripe could not be updated automatically. Check the Stripe API key and try saving again.')
                ->send();
        }
    }
}
