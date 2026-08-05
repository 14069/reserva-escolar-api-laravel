<?php

namespace App\Filament\Resources\LessonSlots\Pages;

use App\Filament\Resources\LessonSlots\LessonSlotResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditLessonSlot extends EditRecord
{
    protected static string $resource = LessonSlotResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
