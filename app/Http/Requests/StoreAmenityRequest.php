<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;

class StoreAmenityRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'property_id' => 'required|exists:properties,id',
            'name' => 'required|string|max:255',
            'icon_file' => 'nullable|file|mimes:jpeg,png,jpg,gif,svg|max:1024',
            'type' => 'required|in:basic,advanced,safety',
        ];
    }
}
