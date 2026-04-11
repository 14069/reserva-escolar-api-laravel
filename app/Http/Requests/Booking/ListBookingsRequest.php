<?php

declare(strict_types=1);

namespace App\Http\Requests\Booking;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ListBookingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'school_id' => ['required', 'integer', 'min:1'],
            'booking_date' => ['nullable', 'date_format:Y-m-d'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d'],
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(['scheduled', 'completed', 'cancelled'])],
            'teacher' => ['nullable', 'string', 'max:120'],
            'resource' => ['nullable', 'string', 'max:150'],
            'class_group' => ['nullable', 'string', 'max:20'],
            'sort' => ['nullable', Rule::in(['date_desc', 'date_asc', 'teacher_asc', 'resource_asc'])],
            'summary_mode' => ['nullable', Rule::in(['full', 'simple'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'page_size' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
