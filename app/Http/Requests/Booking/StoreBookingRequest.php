<?php

namespace App\Http\Requests\Booking;

use Illuminate\Foundation\Http\FormRequest;

class StoreBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $isTenant = $this->user()->tenant_id !== null || $this->user()->isSuperAdmin();

        $rules = [
            // Target trip: a concrete schedule, OR a recurring pattern + date
            // (which is materialised into a scheduled trip on booking).
            'schedule_id'        => 'nullable|integer|exists:schedules,id|required_without:schedule_group_id',
            'schedule_group_id'  => 'nullable|integer|exists:schedule_groups,id|required_without:schedule_id',
            'travel_date'        => 'nullable|date_format:Y-m-d|required_with:schedule_group_id',

            'passengers'   => 'required|array|min:1',
            'passengers.*.name'        => 'required|string|max:100',
            'passengers.*.phone'       => 'required|string|max:20',
            'passengers.*.email'       => 'nullable|email|max:100',
            // Required only when the schedule has seat selection enabled; enforced in BookingService.
            'passengers.*.seat_number' => 'nullable|integer|min:1',
            'notes'        => 'nullable|string|max:500',

            'is_round_trip' => 'boolean',
        ];

        // Round trip — return leg as a concrete schedule or a recurring pattern + date.
        if ($this->boolean('is_round_trip')) {
            $rules['return_schedule_id']       = 'nullable|integer|exists:schedules,id|required_without:return_schedule_group_id';
            $rules['return_schedule_group_id'] = 'nullable|integer|exists:schedule_groups,id|required_without:return_schedule_id';
            $rules['return_travel_date']       = 'nullable|date_format:Y-m-d|required_with:return_schedule_group_id';
        }

        // Tenants can explicitly set the status; customers are always forced to pending
        if ($isTenant) {
            $rules['status'] = 'nullable|in:pending,confirmed,cancelled';
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'passengers.required'        => 'At least one passenger is required.',
            'passengers.*.name.required'  => 'Each passenger must have a name.',
            'passengers.*.phone.required' => 'Each passenger must have a phone number.',
            'schedule_id.required_without'       => 'Provide a schedule_id, or a schedule_group_id with travel_date.',
            'schedule_group_id.required_without' => 'Provide a schedule_id, or a schedule_group_id with travel_date.',
            'travel_date.required_with'          => 'travel_date is required when booking a recurring schedule.',
        ];
    }
}
