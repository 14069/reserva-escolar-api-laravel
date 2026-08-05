<?php

namespace App\Filament\Resources\SystemAdmins;

use App\Filament\Resources\SystemAdmins\Pages\CreateSystemAdmin;
use App\Filament\Resources\SystemAdmins\Pages\EditSystemAdmin;
use App\Filament\Resources\SystemAdmins\Pages\ListSystemAdmins;
use App\Filament\Resources\SystemAdmins\Schemas\SystemAdminForm;
use App\Filament\Resources\SystemAdmins\Tables\SystemAdminsTable;
use App\Models\SystemAdmin;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class SystemAdminResource extends Resource
{
    protected static ?string $model = SystemAdmin::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?string $navigationLabel = 'Administradores';

    protected static \UnitEnum|string|null $navigationGroup = 'Admin Geral';

    protected static ?int $navigationSort = 5;

    protected static ?string $modelLabel = 'administrador do sistema';

    protected static ?string $pluralModelLabel = 'administradores do sistema';

    public static function form(Schema $schema): Schema
    {
        return SystemAdminForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SystemAdminsTable::configure($table);
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
            'index' => ListSystemAdmins::route('/'),
            'create' => CreateSystemAdmin::route('/create'),
            'edit' => EditSystemAdmin::route('/{record}/edit'),
        ];
    }
}
