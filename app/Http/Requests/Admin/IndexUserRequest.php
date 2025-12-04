<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class IndexUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Loosen validation to avoid 422 due to empty strings from URL
        return [
            'search' => 'sometimes|string|max:255',
            'status' => 'sometimes|string',
            'role' => 'sometimes|string',
            'identity_verified' => 'sometimes|string',
            'sort_by' => 'sometimes|string',
            'sort_order' => 'sometimes|string|in:asc,desc',
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Clean empty strings
        $data = $this->all();
        foreach ($data as $key => $value) {
            if ($value === '' || $value === null) {
                unset($data[$key]);
            }
        }
        $this->merge($data);
    }
}

