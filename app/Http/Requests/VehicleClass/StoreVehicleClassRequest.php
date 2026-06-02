<?php

namespace App\Http\Requests\VehicleClass;

use Illuminate\Foundation\Http\FormRequest;

class StoreVehicleClassRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->isSuperAdmin();
    }

    public function rules(): array
    {
        return [
            'vehicle_class' => 'required|string|max:255',
            'vehicle_class_image' => 'required|string', // longText, likely base64 or URL
            'tenant_id' => 'nullable|integer|exists:tenants,id',
            'status_id' => 'nullable|integer|exists:statuses,id',
        ];
    }
}
