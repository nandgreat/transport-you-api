<?php

namespace App\Http\Requests\VehicleClass;

use Illuminate\Foundation\Http\FormRequest;

class UpdateVehicleClassRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->isSuperAdmin();
    }

    public function rules(): array
    {
        return [
            'vehicle_class' => 'sometimes|required|string|max:255',
            'vehicle_class_image' => 'sometimes|required|string',
            'tenant_id' => 'sometimes|required|integer|exists:tenants,id',
            'status_id' => 'sometimes|required|integer|exists:statuses,id',
        ];
    }
}
