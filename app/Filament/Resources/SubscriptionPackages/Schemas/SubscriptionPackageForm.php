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
                    ->label('Subscription Duration (days)')
                    ->required()
                    ->numeric()
                    ->default(30),
                TextInput::make('simulation_limit')
                    ->label('Simulation Exams Included')
                    ->helperText('Leave empty for unlimited simulation exam attempts.')
                    ->numeric()
                    ->minValue(1)
                    ->nullable(),
                TextInput::make('price_usd')
                    ->label('Price (USD)')
                    ->required()
                    ->numeric()
                    ->prefix('$'),
                TextInput::make('original_price_usd')
                    ->label('Original Price Before Offer (USD)')
                    ->helperText('Only set this if there\'s a special offer — leave empty for a regular package with no discount.')
                    ->numeric()
                    ->prefix('$')
                    ->nullable(),
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
