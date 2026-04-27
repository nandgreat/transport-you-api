<?php

namespace App\Http\Requests\Schedule;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && ($this->user()->isSuperAdmin() || $this->user()->tenant_id !== null);
    }

    public function rules(): array
    {
        $tenantId = $this->user()->isSuperAdmin() ? ($this->input('tenant_id') ?? null) : $this->user()->tenant_id;

        return [
            'route_id' => [
                'sometimes',
                'required',
                'integer',
                Rule::exists('routes', 'id')->when($tenantId, function ($query) use ($tenantId) {
                    $query->where('tenant_id', $tenantId);
                })
            ],
            'vehicle_id' => [
                'sometimes',
                'required',
                'integer',
                Rule::exists('vehicles', 'id')->when($tenantId, function ($query) use ($tenantId) {
                    $query->where('tenant_id', $tenantId);
                })
            ],
            'departure_time' => 'sometimes|required|date_format:Y-m-d H:i:s',
            'arrival_time' => 'sometimes|nullable|date_format:Y-m-d H:i:s|after:departure_time',
            'fare' => 'sometimes|required|numeric|min:0',
            'status' => 'sometimes|nullable|in:scheduled,in_transit,completed,cancelled',
        ];
    }
}
