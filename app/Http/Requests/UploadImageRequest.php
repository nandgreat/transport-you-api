<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'image' => 'required|file|image|mimes:jpg,jpeg,png,webp|max:4096',
            'type' => 'required|string|in:vehicles,tenants,routes,locations,schedules,profiles,misc',
            'model_id' => 'nullable|integer|min:1',
        ];
    }

    public function messages(): array
    {
        return [
            'image.max' => 'Image must not exceed 4 MB.',
            'type.in' => 'Type must be one of: vehicles, tenants, routes, locations, schedules, profiles, misc.',
        ];
    }
}
