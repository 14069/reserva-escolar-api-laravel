<?php

declare(strict_types=1);

namespace App\Http\Requests\Booking;

use Illuminate\Foundation\Http\FormRequest;

final class StoreBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'school_id' => ['required', 'integer', 'min:1'],
            'resource_id' => ['required', 'integer', 'min:1'],
            'user_id' => ['required', 'integer', 'min:1'],
            'class_group_id' => ['required', 'integer', 'min:1'],
            'subject_id' => ['required', 'integer', 'min:1'],
            'booking_date' => ['required', 'date_format:Y-m-d'],
            'purpose' => ['nullable', 'string', 'max:255'],
            'lesson_ids' => ['required', 'array', 'min:1'],
            'lesson_ids.*' => ['integer', 'min:1'],
            'idempotency_key' => ['nullable', 'string', 'max:100'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $lessonIds = $this->input('lesson_ids', []);
        if (!is_array($lessonIds)) {
            $lessonIds = [];
        }

        $normalizedLessonIds = array_values(array_unique(array_map('intval', $lessonIds)));
        sort($normalizedLessonIds);

        $this->merge([
            'booking_date' => trim((string) $this->input('booking_date')),
            'purpose' => trim((string) $this->input('purpose', '')),
            'idempotency_key' => trim((string) $this->input('idempotency_key', '')),
            'lesson_ids' => $normalizedLessonIds,
        ]);
    }
}
