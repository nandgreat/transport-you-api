<?php

namespace App\Http\Requests\Location;

use App\Models\Location;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLocationRequest extends FormRequest
{
    /**
     * A tenant admin may only update locations owned by their tenant; a super
     * admin may update any.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->tenant_id === null) {
            return false;
        }

        $location = Location::find($this->route('location'));

        return $location && (int) $location->tenant_id === (int) $user->tenant_id;
    }

    public function rules(): array
    {
        $tenantId = $this->resolveTenantId();
        $stateId  = $this->effectiveStateId();

        return [
            'state_id' => 'sometimes|required|integer|exists:states,id',
            'city'     => [
                'sometimes', 'required', 'string', 'max:255',
                Rule::exists('cities', 'name')->where('state_id', $stateId),
            ],
            'name'     => [
                'sometimes', 'required', 'string', 'max:255',
                $this->uniqueNamePerTenant($tenantId),
            ],
            'address'  => 'sometimes|required|string|max:255',

            'latitude'  => ['sometimes', 'nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['sometimes', 'nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],

            'status' => 'sometimes|nullable|in:active,inactive',
        ];
    }

    public function messages(): array
    {
        return [
            'latitude.required_with'  => 'Provide both latitude and longitude, or leave both blank.',
            'longitude.required_with' => 'Provide both latitude and longitude, or leave both blank.',
        ];
    }

    private function resolveTenantId(): ?int
    {
        $user = $this->user();

        if ($user->isSuperAdmin()) {
            return Location::whereKey($this->route('location'))->value('tenant_id');
        }

        return $user->tenant_id;
    }

    /**
     * City must match the state being set, or the location's current state.
     */
    private function effectiveStateId(): ?int
    {
        return $this->input('state_id')
            ? (int) $this->input('state_id')
            : Location::whereKey($this->route('location'))->value('state_id');
    }

    /**
     * Case-insensitive uniqueness of the name within one tenant, ignoring the
     * location being updated.
     */
    private function uniqueNamePerTenant(?int $tenantId): \Closure
    {
        $ignoreId = (int) $this->route('location');

        return function (string $attribute, $value, \Closure $fail) use ($tenantId, $ignoreId) {
            $exists = Location::where('tenant_id', $tenantId)
                ->whereRaw('LOWER(name) = ?', [strtolower(trim((string) $value))])
                ->where('id', '!=', $ignoreId)
                ->exists();

            if ($exists) {
                $fail('You already have a location with this name.');
            }
        };
    }
}
