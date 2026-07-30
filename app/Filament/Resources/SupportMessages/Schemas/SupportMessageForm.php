<?php

namespace App\Filament\Resources\SupportMessages\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class SupportMessageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->disabled()
                    ->dehydrated(false),
                TextInput::make('email')
                    ->disabled()
                    ->dehydrated(false),
                TextInput::make('phone_number')
                    ->disabled()
                    ->dehydrated(false),
                TextInput::make('subject')
                    ->disabled()
                    ->dehydrated(false)
                    ->columnSpanFull(),
                Textarea::make('message')
                    ->disabled()
                    ->dehydrated(false)
                    ->columnSpanFull(),
                Select::make('status')
                    ->options([
                        'open' => 'Open',
                        'resolved' => 'Resolved',
                    ])
                    ->required(),
                Textarea::make('admin_notes')
                    ->label('Admin Notes')
                    ->columnSpanFull(),
            ]);
    }
}
