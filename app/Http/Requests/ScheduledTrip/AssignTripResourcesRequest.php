<?php

namespace App\Http\Requests\ScheduledTrip;

use App\Models\Schedule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignTripResourcesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && ($this->user()->isSuperAdmin() || $this->user()->tenant_id !== null);
    }

    public function rules(): array
    {
        $tenantId = $this->tripTenantId();

        return [
            // The vehicle must belong to the trip's tenant and be the same
            // vehicle type the trip was scheduled for.
            'vehicle_id' => [
                'required', 'integer',
                Rule::exists('vehicles', 'id')
                    ->where(fn ($q) => $q
                        ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
                        ->when($this->tripVehicleTypeId(), fn ($q, $v) => $q->where('vehicle_type_id', $v))),
            ],
            // The driver must be a driver record belonging to the trip's tenant.
            'driver_id' => [
                'required', 'integer',
                Rule::exists('drivers', 'id')
                    ->where(fn ($q) => $q
                        ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
                        ->where('status', 'active')),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'vehicle_id.exists' => 'The selected vehicle is not available for this trip (it must belong to this operator and match the trip\'s vehicle type).',
            'driver_id.exists'  => 'The selected driver is not an active driver of this operator.',
        ];
    }

    private function trip(): ?Schedule
    {
        return $this->getRouteTrip ??= Schedule::find($this->route('id'));
    }

    private function tripTenantId(): ?int
    {
        return $this->trip()?->tenant_id;
    }

    private function tripVehicleTypeId(): ?int
    {
        return $this->trip()?->vehicle_type_id;
    }

    /** Cache the resolved schedule across rule/message resolution. */
    private ?Schedule $getRouteTrip = null;
}
