<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class IndexReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'property_id' => 'sometimes|integer|exists:properties,id',
            'room_id' => 'sometimes|integer|exists:rooms,id',
            'status' => 'sometimes|string|in:pending,approved,rejected',
            'rating' => 'sometimes|integer|min:1|max:5',
            'verified_only' => 'sometimes|boolean',
            'date_from' => 'sometimes|date',
            'date_to' => 'sometimes|date|after_or_equal:date_from',
            'search' => 'sometimes|string|max:255',
            'sort_by' => 'sometimes|string|in:reviewed_at,rating,created_at',
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

