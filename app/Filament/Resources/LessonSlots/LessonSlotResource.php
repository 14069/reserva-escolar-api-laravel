<?php

namespace App\Filament\Resources\LessonSlots;

use App\Filament\Resources\LessonSlots\Pages\CreateLessonSlot;
use App\Filament\Resources\LessonSlots\Pages\EditLessonSlot;
use App\Filament\Resources\LessonSlots\Pages\ListLessonSlots;
use App\Filament\Resources\LessonSlots\Schemas\LessonSlotForm;
use App\Filament\Resources\LessonSlots\Tables\LessonSlotsTable;
use App\Models\LessonSlot;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class LessonSlotResource extends Resource
{
    protected static ?string $model = LessonSlot::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return LessonSlotForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LessonSlotsTable::configure($table);
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
            'index' => ListLessonSlots::route('/'),
            'create' => CreateLessonSlot::route('/create'),
            'edit' => EditLessonSlot::route('/{record}/edit'),
        ];
    }
}
