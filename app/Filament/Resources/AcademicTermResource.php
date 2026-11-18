<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AcademicTermResource\Pages;
use App\Filament\Resources\AcademicTermResource\RelationManagers;
use App\Models\AcademicTerm;
use Filament\Forms;
use Filament\Forms\Form;
use App\Filament\Resources\AuthorizedResource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class AcademicTermResource extends AuthorizedResource
{
    protected static ?string $requiredPermission = 'manage academic structure';
    protected static ?int $navigationSort = 2;
    protected static ?string $navigationLabel = 'Terms';
    protected static ?string $navigationGroup = 'Classes & subjects';
    protected static ?string $model = AcademicTerm::class;
    protected static ?string $navigationIcon = 'heroicon-o-clock';
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Term details')->schema([
                    Forms\Components\Select::make('academic_session_id')
                        ->label('Academic session')
                        ->relationship('session', 'name')
                        ->searchable()
                        ->preload()
                        ->required(),
                    Forms\Components\TextInput::make('name')
                        ->label('Term name')
                        ->required(),
                    Forms\Components\TextInput::make('sequence')
                        ->label('Term order (1–3)')
                        ->helperText('First, second, or third term.')
                        ->required()
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(3),
                    Forms\Components\Toggle::make('is_current')
                        ->label('Current term')
                        ->helperText('Only one term per academic session can be current.')
                        ->required(),
                    Forms\Components\DatePicker::make('starts_on')->label('Start date'),
                    Forms\Components\DatePicker::make('ends_on')->label('End date'),
                ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('session.name')
                    ->label('Academic session')
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('sequence')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_current')
                    ->boolean(),
                Tables\Columns\TextColumn::make('starts_on')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('ends_on')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('academic_session_id')
                    ->label('Academic session')
                    ->relationship('session', 'name'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAcademicTerms::route('/'),
            'create' => Pages\CreateAcademicTerm::route('/create'),
            'edit' => Pages\EditAcademicTerm::route('/{record}/edit'),
        ];
    }
}
