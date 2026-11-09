<?php

namespace App\Http\Requests\Location;

use App\Models\Location;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLocationRequest extends FormRequest
{
    /**
     * Any tenant admin may create locations for their own tenant; a super admin
     * may create for any tenant (or a global location) by passing tenant_id.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        return $user && ($user->isSuperAdmin() || $user->tenant_id !== null);
    }

    /**
     * When no location name is supplied, fall back to the city name so an
     * operator can register a terminal by city alone.
     */
    protected function prepareForValidation(): void
    {
        $name = trim((string) $this->input('name'));
        $city = trim((string) $this->input('city'));

        if ($name === '' && $city !== '') {
            $this->merge(['name' => $city]);
        }
    }

    public function rules(): array
    {
        $tenantId = $this->resolveTenantId();

        return [
            // City first, then an optional name (defaulted from city above).
            // The city must be one of the reference cities for the chosen state.
            'state_id' => 'required|integer|exists:states,id',
            'city'     => [
                'required', 'string', 'max:255',
                Rule::exists('cities', 'name')->where('state_id', $this->input('state_id')),
            ],
            'name'     => [
                'required', 'string', 'max:255',
                $this->uniqueNamePerTenant($tenantId),
            ],
            'address'  => 'required|string|max:255',

            // Optional geolocation point — both coordinates or neither.
            'latitude'  => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],

            'status_id' => 'nullable|integer|exists:statuses,id',
            'tenant_id' => 'nullable|integer|exists:tenants,id',
        ];
    }

    public function messages(): array
    {
        return [
            'latitude.required_with'  => 'Provide both latitude and longitude, or leave both blank.',
            'longitude.required_with' => 'Provide both latitude and longitude, or leave both blank.',
        ];
    }

    /**
     * Tenant the location belongs to: the admin's own tenant, or the one a
     * super admin names in the payload (null = a global location).
     */
    private function resolveTenantId(): ?int
    {
        $user = $this->user();

        if ($user->isSuperAdmin()) {
            return $this->input('tenant_id') ? (int) $this->input('tenant_id') : null;
        }

        return $user->tenant_id;
    }

    /**
     * Case-insensitive uniqueness of the name within one tenant's locations.
     */
    private function uniqueNamePerTenant(?int $tenantId): \Closure
    {
        return function (string $attribute, $value, \Closure $fail) use ($tenantId) {
            $exists = Location::where('tenant_id', $tenantId)
                ->whereRaw('LOWER(name) = ?', [strtolower(trim((string) $value))])
                ->exists();

            if ($exists) {
                $fail('You already have a location with this name.');
            }
        };
    }
}
