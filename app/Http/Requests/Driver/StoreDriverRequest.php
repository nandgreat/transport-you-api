<?php

namespace App\Http\Requests\Driver;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDriverRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && ($this->user()->isSuperAdmin() || $this->user()->tenant_id !== null);
    }

    public function rules(): array
    {
        $tenantId = $this->user()->isSuperAdmin() ? ($this->input('tenant_id') ?? null) : $this->user()->tenant_id;

        $rules = [
            'name'           => 'required|string|max:255',
            'phone'          => 'nullable|string|max:30',
            'email'          => 'nullable|email|max:255',
            'licence_number' => [
                'nullable', 'string', 'max:100',
                Rule::unique('drivers')->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'status'         => 'nullable|in:active,inactive',
            'availability'   => 'nullable|in:available,on_trip,unavailable',
        ];

        if ($this->user()->isSuperAdmin() && $this->isMethod('post')) {
            $rules['tenant_id'] = 'required|integer|exists:tenants,id';
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'licence_number.unique' => 'A driver with this licence number already exists for this operator.',
        ];
    }
}
