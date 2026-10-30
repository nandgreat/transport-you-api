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

    /**
     * Accept ISO / datetime-local values by normalising to `Y-m-d H:i:s`.
     */
    protected function prepareForValidation(): void
    {
        foreach (['departure_time', 'arrival_time', 'return_departure_time', 'return_arrival_time'] as $field) {
            if ($this->filled($field) && is_string($this->input($field))) {
                try {
                    $this->merge([$field => \Carbon\Carbon::parse($this->input($field))->format('Y-m-d H:i:s')]);
                } catch (\Throwable $e) {
                    // leave original value for validation to flag
                }
            }
        }
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
            // Must belong to the vehicle type being set (or the schedule's current one).
            'seat_arrangement_type_id' => [
                'sometimes', 'nullable', 'integer',
                Rule::exists('seat_arrangement_type_vehicle_type', 'seat_arrangement_type_id')
                    ->where('vehicle_type_id', $this->resolveVehicleTypeId()),
            ],
            'seat_selection_enabled'   => 'sometimes|boolean',
            'departure_time' => 'sometimes|required|date_format:Y-m-d H:i:s',
            'arrival_time'   => 'sometimes|nullable|date_format:Y-m-d H:i:s|after:departure_time',
            'fare'           => 'sometimes|required|numeric|min:0',
            'status'         => 'sometimes|nullable|in:scheduled,in_transit,completed,cancelled',
        ];
    }

    public function messages(): array
    {
        return [
            'seat_arrangement_type_id.exists' => 'The selected seat arrangement is not available for this vehicle type.',
        ];
    }

    /**
     * Vehicle type the arrangement must belong to: the one in the payload,
     * otherwise the schedule's current vehicle_type_id.
     */
    private function resolveVehicleTypeId(): ?int
    {
        if ($this->filled('vehicle_type_id')) {
            return (int) $this->input('vehicle_type_id');
        }

        $scheduleId = $this->route('schedule');

        return $scheduleId
            ? \App\Models\Schedule::whereKey($scheduleId)->value('vehicle_type_id')
            : null;
    }
}
