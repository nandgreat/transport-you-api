<?php

namespace App\Http\Requests\SeatArrangementType;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSeatArrangementTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->isSuperAdmin();
    }

    public function rules(): array
    {
        return [
            'name' => [
                'sometimes', 'required', 'string', 'max:150',
                Rule::unique('seat_arrangement_types', 'name')->ignore($this->route('seat_arrangement_type')),
            ],
            'number_of_seats'   => 'sometimes|required|integer|min:1|max:500',
            'arrangement_image' => 'sometimes|nullable|string|max:2048',
            'description'       => 'sometimes|nullable|string|max:1000',
            'status'            => 'sometimes|required|in:active,inactive',
        ];
    }
}
