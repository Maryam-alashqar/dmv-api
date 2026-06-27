<?php

namespace App\Filament\Resources\SimulationExams\Pages;

use App\Filament\Resources\SimulationExams\SimulationExamResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSimulationExam extends EditRecord
{
    protected static string $resource = SimulationExamResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
