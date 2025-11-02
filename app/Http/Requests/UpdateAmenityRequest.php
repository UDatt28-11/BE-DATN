<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAmenityRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'property_id' => 'sometimes|required|exists:properties,id',
            'name' => 'sometimes|required|string|max:255',
            'icon_file' => 'nullable|file|mimes:jpeg,png,jpg,gif,svg|max:1024',
            'type' => 'sometimes|required|in:basic,advanced,safety',
        ];
    }
}
