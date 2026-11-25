<?php

namespace App\Filament\Widgets;

use App\Models\Result;
use Filament\Widgets\ChartWidget;

/** Doughnut of result lines by status, so administrators see at a glance how much is still waiting to be published. */
class ResultsStatusChart extends ChartWidget
{
    protected static ?string $heading = 'Results by status';

    protected static ?string $description = 'All result lines in the system';

    protected static ?int $sort = 2;

    protected static ?string $maxHeight = '260px';

    protected int|string|array $columnSpan = 1;

    public static function canView(): bool
    {
        $user = auth()->user();

        return (bool) ($user && ($user->hasRole('Super Admin') || $user->can('manage results')));
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getData(): array
    {
        $counts = Result::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return [
            'datasets' => [[
                'data' => [(int) ($counts['published'] ?? 0), (int) ($counts['pending'] ?? 0), (int) ($counts['draft'] ?? 0)],
                'backgroundColor' => ['#0d6b35', '#e2a52c', '#94a3b8'],
                'borderWidth' => 0,
            ]],
            'labels' => ['Published', 'Pending', 'Draft'],
        ];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => ['legend' => ['position' => 'bottom']],
            'cutout' => '66%',
            'scales' => ['x' => ['display' => false], 'y' => ['display' => false]],
        ];
    }
}
