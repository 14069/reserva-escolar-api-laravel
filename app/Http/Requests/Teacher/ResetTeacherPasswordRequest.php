<?php

declare(strict_types=1);

namespace App\Http\Requests\Teacher;

use Illuminate\Foundation\Http\FormRequest;

final class ResetTeacherPasswordRequest extends FormRequest
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
            'teacher_id' => ['required', 'integer', 'min:1'],
            'new_password' => ['required', 'string', 'min:6'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'new_password' => trim((string) $this->input('new_password', '')),
        ]);
    }
}
