<?php

namespace App\Filament\Resources\Categories\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class CategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('state_id')
                ->relationship('state', 'name_en')
                ->required(),
                TextInput::make('name_ar')
                    ->required(),
                TextInput::make('name_en')
                    ->required(),
                Select::make('category_type')
                    ->options(['general' => 'General', 'signs' => 'Signs'])
                    ->default('general')
                    ->required(),
                TextInput::make('sequence_order')
                    ->required()
                    ->numeric()
                    ->default(0),
            ]);
    }
}
