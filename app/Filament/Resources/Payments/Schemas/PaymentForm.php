<?php

namespace App\Filament\Resources\Payments\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class PaymentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_id')
                    ->relationship('user', 'email')
                    ->searchable()
                    ->preload()
                    ->required(),
               Select::make('package_id')
                    ->relationship('package', 'name_en')
                    ->searchable()
                    ->preload()
                    ->required(),
               TextInput::make('amount_usd')
                    ->numeric()
                    ->prefix('$')
                    ->required(),
                Select::make('payment_method')
                    ->options([
            'credit_card' => 'Credit card',
            'debit_card' => 'Debit card',
            'apple_pay' => 'Apple pay',
            'google_pay' => 'Google pay',
            'apple_iap' => 'Apple iap',
        ])
                    ->required(),
                TextInput::make('stripe_payment_intent_id')
                    ->disabled()
                    ->dehydrated(false),
                TextInput::make('apple_transaction_id')
                    ->disabled()
                    ->dehydrated(false),
                Select::make('payment_status')
                    ->options([
            'pending' => 'Pending',
            'confirmed' => 'Confirmed',
            'failed' => 'Failed',
            'refunded' => 'Refunded',
        ])
                    ->default('pending')
                    ->required(),
            ]);
    }
}
