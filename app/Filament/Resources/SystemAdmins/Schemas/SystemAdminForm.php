<?php

namespace App\Filament\Resources\SystemAdmins\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Schema;

class SystemAdminForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nome')
                    ->required()
                    ->maxLength(255),

                TextInput::make('email')
                    ->label('E-mail')
                    ->email()
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),

                TextInput::make('password')
                    ->label('Senha')
                    ->password()
                    ->required(static fn ($livewire) => $livewire instanceof CreateRecord)
                    ->dehydrated(static fn (?string $state): bool => filled($state))
                    ->minLength(8),

                Toggle::make('active')
                    ->label('Ativo')
                    ->default(true),
            ]);
    }
}
