<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class ResourceCategory extends Model
{
    protected $table = 'resource_categories';

    protected $fillable = [
        'name',
    ];

    public function resources(): HasMany
    {
        return $this->hasMany(Resource::class, 'category_id');
    }
}
