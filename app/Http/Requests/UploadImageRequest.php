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
            'image' => 'required|file|image|mimes:jpg,jpeg,png,webp|max:5120', // max 5MB
            'type'  => 'required|string|in:vehicles,tenants,routes,locations,schedules,profiles,misc',
        ];
    }
}
