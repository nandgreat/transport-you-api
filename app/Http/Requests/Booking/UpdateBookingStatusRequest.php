<?php

namespace App\Http\Requests\Booking;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBookingStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Only tenant users and super admins can change status
        $user = $this->user();
        return $user && ($user->tenant_id !== null || $user->isSuperAdmin());
    }

    public function rules(): array
    {
        return [
            'status' => 'required|in:pending,confirmed,cancelled',
            'notes'  => 'nullable|string|max:500',
        ];
    }
}
