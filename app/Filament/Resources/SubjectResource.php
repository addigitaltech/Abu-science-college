<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SubjectResource\Pages;
use App\Models\Subject;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

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
        $activeClasses = fn () => \App\Models\SchoolClass::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id');

        return $form->schema([
            Forms\Components\TextInput::make('code')->maxLength(40)->unique(ignoreRecord: true)
                ->helperText('When a subject goes to several classes, the class name is added to the code, for example MTH-JSS1.'),
            Forms\Components\TextInput::make('name')->required()->maxLength(140),

            // Creating: pick one, several or all classes. One subject record is made for each class.
            Forms\Components\Select::make('class_ids')
                ->label('Classes (leave blank for a shared subject)')
                ->multiple()
                ->options($activeClasses)
                ->searchable()
                ->preload()
                ->live()
                ->afterStateUpdated(fn (Set $set) => $set('arm_id', null))
                ->visible(fn (string $operation) => $operation === 'create')
                ->helperText('Choose one class, a few classes, or use Select all.')
                ->suffixAction(
                    Forms\Components\Actions\Action::make('selectAllClasses')
                        ->label('Select all')
                        ->icon('heroicon-o-check-circle')
                        ->action(fn (Set $set) => $set('class_ids', \App\Models\SchoolClass::query()->where('is_active', true)->pluck('id')->map(fn ($id) => (string) $id)->all())),
                ),

            // Editing: change this record's class, and optionally copy the subject into more classes.
            Forms\Components\Select::make('school_class_id')->label('Class (leave blank for shared subject)')
                ->relationship('schoolClass', 'name', modifyQueryUsing: fn ($query) => $query->where('is_active', true))
                ->searchable()->preload()->live()
                ->afterStateUpdated(fn (Set $set) => $set('arm_id', null))
                ->visible(fn (string $operation) => $operation === 'edit'),

            Forms\Components\Placeholder::make('current_class_assignments')
                ->label('Currently added classes')
                ->content(function (?Subject $record): string|HtmlString {
                    if (! $record) {
                        return 'Save the subject first to manage its class assignments.';
                    }

                    $assignments = Subject::query()
                        ->with(['schoolClass', 'arm'])
                        ->where('name', $record->name)
                        ->where('arm_id', $record->arm_id)
                        ->whereNotNull('school_class_id')
                        ->orderBy('school_class_id')
                        ->get();

                    if ($assignments->isEmpty()) {
                        return 'No class-specific assignments. This subject is shared.';
                    }

                    $items = $assignments->map(function (Subject $assignment): string {
                        $label = $assignment->schoolClass?->name ?? 'Unknown class';
                        if ($assignment->arm) {
                            $label .= ' — '.$assignment->arm->name;
                        }
                        if ($assignment->status !== 'active') {
                            $label .= ' (inactive; history retained)';
                        }

                        return '<li>'.e($label).'</li>';
                    })->implode('');

                    return new HtmlString('<ul class="list-disc space-y-1 pl-5">'.$items.'</ul>');
                })
                ->visible(fn (string $operation) => $operation === 'edit'),

            Forms\Components\Select::make('arm_id')->label('Arm (optional)')
                ->options(function (Get $get) {
                    $classId = $get('school_class_id') ?: (($get('class_ids') ?? [])[0] ?? null);
                    if (! $classId) {
                        return [];
                    }
                    return \App\Models\Arm::query()->whereHas('classes', fn ($query) => $query->whereKey($classId))->orderBy('name')->pluck('name', 'id');
                })
                ->searchable()->placeholder('All arms')
                ->visible(fn (Get $get, string $operation) => $operation === 'edit' || count($get('class_ids') ?? []) === 1),

            Forms\Components\Select::make('more_class_ids')
                ->label('Also add this subject to more classes')
                ->multiple()
                ->options(function (?\App\Models\Subject $record) use ($activeClasses) {
                    $options = $activeClasses();
                    if (! $record) {
                        return $options;
                    }
                    $taken = \App\Models\Subject::query()->where('name', $record->name)->whereNull('arm_id')->pluck('school_class_id')->all();
                    return $options->except(array_merge($taken, [$record->school_class_id]));
                })
                ->searchable()->preload()
                ->visible(fn (string $operation) => $operation === 'edit')
                ->helperText('Classes that already have this subject are not listed.'),

            Forms\Components\Select::make('status')->options(['active' => 'Active', 'inactive' => 'Inactive'])->default('active')->required(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->modifyQueryUsing(fn ($query) => $query->with(['schoolClass', 'arm']))->columns([
            Tables\Columns\TextColumn::make('code')->searchable(),
            Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('schoolClass.name')->label('Class')->sortable(),
            Tables\Columns\TextColumn::make('arm.name')->label('Arm'),
            Tables\Columns\TextColumn::make('status')->badge()->sortable(),
        ])->filters([Tables\Filters\SelectFilter::make('school_class_id')->label('Class')->relationship('schoolClass', 'name')])->actions([Tables\Actions\EditAction::make()])->bulkActions([]);
    }

    public static function getRelations(): array { return []; }
    public static function getPages(): array { return ['index' => Pages\ListSubjects::route('/'), 'create' => Pages\CreateSubject::route('/create'), 'edit' => Pages\EditSubject::route('/{record}/edit')]; }
}
