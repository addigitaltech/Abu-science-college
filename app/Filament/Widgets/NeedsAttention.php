<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\AdmissionApplicationResource;
use App\Filament\Resources\ContactMessageResource;
use App\Models\AdmissionApplication;
use App\Models\ContactMessage;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Things waiting for a person: new website messages and admission applications, each linking to its list. */
class NeedsAttention extends StatsOverviewWidget
{
    protected static ?int $sort = 0;

    protected static ?string $pollingInterval = '60s';

    protected int|string|array $columnSpan = 'full';

    protected function getColumns(): int
    {
        return 2;
    }

    public static function canView(): bool
    {
        $user = auth()->user();

        return (bool) ($user && ($user->hasRole('Super Admin') || $user->can('manage website content')));
    }

    protected function getStats(): array
    {
        $messages = ContactMessage::query()->where('status', 'new')->count();
        $applications = AdmissionApplication::query()->where('status', 'submitted')->count();

        return [
            Stat::make('New messages', $messages)
                ->description($messages > 0 ? 'From the website contact form' : 'Nothing waiting')
                ->descriptionIcon($messages > 0 ? 'heroicon-m-envelope' : 'heroicon-m-check-circle')
                ->color($messages > 0 ? 'warning' : 'success')
                ->url(ContactMessageResource::getUrl()),
            Stat::make('New admission applications', $applications)
                ->description($applications > 0 ? 'Waiting to be reviewed' : 'Nothing waiting')
                ->descriptionIcon($applications > 0 ? 'heroicon-m-inbox-arrow-down' : 'heroicon-m-check-circle')
                ->color($applications > 0 ? 'warning' : 'success')
                ->url(AdmissionApplicationResource::getUrl()),
        ];
    }
}
