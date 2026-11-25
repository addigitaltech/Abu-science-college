<?php

namespace App\Filament\Widgets;

use App\Models\SchoolClass;
use Filament\Widgets\ChartWidget;

/** Bar chart of active students in each active class. */
class StudentsPerClassChart extends ChartWidget
{
    protected static ?string $heading = 'Students per class';

    protected static ?string $description = 'Active students only';

    protected static ?int $sort = 2;

    protected static ?string $maxHeight = '260px';

    protected int|string|array $columnSpan = 1;

    public static function canView(): bool
    {
        $user = auth()->user();

        return (bool) ($user && ($user->hasRole('Super Admin') || $user->can('manage students') || $user->can('manage results')));
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $classes = SchoolClass::query()
            ->where('is_active', true)
            ->withCount(['students' => fn ($query) => $query->where('status', 'active')])
            ->orderBy('name')
            ->get();

        return [
            'datasets' => [[
                'label' => 'Students',
                'data' => $classes->pluck('students_count')->map(fn ($count) => (int) $count)->all(),
                'backgroundColor' => '#102f80',
                'borderRadius' => 6,
                'maxBarThickness' => 36,
            ]],
            'labels' => $classes->pluck('name')->all(),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => ['legend' => ['display' => false]],
            'scales' => ['y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]]],
        ];
    }
}
