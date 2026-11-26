<?php

namespace App\Filament\Resources\SubjectResource\Pages;

use App\Filament\Resources\SubjectResource;
use App\Models\Subject;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditSubject extends EditRecord
{
    protected static string $resource = SubjectResource::class;

    /** @var array<int, int|string> */
    protected array $classIds = [];

    protected int|string|null $armId = null;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['class_ids'] = $this->record->classes()->pluck('school_classes.id')->all();
        if ($this->record->school_class_id) {
            $data['class_ids'][] = (int) $this->record->school_class_id;
        }
        $data['class_ids'] = array_values(array_unique($data['class_ids']));

        $armIds = $this->record->classes()->get()->pluck('pivot.arm_id')->filter()->unique();
        $data['arm_id'] = $armIds->count() === 1 ? $armIds->first() : ($this->record->arm_id ?: null);

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->classIds = (array) ($data['class_ids'] ?? []);
        $this->armId = $data['arm_id'] ?? null;
        unset($data['class_ids'], $data['arm_id']);

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $record->fill($data)->save();
        $record->syncClassAssignments($this->classIds, $this->armId);

        return $record->refresh();
    }

    protected function getHeaderActions(): array
    {
        // Do not expose record deletion here: results and teacher assignments reference subject IDs.
        return [];
    }

    protected function afterSave(): void
    {
        Notification::make()->success()->title('Subject and class assignments updated')->send();
    }
}
