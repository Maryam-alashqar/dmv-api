<?php

namespace App\Filament\Resources\SubscriptionPackages\Pages;

use App\Filament\Resources\SubscriptionPackages\SubscriptionPackageResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSubscriptionPackage extends EditRecord
{
    protected static string $resource = SubscriptionPackageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
