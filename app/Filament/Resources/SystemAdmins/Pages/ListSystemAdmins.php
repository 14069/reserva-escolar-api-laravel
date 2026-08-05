<?php

namespace App\Filament\Resources\SystemAdmins\Pages;

use App\Filament\Resources\SystemAdmins\SystemAdminResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSystemAdmins extends ListRecords
{
    protected static string $resource = SystemAdminResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
