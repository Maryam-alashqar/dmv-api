<?php

namespace App\Filament\Resources\SubscriptionPackages;

use App\Filament\Resources\SubscriptionPackages\Pages\CreateSubscriptionPackage;
use App\Filament\Resources\SubscriptionPackages\Pages\EditSubscriptionPackage;
use App\Filament\Resources\SubscriptionPackages\Pages\ListSubscriptionPackages;
use App\Filament\Resources\SubscriptionPackages\Schemas\SubscriptionPackageForm;
use App\Filament\Resources\SubscriptionPackages\Tables\SubscriptionPackagesTable;
use App\Models\SubscriptionPackage;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class SubscriptionPackageResource extends Resource
{
    protected static ?string $model = SubscriptionPackage::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'name_en';

    public static function form(Schema $schema): Schema
    {
        return SubscriptionPackageForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SubscriptionPackagesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSubscriptionPackages::route('/'),
            'create' => CreateSubscriptionPackage::route('/create'),
            'edit' => EditSubscriptionPackage::route('/{record}/edit'),
        ];
    }
}
