<?php

namespace App\Http\Requests\SeatArrangementType;

use Illuminate\Foundation\Http\FormRequest;

class StoreSeatArrangementTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->isSuperAdmin();
    }

    public function rules(): array
    {
        return [
            'name'              => 'required|string|max:150|unique:seat_arrangement_types,name',
            'number_of_seats'   => 'required|integer|min:1|max:500',
            'arrangement_image' => 'nullable|string|max:2048',
            'description'       => 'nullable|string|max:1000',
            'status'            => 'nullable|in:active,inactive',
        ];
    }
}
