<?php

namespace App\Filament\Resources\SubjectResource\Pages;

use App\Filament\Resources\SubjectResource;
use App\Models\Subject;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditSubject extends EditRecord
{
    protected static string $resource = SubjectResource::class;

    /** @var array<int, int|string> */
    protected array $moreClassIds = [];

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('removeClassAssignment')
                ->label('Remove class')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->form([
                    \Filament\Forms\Components\Select::make('class_id')
                        ->label('Class assignment to remove')
                        ->options(fn (): array => $this->getClassAssignmentOptions())
                        ->required()
                        ->helperText('This stops the subject being offered in that class. Any existing results or teaching history will be kept.'),
                ])
                ->modalHeading('Remove subject from a class')
                ->modalDescription('Choose the class assignment to remove. Other class assignments remain unchanged; academic history is preserved.')
                ->modalSubmitActionLabel('Remove class')
                ->action(fn (array $data) => $this->removeClassAssignment((int) $data['class_id']))
                ->visible(fn (): bool => $this->getClassAssignmentsQuery(activeOnly: true)->exists()),
            Actions\DeleteAction::make(),
        ];
    }

    /** @return array<int, string> */
    private function getClassAssignmentOptions(): array
    {
        return $this->getClassAssignmentsQuery(activeOnly: true)
            ->get()
            ->mapWithKeys(fn (Subject $assignment): array => [
                $assignment->school_class_id => $assignment->schoolClass?->name
                    ? ($assignment->arm ? $assignment->schoolClass->name.' — '.$assignment->arm->name : $assignment->schoolClass->name)
                    : 'Unknown class',
            ])
            ->all();
    }

    private function getClassAssignmentsQuery(bool $activeOnly = false)
    {
        $query = Subject::query()
            ->with(['schoolClass', 'arm'])
            ->where('name', $this->record->name)
            ->where('arm_id', $this->record->arm_id)
            ->whereNotNull('school_class_id');

        return $activeOnly ? $query->where('status', 'active') : $query;
    }

    private function removeClassAssignment(int $classId): void
    {
        $assignment = $this->getClassAssignmentsQuery(activeOnly: true)
            ->where('school_class_id', $classId)
            ->first();

        if (! $assignment) {
            Notification::make()->danger()
                ->title('Class assignment not found')
                ->send();

            return;
        }

        $isCurrentRecord = $assignment->is($this->record);
        $nextAssignment = $isCurrentRecord
            ? $this->getClassAssignmentsQuery(activeOnly: true)->where('id', '<>', $assignment->getKey())->first()
            : null;

        $hasAcademicHistory = $assignment->results()->exists()
            || $assignment->teacherAssignments()->exists()
            || \Illuminate\Support\Facades\DB::table('assessment_configs')->where('subject_id', $assignment->getKey())->exists();

        if ($hasAcademicHistory) {
            $assignment->update(['status' => 'inactive']);
        } else {
            $assignment->delete();
        }

        Notification::make()->success()
            ->title($hasAcademicHistory ? 'Class removed; academic history retained' : 'Class removed from subject')
            ->send();

        if ($isCurrentRecord) {
            $this->redirect($nextAssignment
                ? SubjectResource::getUrl('edit', ['record' => $nextAssignment])
                : SubjectResource::getUrl('index'));
        }
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->moreClassIds = array_values(array_unique(array_filter((array) ($data['more_class_ids'] ?? []))));
        unset($data['more_class_ids'], $data['class_ids']);

        return $data;
    }

    protected function afterSave(): void
    {
        if ($this->moreClassIds === []) {
            return;
        }

        $created = Subject::createForClasses([
            'name' => $this->record->name,
            'code' => $this->record->code,
            'status' => $this->record->status,
        ], $this->moreClassIds);

        Notification::make()->success()
            ->title($created->count().' more class'.($created->count() === 1 ? '' : 'es').' added to '.$this->record->name)
            ->send();

        $this->moreClassIds = [];
        $this->data['more_class_ids'] = [];
    }
}
