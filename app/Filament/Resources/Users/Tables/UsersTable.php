<?php

namespace App\Filament\Resources\Users\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('full_name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label('Email address')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('phone_number')
                    ->searchable(),
                TextColumn::make('profile_photo_url')
                    ->searchable(),
                TextColumn::make('preferred_language')
                    ->badge(),
                TextColumn::make('selectedState.name_en')
                ->label('State')
                ->searchable()
                ->sortable(),
                TextColumn::make('selected_state_id')
                    ->numeric()
                    ->sortable(),
                IconColumn::make('verification_status')
                    ->boolean(),
                TextColumn::make('account_status')
                    ->badge(),
                TextColumn::make('free_questions_used')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('stripe_customer_id')
                    ->searchable(),
                TextColumn::make('apple_uid')
                    ->searchable(),
                TextColumn::make('google_uid')
                    ->searchable(),
                TextColumn::make('role')
                    ->badge(),
                TextColumn::make('last_login')
                    ->dateTime()
                    ->sortable(),
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
