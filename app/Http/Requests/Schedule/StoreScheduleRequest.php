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

    /**
     * Normalise one-off datetime fields to `Y-m-d H:i:s` so clients may send
     * ISO / datetime-local values (e.g. "2026-10-28T12:00"). Recurring mode
     * uses plain `H:i` times, so it is left untouched.
     */
    protected function prepareForValidation(): void
    {
        if ($this->filled('start_date')) {
            return;
        }

        foreach (['departure_time', 'arrival_time', 'return_departure_time', 'return_arrival_time'] as $field) {
            if ($this->filled($field) && ($normalised = $this->normaliseDateTime($this->input($field)))) {
                $this->merge([$field => $normalised]);
            }
        }
    }

    private function normaliseDateTime($value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        try {
            return \Carbon\Carbon::parse($value)->format('Y-m-d H:i:s');
        } catch (\Throwable $e) {
            return null; // leave the original value so validation reports the error
        }
    }

    public function rules(): array
    {
        $tenantId = $this->user()->isSuperAdmin() ? ($this->input('tenant_id') ?? null) : $this->user()->tenant_id;

        $isRecurring     = $this->filled('start_date');
        $hasRouteId      = $this->filled('route_id');
        $hasLocationIds  = $this->filled('origin_id') && $this->filled('destination_id');
        $hasCityNames    = $this->filled('origin_city') && $this->filled('destination_city');

        $rules = [
            // --- Route resolution (shared by both modes) ---
            'route_id' => [
                'nullable', 'integer',
                Rule::exists('routes', 'id')->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId)),
                Rule::requiredIf(! $hasLocationIds && ! $hasCityNames),
            ],
            'origin_id'        => ['nullable', 'integer', 'exists:locations,id', Rule::requiredIf(! $hasRouteId && ! $hasCityNames)],
            'destination_id'   => ['nullable', 'integer', 'exists:locations,id', Rule::requiredIf(! $hasRouteId && ! $hasCityNames)],
            'origin_city'      => ['nullable', 'string', 'max:100', Rule::requiredIf(! $hasRouteId && ! $hasLocationIds)],
            'destination_city' => ['nullable', 'string', 'max:100', Rule::requiredIf(! $hasRouteId && ! $hasLocationIds)],
            'external_route_ref'  => 'nullable|string|max:255',
            'distance'            => 'nullable|numeric|min:0',
            'estimated_duration'  => 'nullable|integer|min:1',

            // --- Vehicle & fare (shared) ---
            'vehicle_type_id' => [
                'required', 'integer',
                Rule::exists('vehicle_types', 'id')->where('status', 'active'),
            ],
            'seat_arrangement_type_id' => 'nullable|integer|exists:seat_arrangement_types,id',
            'seat_selection_enabled'   => 'nullable|boolean',
            'fare'   => 'required|numeric|min:0',
            'status' => 'nullable|in:scheduled,in_transit,completed,cancelled',

            // --- Round trip (shared) ---
            'is_round_trip' => 'boolean',
            'return_fare'   => 'nullable|numeric|min:0',
        ];

        $isRoundTrip = $this->boolean('is_round_trip');

        if ($isRecurring) {
            // --- Recurring mode ---
            $rules['start_date']      = 'required|date|date_format:Y-m-d|after_or_equal:today';
            $rules['end_date']        = 'required|date|date_format:Y-m-d|after_or_equal:start_date';
            $rules['days_of_week']    = 'required|array|min:1';
            $rules['days_of_week.*']  = 'required|string|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday';
            $rules['departure_time']  = 'required|date_format:H:i';
            $rules['arrival_time']    = 'nullable|date_format:H:i';

            if ($isRoundTrip) {
                $rules['return_departure_time'] = 'required|date_format:H:i';
                $rules['return_arrival_time']   = 'nullable|date_format:H:i';
            }
        } else {
            // --- One-off mode ---
            $rules['departure_time']  = 'required|date_format:Y-m-d H:i:s|after:now';
            $rules['arrival_time']    = 'nullable|date_format:Y-m-d H:i:s|after:departure_time';

            if ($isRoundTrip) {
                $rules['return_departure_time'] = 'required|date_format:Y-m-d H:i:s|after:departure_time';
                $rules['return_arrival_time']   = 'nullable|date_format:Y-m-d H:i:s|after:return_departure_time';
            }
        }

        if ($this->user()->isSuperAdmin() && $this->isMethod('post')) {
            $rules['tenant_id'] = 'required|integer|exists:tenants,id';
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'route_id.required'         => 'Provide a route_id, or origin_id + destination_id, or origin_city + destination_city.',
            'origin_id.required'        => 'Provide origin_id + destination_id when not using route_id or city names.',
            'destination_id.required'   => 'Provide origin_id + destination_id when not using route_id or city names.',
            'origin_city.required'      => 'Provide origin_city + destination_city when not using route_id or location IDs.',
            'destination_city.required' => 'Provide origin_city + destination_city when not using route_id or location IDs.',
            'days_of_week.required'     => 'Select at least one day of the week for recurring schedules.',
            'days_of_week.*.in'         => 'Each day must be one of: monday, tuesday, wednesday, thursday, friday, saturday, sunday.',
            'departure_time.date_format' => $this->filled('start_date')
                ? 'departure_time must be a time in H:i format (e.g. 08:00).'
                : 'departure_time must be a full datetime in Y-m-d H:i:s format.',
        ];
    }
}
