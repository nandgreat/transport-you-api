<?php

namespace App\Http\Requests\VehicleType;

use Illuminate\Foundation\Http\FormRequest;

class UpdateVehicleTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->isSuperAdmin();
    }

    public function rules(): array
    {
        return [
            'vehicle_name'        => 'sometimes|required|string|max:150',
            'image_url'           => 'sometimes|nullable|string|max:2048',
            'capacity'            => 'sometimes|required|integer|min:1|max:500',
            'vehicle_category_id' => 'sometimes|required|integer|exists:vehicle_categories,id',
            'status'              => 'sometimes|required|in:active,inactive',
            'year'                => 'sometimes|required|integer|min:1900|max:' . (date('Y') + 1),
        ];
    }
}
