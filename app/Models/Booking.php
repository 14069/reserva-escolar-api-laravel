<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

final class Booking extends Model
{
    protected $table = 'bookings';

    protected $fillable = [
        'school_id',
        'resource_id',
        'user_id',
        'class_group_id',
        'subject_id',
        'booking_date',
        'purpose',
        'status',
        'cancelled_at',
        'completed_at',
        'completion_feedback',
        'completed_by_user_id',
        'cancelled_by_user_id',
        'idempotency_key',
    ];

    protected function casts(): array
    {
        return [
            'booking_date' => 'date',
            'cancelled_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function resource(): BelongsTo
    {
        return $this->belongsTo(Resource::class);
    }

    public function classGroup(): BelongsTo
    {
        return $this->belongsTo(ClassGroup::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by_user_id');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by_user_id');
    }

    public function lessons(): BelongsToMany
    {
        return $this->belongsToMany(
            LessonSlot::class,
            'booking_lessons',
            'booking_id',
            'lesson_slot_id'
        )->withPivot('id')->orderBy('lesson_number');
    }
}
