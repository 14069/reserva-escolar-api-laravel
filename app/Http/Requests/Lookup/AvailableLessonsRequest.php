<?php

declare(strict_types=1);

namespace App\Http\Requests\Lookup;

use Illuminate\Foundation\Http\FormRequest;

final class AvailableLessonsRequest extends FormRequest
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
            'booking_date' => ['required', 'date_format:Y-m-d'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'booking_date' => trim((string) $this->input('booking_date')),
        ]);
    }
}
