<?php

declare(strict_types=1);

namespace App\Http\Requests\Catalog;

use Illuminate\Foundation\Http\FormRequest;

final class ResourceMutationRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'school_id' => ['required', 'integer', 'min:1'],
            'user_id' => ['required', 'integer', 'min:1'],
            'resource_id' => ['sometimes', 'required', 'integer', 'min:1'],
            'category_id' => ['required', 'integer', 'min:1'],
            'name' => ['required', 'string', 'max:150'],
        ];
    }
    protected function prepareForValidation(): void
    {
        $this->merge(['name' => trim((string) $this->input('name', ''))]);
    }
}
