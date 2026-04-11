<?php

declare(strict_types=1);

namespace App\Http\Requests\Lookup;

use Illuminate\Foundation\Http\FormRequest;

final class ListClassGroupsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'school_id' => ['required', 'integer', 'min:1'],
        ];
    }
}
