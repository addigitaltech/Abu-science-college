<?php

namespace App\Filament\Widgets;

use App\Models\AuditLog;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/** The latest sensitive actions (publishing results, payments, sign-ins) for people allowed to read the audit log. */
class RecentActivity extends TableWidget
{
    protected static ?int $sort = 4;

    protected static ?string $heading = 'Recent activity';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        $user = auth()->user();

        return (bool) ($user && ($user->hasRole('Super Admin') || $user->can('view audit logs')));
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(AuditLog::query()->with('actor')->latest('created_at')->latest('id'))
            ->columns([
                Tables\Columns\TextColumn::make('event')->label('What happened')->badge()->color('gray')->wrap(),
                Tables\Columns\TextColumn::make('actor.name')->label('By')->placeholder('System')->wrap(),
                Tables\Columns\TextColumn::make('created_at')->label('When')->since()->tooltip(fn (AuditLog $record): ?string => $record->created_at?->format('M j, Y H:i')),
            ])
            ->paginated([5, 10])
            ->defaultPaginationPageOption(5)
            ->emptyStateHeading('No activity yet')
            ->emptyStateDescription('Important actions will be listed here as people use the system.');
    }
}
