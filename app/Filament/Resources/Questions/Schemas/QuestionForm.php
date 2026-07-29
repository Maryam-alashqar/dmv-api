<?php

namespace App\Filament\Resources\Questions\Schemas;

use App\Models\Question;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class QuestionForm
{
    /**
     * The DB column stores the file's relative path on the "public" disk
     * (see Question::diskUrlAttribute()), so FileUpload needs the raw value
     * to locate the file for its preview — the accessor's computed full URL
     * isn't a valid disk path. Shared across the question image and all
     * four option images below.
     */
    private static function hydrateFromRawPath(string $column): \Closure
    {
        return fn (?Question $record) => $record?->getRawOriginal($column);
    }

    private static function optionField(string $letter): array
    {
        return [
            Textarea::make("option_{$letter}_ar")
                ->label("Option {$letter} (Arabic)")
                ->required(fn (Get $get) => blank($get("option_{$letter}_image")))
                ->columnSpanFull(),
            Textarea::make("option_{$letter}_en")
                ->label("Option {$letter} (English)")
                ->default(null)
                ->columnSpanFull(),
            FileUpload::make("option_{$letter}_image")
                ->label("Option {$letter} Image (optional — use instead of, or alongside, text)")
                ->image()
                ->disk('public')
                ->directory('questions/options')
                ->visibility('public')
                ->live()
                ->formatStateUsing(self::hydrateFromRawPath("option_{$letter}_image"))
                ->columnSpanFull(),
        ];
    }

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
                ->label('Question Image')
                ->image()
                ->disk('public')
                ->directory('questions')
                ->visibility('public')
                ->formatStateUsing(self::hydrateFromRawPath('image_url'))
                ->nullable(),

                ...self::optionField('a'),
                ...self::optionField('b'),
                ...self::optionField('c'),
                ...self::optionField('d'),

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
