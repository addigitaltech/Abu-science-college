<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SubjectResource\Pages;
use App\Models\Arm;
use App\Models\SchoolClass;
use App\Models\Subject;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class SubjectResource extends AuthorizedResource
{
    protected static ?string $requiredPermission = 'manage academic structure';
    protected static ?int $navigationSort = 5;
    protected static ?string $navigationLabel = 'Subjects';
    protected static ?string $navigationGroup = 'Classes & subjects';
    protected static ?string $model = Subject::class;
    protected static ?string $navigationIcon = 'heroicon-o-book-open';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('code')->required()->maxLength(40),
            Forms\Components\TextInput::make('name')->required()->maxLength(140),
            Forms\Components\Select::make('class_ids')
                ->label('Classes')
                ->multiple()
                ->options(fn () => SchoolClass::query()->orderBy('name')->pluck('name', 'id'))
                ->searchable()->preload()->required(),
            Forms\Components\Select::make('arm_id')
                ->label('Arm (optional)')
                ->options(function (Get $get): array {
                    $classIds = collect($get('class_ids') ?? [])->map(fn ($id) => (int) $id)->filter()->unique()->values();
                    if ($classIds->isEmpty()) {
                        return [];
                    }

                    $commonArmIds = null;
                    foreach ($classIds as $classId) {
                        $classArmIds = Arm::query()->whereHas('classes', fn ($query) => $query->whereKey($classId))->pluck('id');
                        $commonArmIds = $commonArmIds === null ? $classArmIds : $commonArmIds->intersect($classArmIds);
                    }

                    return Arm::query()->whereIn('id', $commonArmIds ?? [])->orderBy('name')->pluck('name', 'id')->all();
                })
                ->searchable()->placeholder('All arms'),
            Forms\Components\Select::make('status')
                ->options(['active' => 'Active', 'inactive' => 'Inactive'])
                ->default('active')->required(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query
                ->whereRaw('subjects.id = (SELECT MIN(subjects_identity.id) FROM subjects AS subjects_identity WHERE LOWER(TRIM(subjects_identity.name)) = LOWER(TRIM(subjects.name)))')
                ->with(['classes', 'classAssignments.schoolClass', 'classAssignments.arm', 'schoolClass', 'arm']))
            ->columns([
                Tables\Columns\TextColumn::make('code')->label('Subject Code')->searchable(),
                Tables\Columns\TextColumn::make('name')->label('Subject Name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('assigned_classes')
                    ->label('Assigned Classes')->wrap()
                    ->getStateUsing(fn (Subject $record): string => static::assignmentLabels($record, includeArms: false)),
                Tables\Columns\TextColumn::make('assigned_arms')
                    ->label('Assigned Arms')->wrap()->placeholder('All arms')
                    ->getStateUsing(fn (Subject $record): string => static::assignmentLabels($record, includeArms: true)),
                Tables\Columns\TextColumn::make('status')->badge()->sortable(),
            ])
            ->actions([Tables\Actions\EditAction::make()])
            ->bulkActions([]);
    }

    private static function assignmentLabels(Subject $record, bool $includeArms): string
    {
        $siblings = Subject::query()
            ->with(['classes', 'classAssignments.schoolClass', 'classAssignments.arm', 'schoolClass', 'arm'])
            ->whereRaw('LOWER(TRIM(name)) = ?', [Str::lower(trim($record->name))])
            ->get();

        $labels = collect();
        foreach ($siblings as $subject) {
            foreach ($subject->classAssignments as $assignment) {
                $className = $assignment->schoolClass?->name;
                if (! $className) {
                    continue;
                }
                $armName = $assignment->arm?->name;
                $labels->push($includeArms ? ($armName ?: 'All arms') : ($className.($armName ? ' ('.$armName.')' : '')));
            }

            foreach ($subject->classes as $class) {
                $armName = $class->pivot->arm_id ? Arm::query()->whereKey($class->pivot->arm_id)->value('name') : null;
                $labels->push($includeArms ? ($armName ?: 'All arms') : ($class->name.($armName ? ' ('.$armName.')' : '')));
            }

            if ($subject->school_class_id && $subject->schoolClass) {
                $labels->push($includeArms ? ($subject->arm?->name ?: 'All arms') : ($subject->schoolClass->name.($subject->arm ? ' ('.$subject->arm->name.')' : '')));
            }
        }

        return $labels->unique()->sort()->implode(', ');
    }

    public static function getRelations(): array { return []; }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSubjects::route('/'),
            'create' => Pages\CreateSubject::route('/create'),
            'edit' => Pages\EditSubject::route('/{record}/edit'),
        ];
    }
}
