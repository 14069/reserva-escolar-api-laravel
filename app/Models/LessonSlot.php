<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

final class LessonSlot extends Model
{
    protected $table = 'lesson_slots';

    protected $fillable = [
        'school_id',
        'lesson_number',
        'label',
        'start_time',
        'end_time',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'integer',
            'lesson_number' => 'integer',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function bookings(): BelongsToMany
    {
        return $this->belongsToMany(
            Booking::class,
            'booking_lessons',
            'lesson_slot_id',
            'booking_id'
        );
    }
}
