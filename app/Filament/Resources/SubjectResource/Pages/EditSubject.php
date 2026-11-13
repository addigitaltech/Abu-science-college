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
            Actions\DeleteAction::make(),
        ];
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
