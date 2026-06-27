<?php

namespace App\Filament\Resources\SimulationExams;

use App\Filament\Resources\SimulationExams\Pages\CreateSimulationExam;
use App\Filament\Resources\SimulationExams\Pages\EditSimulationExam;
use App\Filament\Resources\SimulationExams\Pages\ListSimulationExams;
use App\Filament\Resources\SimulationExams\Schemas\SimulationExamForm;
use App\Filament\Resources\SimulationExams\Tables\SimulationExamsTable;
use App\Models\SimulationExam;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class SimulationExamResource extends Resource
{
    protected static ?string $model = SimulationExam::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'title_ar';

    public static function form(Schema $schema): Schema
    {
        return SimulationExamForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SimulationExamsTable::configure($table);
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
            'index' => ListSimulationExams::route('/'),
            'create' => CreateSimulationExam::route('/create'),
            'edit' => EditSimulationExam::route('/{record}/edit'),
        ];
    }
}
