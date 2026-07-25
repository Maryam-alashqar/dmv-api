<?php

namespace App\Filament\Resources\SubscriptionPackages\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class SubscriptionPackageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name_ar')
                    ->required(),
                TextInput::make('name_en')
                    ->required(),
                TextInput::make('duration_days')
                    ->required()
                    ->numeric()
                    ->default(30),
                TextInput::make('price_usd')
                    ->required()
                    ->numeric(),
                TextInput::make('stripe_price_id')
                    ->helperText('Managed automatically from the price above — created/updated in Stripe on save.')
                    ->disabled()
                    ->dehydrated(false),
                TextInput::make('stripe_product_id')
                    ->disabled()
                    ->dehydrated(false),
                TextInput::make('apple_product_id')
                    ->default(null),
                Textarea::make('features')->rows(5)
                    ->helperText('Example: {"simulation_access": true, "analytics_access": true}')
                    ->columnSpanFull(),
                Toggle::make('is_active')
                    ->default(true),
            ]);
    }
}
