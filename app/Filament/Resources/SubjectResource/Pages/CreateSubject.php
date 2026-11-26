<?php

namespace App\Filament\Resources\SubjectResource\Pages;

use App\Filament\Resources\SubjectResource;
use App\Models\Subject;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateSubject extends CreateRecord
{
    protected static string $resource = SubjectResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $classIds = (array) ($data['class_ids'] ?? []);
        unset($data['class_ids']);

        $subject = Subject::createForClasses($data, $classIds)->firstOrFail();
        Notification::make()->success()->title('Subject saved with its class assignments')->send();

        return $subject;
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
