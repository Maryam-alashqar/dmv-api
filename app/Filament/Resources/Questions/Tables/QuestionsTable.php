<?php

namespace App\Filament\Resources\Questions\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class QuestionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('state.name_en')
                ->label('State')
                ->searchable()
                ->sortable(),
                TextColumn::make('category.name_en')
                ->label('Category')
                ->searchable()
                ->sortable(),
                TextColumn::make('question_type')
                    ->badge(),
                ImageColumn::make('image_url')
                ->disk('public')
                ->square(),
                TextColumn::make('correct_answer')
                    ->badge(),
                TextColumn::make('difficulty_level')
                    ->badge(),
                TextColumn::make('source_type')
                    ->badge(),
                TextColumn::make('import_status')
                    ->badge(),
                IconColumn::make('is_active')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
