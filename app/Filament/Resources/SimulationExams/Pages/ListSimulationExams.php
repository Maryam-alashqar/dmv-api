<?php

namespace App\Filament\Resources\SimulationExams\Pages;

use App\Filament\Resources\SimulationExams\SimulationExamResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSimulationExams extends ListRecords
{
    protected static string $resource = SimulationExamResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
