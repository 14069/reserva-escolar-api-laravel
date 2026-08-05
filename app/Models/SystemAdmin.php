<?php

declare(strict_types=1);

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

final class SystemAdmin extends Authenticatable implements FilamentUser
{
    use Notifiable;

    protected $table = 'system_admins';

    protected $fillable = [
        'name',
        'email',
        'password',
        'active',
        'last_login_at',
        'api_token',
        'api_token_expires_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'last_login_at' => 'datetime',
            'api_token_expires_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $panel->getId() === 'admin-geral' && $this->active;
    }
}
