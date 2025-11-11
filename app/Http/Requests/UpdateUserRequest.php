<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = $this->route('id') ?? $this->route('user');

        return [
            'full_name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'password' => ['sometimes', 'required', 'string', 'min:8'],
            'phone_number' => ['nullable', 'string', 'max:30', Rule::unique('users', 'phone_number')->ignore($userId)],
            'avatar_url' => ['nullable', 'url', 'max:2048'],
            'status' => ['nullable', Rule::in(['active', 'locked'])],
            'date_of_birth' => ['nullable', 'date'],
            'gender' => ['nullable', Rule::in(['male', 'female', 'other'])],
            'address' => ['nullable', 'string', 'max:500'],
            'preferred_language' => ['nullable', 'string', 'max:10'],
            'google_id' => ['nullable', 'string', 'max:255'],
            'facebook_id' => ['nullable', 'string', 'max:255'],
            'role' => ['nullable', Rule::in(['admin', 'staff', 'user'])],
        ];
    }
}


