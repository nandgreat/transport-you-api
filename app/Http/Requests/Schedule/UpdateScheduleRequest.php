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
            // Route update: accept any of the three forms, all optional on PATCH
            'route_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('routes', 'id')->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId)),
            ],
            'origin_id'      => 'sometimes|nullable|integer|exists:locations,id',
            'destination_id' => 'sometimes|nullable|integer|exists:locations,id',
            'origin_city'      => 'sometimes|nullable|string|max:100',
            'destination_city' => 'sometimes|nullable|string|max:100',

            'external_route_ref'  => 'sometimes|nullable|string|max:255',
            'distance'            => 'sometimes|nullable|numeric|min:0',
            'estimated_duration'  => 'sometimes|nullable|integer|min:1',

            'vehicle_type_id' => [
                'sometimes',
                'required',
                'integer',
                Rule::exists('vehicle_types', 'id')->where('status', 'active'),
            ],
            'seat_arrangement_type_id' => 'sometimes|nullable|integer|exists:seat_arrangement_types,id',
            'departure_time' => 'sometimes|required|date_format:Y-m-d H:i:s',
            'arrival_time'   => 'sometimes|nullable|date_format:Y-m-d H:i:s|after:departure_time',
            'fare'           => 'sometimes|required|numeric|min:0',
            'status'         => 'sometimes|nullable|in:scheduled,in_transit,completed,cancelled',
        ];
    }
}
