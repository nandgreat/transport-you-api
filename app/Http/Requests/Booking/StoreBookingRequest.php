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
            'schedule_id'  => 'required|integer|exists:schedules,id',
            'passengers'   => 'required|array|min:1',
            'passengers.*.name'        => 'required|string|max:100',
            'passengers.*.phone'       => 'nullable|string|max:20',
            'passengers.*.email'       => 'nullable|email|max:100',
            'passengers.*.seat_number' => 'required|integer|min:1',
            'notes'        => 'nullable|string|max:500',

            // Round trip — optional
            'is_round_trip'        => 'boolean',
            'return_schedule_id'   => 'nullable|integer|exists:schedules,id|required_if:is_round_trip,true',
        ];

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
            'passengers.*.name.required' => 'Each passenger must have a name.',
            'passengers.*.seat_number.required' => 'Each passenger must select a seat number.',
            'return_schedule_id.required_if' => 'A return schedule is required for round trip bookings.',
        ];
    }
}
