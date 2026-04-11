<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class School extends Model
{
    protected $table = 'schools';

    protected $fillable = [
        'school_name',
        'school_code',
        'password',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
