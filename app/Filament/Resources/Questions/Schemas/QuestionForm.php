<?php

namespace App\Filament\Resources\Questions\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class QuestionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('state_id')
                ->relationship('state', 'name_en')
                ->searchable()
                ->preload()
                ->required(),
                Select::make('category_id')
                ->relationship('category', 'name_en')
                ->searchable()
                ->preload()
                ->required(),
                Textarea::make('question_text_ar')
                    ->required()
                    ->columnSpanFull(),
                Textarea::make('question_text_en')
                    ->default(null)
                    ->columnSpanFull(),
                Select::make('question_type')
                    ->options(['text' => 'Text', 'image' => 'Image'])
                    ->default('text')
                    ->required(),
                FileUpload::make('image_url')
                ->image()
                ->directory('questions')
                ->nullable(),
                Textarea::make('option_a_ar')
                    ->required()
                    ->columnSpanFull(),
                Textarea::make('option_b_ar')
                    ->required()
                    ->columnSpanFull(),
                Textarea::make('option_c_ar')
                    ->required()
                    ->columnSpanFull(),
                Textarea::make('option_d_ar')
                    ->required()
                    ->columnSpanFull(),
                Textarea::make('option_a_en')
                    ->default(null)
                    ->columnSpanFull(),
                Textarea::make('option_b_en')
                    ->default(null)
                    ->columnSpanFull(),
                Textarea::make('option_c_en')
                    ->default(null)
                    ->columnSpanFull(),
                Textarea::make('option_d_en')
                    ->default(null)
                    ->columnSpanFull(),
                Select::make('correct_answer')
                    ->options(['a' => 'A', 'b' => 'B', 'c' => 'C', 'd' => 'D'])
                    ->required(),
                Textarea::make('explanation_ar')
                    ->required()
                    ->columnSpanFull(),
                Select::make('difficulty_level')
                    ->options(['easy' => 'Easy', 'medium' => 'Medium', 'hard' => 'Hard'])
                    ->default('medium')
                    ->required(),
                Select::make('source_type')
                    ->options(['manual' => 'Manual', 'ai' => 'Ai'])
                    ->default('manual')
                    ->required(),
                Select::make('import_status')
                    ->options(['active' => 'Active', 'pending_review' => 'Pending review'])
                    ->default('active')
                    ->required(),
                Toggle::make('is_active')
                ->default(true),
            ]);
    }
}
