<?php

declare(strict_types=1);

namespace App\Http\Requests\Lookup;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ListResourcesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'school_id' => ['required', 'integer', 'min:1'],
            'only_active' => ['nullable', Rule::in(['0', '1'])],
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'category' => ['nullable', 'string', 'max:80'],
            'sort' => ['nullable', Rule::in(['name_asc', 'name_desc', 'category_asc', 'status'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'page_size' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'only_active' => (string) $this->input('only_active', '1'),
            'search' => trim((string) $this->input('search', '')),
            'status' => trim((string) $this->input('status', '')),
            'category' => trim((string) $this->input('category', '')),
            'sort' => trim((string) $this->input('sort', 'name_asc')),
        ]);
    }
}
