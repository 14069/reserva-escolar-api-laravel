<?php

namespace App\Filament\Resources\LessonSlots\Pages;

use App\Filament\Resources\LessonSlots\LessonSlotResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListLessonSlots extends ListRecords
{
    protected static string $resource = LessonSlotResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
