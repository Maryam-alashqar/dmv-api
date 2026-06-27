<?php

namespace App\Filament\Resources\SimulationExams\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;

class SimulationExamForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title_ar')
                    ->required(),
                Select::make('state_id')
                ->relationship('state', 'name_en')
                ->searchable()
                ->preload()
                ->required(),
                TextInput::make('total_questions')
                    ->required()
                    ->numeric(),
                TextInput::make('passing_score')
                    ->required()
                    ->numeric(),
                Toggle::make('is_published')
                    ->required(),
            ]);
    }
}
