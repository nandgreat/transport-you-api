<?php

namespace App\Http\Requests\VehicleType;

use Illuminate\Foundation\Http\FormRequest;

class StoreVehicleTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->isSuperAdmin();
    }

    public function rules(): array
    {
        return [
            'vehicle_name'        => 'required|string|max:150',
            'image_url'           => 'nullable|string|max:2048',
            'vehicle_category_id' => 'required|integer|exists:vehicle_categories,id',
            'seat_arrangement_type_ids'   => 'nullable|array',
            'seat_arrangement_type_ids.*' => 'integer|distinct|exists:seat_arrangement_types,id',
            'default_seat_arrangement_type_id' => 'nullable|integer|exists:seat_arrangement_types,id',
            'status'              => 'nullable|in:active,inactive',
            'year'                => 'required|integer|min:1900|max:' . (date('Y') + 1),
        ];
    }
}
