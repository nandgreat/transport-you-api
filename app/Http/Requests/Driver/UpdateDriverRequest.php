<?php

namespace App\Http\Requests\Driver;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDriverRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && ($this->user()->isSuperAdmin() || $this->user()->tenant_id !== null);
    }

    public function rules(): array
    {
        $driver   = $this->route('driver');
        $driverId = is_object($driver) ? $driver->id : $driver;
        $tenantId = $this->user()->isSuperAdmin() ? ($this->input('tenant_id') ?? null) : $this->user()->tenant_id;

        return [
            'name'           => 'sometimes|required|string|max:255',
            'phone'          => 'sometimes|nullable|string|max:30',
            'email'          => 'sometimes|nullable|email|max:255',
            'licence_number' => [
                'sometimes', 'nullable', 'string', 'max:100',
                Rule::unique('drivers')->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))->ignore($driverId),
            ],
            'status'         => 'sometimes|required|in:active,inactive',
            'availability'   => 'sometimes|required|in:available,on_trip,unavailable',
        ];
    }

    public function messages(): array
    {
        return [
            'licence_number.unique' => 'A driver with this licence number already exists for this operator.',
        ];
    }
}
