<?php

namespace App\Filament\Resources\States\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class StateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name_en')
                    ->required(),
                TextInput::make('name_ar')
                    ->required(),
                TextInput::make('abbreviation')
                    ->required()
                    ->length(2)
                    ->unique(ignoreRecord: true)
                    ->helperText('2-letter U.S. state code, e.g. CA, NY, TX.')
                    ->formatStateUsing(fn (?string $state) => $state ? strtoupper($state) : $state)
                    ->dehydrateStateUsing(fn (?string $state) => $state ? strtoupper($state) : $state),
                TextInput::make('dmv_question_count')
                    ->required()
                    ->numeric(),
                TextInput::make('dmv_passing_score')
                    ->required()
                    ->numeric(),
                TextInput::make('icon_url')
                    ->url()
                    ->default(null),
                Toggle::make('is_active')
                    ->required(),
            ]);
    }
}
