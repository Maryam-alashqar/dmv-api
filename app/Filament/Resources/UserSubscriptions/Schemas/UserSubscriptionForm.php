<?php

namespace App\Filament\Resources\UserSubscriptions\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class UserSubscriptionForm
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
                Select::make('payment_id')
                   ->relationship('payment', 'id')
                   ->searchable()
                   ->preload()
                   ->nullable(),
                DateTimePicker::make('activation_date')
                    ->required(),
                DateTimePicker::make('expiry_date')
                    ->required(),
                Select::make('status')
                    ->options([
            'active' => 'Active',
            'expired' => 'Expired',
            'cancelled' => 'Cancelled',
            'refunded' => 'Refunded',
        ])
                    ->default('active')
                    ->required(),
                Toggle::make('auto_renewal')
                    ->default(false),
            ]);
    }
}
