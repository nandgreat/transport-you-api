<?php

namespace App\Http\Requests\Schedule;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && ($this->user()->isSuperAdmin() || $this->user()->tenant_id !== null);
    }

    public function rules(): array
    {
        $tenantId = $this->user()->isSuperAdmin() ? ($this->input('tenant_id') ?? null) : $this->user()->tenant_id;

        $rules = [
            'route_id' => [
                'required',
                'integer',
                Rule::exists('routes', 'id')->when($tenantId, function ($query) use ($tenantId) {
                    $query->where('tenant_id', $tenantId);
                })
            ],
            'vehicle_id' => [
                'required',
                'integer',
                Rule::exists('vehicles', 'id')->when($tenantId, function ($query) use ($tenantId) {
                    $query->where('tenant_id', $tenantId);
                })
            ],
            'departure_time' => 'required|date_format:Y-m-d H:i:s|after:now',
            'arrival_time' => 'nullable|date_format:Y-m-d H:i:s|after:departure_time',
            'fare' => 'required|numeric|min:0',
            'status' => 'nullable|in:scheduled,in_transit,completed,cancelled',
        ];

        if ($this->user()->isSuperAdmin() && $this->isMethod('post')) {
            $rules['tenant_id'] = 'required|integer|exists:tenants,id';
        }

        return $rules;
    }
}
