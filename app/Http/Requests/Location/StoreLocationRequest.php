<?php

namespace App\Http\Requests\Location;

use Illuminate\Foundation\Http\FormRequest;

class StoreLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && ($this->user()->isSuperAdmin());
    }

    public function rules(): array
    {
        $rules = [
            'name' => 'required|string|max:255',
            'city' => 'required|string|max:255',
            'state_id' => 'required|integer|exists:states,id',
            'status_id' => 'nullable|integer|exists:statuses,id',
        ];

        return $rules;
    }
}
