<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\FileUpload;
use Illuminate\Support\Facades\Hash;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('full_name')
                    ->default(null),
                TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->default(null),
                TextInput::make('phone_number')
                    ->tel()
                    ->default(null),
                TextInput::make('password')
                    ->password()
                    ->dehydrateStateUsing(fn ($state) => filled($state) ? Hash::make($state) : null)
                    ->dehydrated(fn ($state) => filled($state))
                    ->required(fn (string $operation): bool => $operation === 'create'),
                FileUpload::make('profile_photo')
                    ->image()
                    ->directory('profiles'),
                Select::make('preferred_language')
                    ->options(['ar' => 'Ar', 'en' => 'En'])
                    ->default('ar')
                    ->required(),
                Select::make('selected_state_id')
                    ->relationship('selectedState', 'name_en')
                    ->searchable()
                    ->preload()
                    ->label('Selected State'),
                Toggle::make('verification_status')
                    ->default(false),
                Select::make('account_status')
                    ->options(['active' => 'Active', 'disabled' => 'Disabled'])
                    ->default('active')
                    ->required(),
                TextInput::make('free_questions_used')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('stripe_customer_id')
                    ->disabled()
                    ->dehydrated(false),
                TextInput::make('apple_uid')
                    ->disabled()
                    ->dehydrated(false),
                TextInput::make('google_uid')
                    ->disabled()
                    ->dehydrated(false),
                Select::make('role')
                    ->options(['user' => 'User', 'admin' => 'Admin'])
                    ->default('user')
                    ->required(),
                DateTimePicker::make('last_login')
                    ->disabled(),
            ]);
    }
}
