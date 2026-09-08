<?php

namespace App\Filament\Resources\Payments\Schemas;

use App\Models\User;
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
                    // Email is optional at registration (phone-only accounts are
                    // valid), so a plain 'email' title attribute crashes this
                    // Select for any such user — always fall back to a non-null label.
                    ->getOptionLabelFromRecordUsing(fn (User $record) => $record->email ?: "{$record->full_name} ({$record->phone_number})")
                    ->searchable(['email', 'full_name', 'phone_number'])
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
            // "stripe" is a placeholder the backend sets on a Payment row the
            // moment a Stripe checkout starts, before the webhook resolves it
            // to the real card type — must stay selectable here or opening a
            // still-pending Stripe payment crashes this Select too.
            'stripe' => 'Stripe (pending)',
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
