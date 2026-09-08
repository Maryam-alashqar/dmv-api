<?php

namespace App\Filament\Resources\UserSubscriptions\Schemas;

use App\Models\User;
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
