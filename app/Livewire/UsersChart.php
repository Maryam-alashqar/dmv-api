<?php

namespace App\Livewire;

use App\Models\User;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;

class UsersChart extends ChartWidget
{
    protected ?string $heading = 'New Users – Last 30 Days';

    protected ?string $description = 'Daily number of newly registered users';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected function getData(): array
    {
        $startDate = now()->subDays(29)->startOfDay();
        $endDate = now()->endOfDay();

        $usersByDate = User::query()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('DATE(created_at) as date, COUNT(*) as total')
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('total', 'date');

        $labels = [];
        $data = [];

        for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
            $dateKey = $date->format('Y-m-d');

            $labels[] = $date->format('M d');
            $data[] = (int) ($usersByDate[$dateKey] ?? 0);
        }

        return [
            'datasets' => [
                [
                    'label' => 'New users',
                    'data' => $data,
                    'fill' => true,
                    'tension' => 0.35,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'responsive' => true,
            'maintainAspectRatio' => false,
            'plugins' => [
                'legend' => [
                    'display' => false,
                ],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => [
                        'precision' => 0,
                    ],
                ],
            ],
        ];
    }
}
