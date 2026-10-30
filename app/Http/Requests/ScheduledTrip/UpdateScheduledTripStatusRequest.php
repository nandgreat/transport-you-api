<?php

namespace App\Http\Requests\ScheduledTrip;

use Illuminate\Foundation\Http\FormRequest;

class UpdateScheduledTripStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && ($this->user()->isSuperAdmin() || $this->user()->tenant_id !== null);
    }

    public function rules(): array
    {
        return [
            'status' => 'required|in:not_started,ongoing,completed,cancelled',
        ];
    }

    public function messages(): array
    {
        return [
            'status.in' => 'Status must be one of: not_started, ongoing, completed, cancelled.',
        ];
    }
}
