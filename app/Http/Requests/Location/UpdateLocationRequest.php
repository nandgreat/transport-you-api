<?php

namespace App\Http\Requests\Location;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && ($this->user()->isSuperAdmin());
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|required|string|max:255',
            'city' => 'sometimes|required|string|max:255',
            'state_id' => 'sometimes|required|integer|exists:states,id',
            'status' => 'sometimes|nullable|in:active,inactive',
        ];
    }
}
