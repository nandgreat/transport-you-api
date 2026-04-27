<?php

namespace App\Http\Requests\Vehicle;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVehicleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && ($this->user()->isSuperAdmin() || $this->user()->tenant_id !== null);
    }

    public function rules(): array
    {
        $vehicle = $this->route('vehicle');
        $vehicleId = is_object($vehicle) ? $vehicle->id : $vehicle;

        return [
            'registration_number' => [
                'sometimes',
                'required',
                'string',
                Rule::unique('vehicles')->where(function ($query) {
                    return $query->where('tenant_id', $this->user()->tenant_id);
                })->ignore($vehicleId),
            ],
            'type' => 'sometimes|required|string',
            'capacity' => 'sometimes|required|integer|min:1',
            'status' => 'sometimes|required|in:active,maintenance,inactive',
            'image_url' => 'sometimes|nullable|url|max:2048',
        ];
    }
}
