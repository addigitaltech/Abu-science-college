<?php

namespace App\Filament\Resources\SubjectResource\Pages;

use App\Filament\Resources\SubjectResource;
use App\Models\Subject;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class CreateSubject extends CreateRecord
{
    protected static string $resource = SubjectResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $classIds = array_values(array_unique(array_filter((array) ($data['class_ids'] ?? []))));
        unset($data['class_ids'], $data['more_class_ids']);

        // None selected = shared subject; one selected = a normal subject for that class (arm allowed).
        if (count($classIds) <= 1) {
            $data['school_class_id'] = $classIds[0] ?? null;

            return Subject::query()->create($data);
        }

        $created = Subject::createForClasses($data, $classIds);

        if ($created->isEmpty()) {
            throw ValidationException::withMessages(['data.class_ids' => 'Every selected class already has this subject.']);
        }

        $skipped = count($classIds) - $created->count();
        Notification::make()->success()
            ->title($created->count().' class'.($created->count() === 1 ? '' : 'es').' added to '.$created->first()->name)
            ->body($skipped > 0 ? $skipped.' class'.($skipped === 1 ? ' already had' : 'es already had').' this subject and '.($skipped === 1 ? 'was' : 'were').' skipped.' : null)
            ->send();

        return $created->first();
    }

    protected function getCreatedNotification(): ?Notification
    {
        return null;
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
