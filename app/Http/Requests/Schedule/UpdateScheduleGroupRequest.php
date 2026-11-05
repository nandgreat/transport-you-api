<?php

namespace App\Http\Requests\Schedule;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateScheduleGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && ($this->user()->isSuperAdmin() || $this->user()->tenant_id !== null);
    }

    /**
     * Normalise dates to Y-m-d and times to H:i so clients may send ISO or
     * datetime-local values.
     */
    protected function prepareForValidation(): void
    {
        foreach (['start_date', 'end_date'] as $field) {
            if ($this->filled($field) && is_string($this->input($field))) {
                try {
                    $this->merge([$field => \Carbon\Carbon::parse($this->input($field))->format('Y-m-d')]);
                } catch (\Throwable $e) {
                    // leave original for validation to flag
                }
            }
        }

        foreach (['departure_time', 'arrival_time'] as $field) {
            if ($this->filled($field) && is_string($this->input($field))) {
                try {
                    $this->merge([$field => \Carbon\Carbon::parse($this->input($field))->format('H:i')]);
                } catch (\Throwable $e) {
                    // leave original for validation to flag
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
                'sometimes', 'nullable', 'integer',
                Rule::exists('routes', 'id')->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'origin_id'        => 'sometimes|nullable|integer|exists:locations,id',
            'destination_id'   => 'sometimes|nullable|integer|exists:locations,id',
            'origin_city'      => 'sometimes|nullable|string|max:100',
            'destination_city' => 'sometimes|nullable|string|max:100',
            'external_route_ref' => 'sometimes|nullable|string|max:255',
            'distance'           => 'sometimes|nullable|numeric|min:0',
            'estimated_duration' => 'sometimes|nullable|integer|min:1',

            'vehicle_type_id' => [
                'sometimes', 'required', 'integer',
                Rule::exists('vehicle_types', 'id')->where('status', 'active'),
            ],
            'seat_arrangement_type_id' => [
                'sometimes', 'nullable', 'integer',
                Rule::exists('seat_arrangement_type_vehicle_type', 'seat_arrangement_type_id')
                    ->where('vehicle_type_id', $this->resolveVehicleTypeId()),
            ],
            'seat_selection_enabled' => 'sometimes|boolean',

            // Recurring pattern fields
            'start_date'     => 'sometimes|required|date_format:Y-m-d',
            'end_date'       => 'sometimes|required|date_format:Y-m-d',
            'days_of_week'   => 'sometimes|required|array|min:1',
            'days_of_week.*' => 'required|string|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
            'departure_time' => 'sometimes|required|date_format:H:i',
            'arrival_time'   => 'sometimes|nullable|date_format:H:i',
            'fare'           => 'sometimes|required|numeric|min:0',
            'status'         => 'sometimes|required|in:active,paused,ended',
        ];
    }

    public function messages(): array
    {
        return [
            'seat_arrangement_type_id.exists' => 'The selected seat arrangement is not available for this vehicle type.',
            'days_of_week.*.in' => 'Each day must be one of: monday, tuesday, wednesday, thursday, friday, saturday, sunday.',
            'status.in' => 'Status must be one of: active, paused, ended.',
        ];
    }

    /**
     * Vehicle type the arrangement must belong to: the one in the payload,
     * otherwise the group's current vehicle_type_id.
     */
    private function resolveVehicleTypeId(): ?int
    {
        if ($this->filled('vehicle_type_id')) {
            return (int) $this->input('vehicle_type_id');
        }

        $groupId = $this->route('id');

        return $groupId
            ? \App\Models\ScheduleGroup::whereKey($groupId)->value('vehicle_type_id')
            : null;
    }
}
