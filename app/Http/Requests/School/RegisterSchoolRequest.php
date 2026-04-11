<?php

declare(strict_types=1);

namespace App\Http\Requests\School;

use Illuminate\Foundation\Http\FormRequest;

final class RegisterSchoolRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'school_name' => ['required', 'string', 'max:150'],
            'school_code' => ['required', 'string', 'max:50'],
            'school_password' => ['required', 'string', 'min:6'],
            'technician_name' => ['required', 'string', 'max:120'],
            'technician_email' => ['required', 'email', 'max:120'],
            'technician_password' => ['required', 'string', 'min:6'],
            'chromebooks_count' => ['nullable', 'integer', 'min:0'],
            'audiovisual_count' => ['nullable', 'integer', 'min:0'],
            'spaces_count' => ['nullable', 'integer', 'min:0'],
            'class_groups' => ['nullable', 'array'],
            'class_groups.*' => ['string', 'max:20'],
            'subjects' => ['nullable', 'array'],
            'subjects.*' => ['string', 'max:100'],
            'lesson_count' => ['required', 'integer', 'min:1'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $normalizeArray = static fn (mixed $items): array => is_array($items)
            ? array_values(array_filter(array_map(
                static fn ($item) => trim((string) $item),
                $items
            ), static fn ($item) => $item !== ''))
            : [];

        $this->merge([
            'school_name' => trim((string) $this->input('school_name', '')),
            'school_code' => trim((string) $this->input('school_code', '')),
            'school_password' => trim((string) $this->input('school_password', '')),
            'technician_name' => trim((string) $this->input('technician_name', '')),
            'technician_email' => trim((string) $this->input('technician_email', '')),
            'technician_password' => trim((string) $this->input('technician_password', '')),
            'class_groups' => $normalizeArray($this->input('class_groups', [])),
            'subjects' => $normalizeArray($this->input('subjects', [])),
        ]);
    }
}
