<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'phone_number' => ['nullable', 'string', 'max:30', 'unique:users,phone_number'],
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


