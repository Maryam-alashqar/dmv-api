<?php

namespace App\Filament\Widgets;

use App\Models\User;
use App\Models\Question;
use App\Models\SimulationExam;
use App\Models\Payment;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class DmvStatsOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Users', User::count())
                ->description('Registered users')
                ->descriptionIcon('heroicon-m-users')
                ->color('primary'),

            Stat::make('Questions', Question::count())
                ->description('Available questions')
                ->descriptionIcon('heroicon-m-question-mark-circle')
                ->color('success'),

            Stat::make('Simulation Exams', SimulationExam::count())
                ->description('Published exams')
                ->descriptionIcon('heroicon-m-academic-cap')
                ->color('warning'),

            Stat::make('Payments', Payment::count())
                ->description('Completed payments')
                ->descriptionIcon('heroicon-m-credit-card')
                ->color('danger'),
        ];
    }
}
