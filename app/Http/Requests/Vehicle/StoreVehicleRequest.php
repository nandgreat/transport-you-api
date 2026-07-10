<?php

namespace App\Http\Requests\Vehicle;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVehicleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && ($this->user()->isSuperAdmin() || $this->user()->tenant_id !== null);
    }

    public function rules(): array
    {
        $tenantId = $this->user()->isSuperAdmin() ? ($this->input('tenant_id') ?? null) : $this->user()->tenant_id;

        $rules = [
            'registration_number' => [
                'required',
                'string',
                Rule::unique('vehicles')->when($tenantId, function ($query) use ($tenantId) {
                    $query->where('tenant_id', $tenantId);
                }),
            ],
            'vehicle_type_id' => 'nullable|integer|exists:vehicle_types,id',
            'status' => 'nullable|in:active,maintenance,inactive',
            'image_url' => 'nullable|string|max:2048',
        ];

        if ($this->user()->isSuperAdmin() && $this->isMethod('post')) {
            $rules['tenant_id'] = 'required|integer|exists:tenants,id';
        }

        return $rules;
    }
}