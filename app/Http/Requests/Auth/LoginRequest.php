<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

final class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'school_code' => ['required', 'string', 'max:50'],
            'email' => ['required', 'email', 'max:120'],
            'password' => ['required', 'string'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'school_code' => trim((string) $this->input('school_code')),
            'email' => trim((string) $this->input('email')),
            'password' => trim((string) $this->input('password')),
        ]);
    }
}
