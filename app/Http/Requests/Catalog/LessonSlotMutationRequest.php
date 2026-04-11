<?php

declare(strict_types=1);

namespace App\Http\Requests\Catalog;

use Illuminate\Foundation\Http\FormRequest;

final class LessonSlotMutationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'school_id' => ['required', 'integer', 'min:1'],
            'user_id' => ['required', 'integer', 'min:1'],
            'lesson_slot_id' => ['sometimes', 'required', 'integer', 'min:1'],
            'lesson_number' => ['required', 'integer', 'min:1'],
            'label' => ['required', 'string', 'max:30'],
            'start_time' => ['nullable', 'date_format:H:i:s'],
            'end_time' => ['nullable', 'date_format:H:i:s'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $start = trim((string) $this->input('start_time', ''));
        $end = trim((string) $this->input('end_time', ''));
        $this->merge([
            'label' => trim((string) $this->input('label', '')),
            'start_time' => $start === '' ? null : $start,
            'end_time' => $end === '' ? null : $end,
        ]);
    }
}
