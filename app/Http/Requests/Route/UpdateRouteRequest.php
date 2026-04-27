<?php

namespace App\Http\Requests\Route;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRouteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && ($this->user()->isSuperAdmin() || $this->user()->tenant_id !== null);
    }

    public function rules(): array
    {
        $tenantId = $this->user()->isSuperAdmin() ? ($this->input('tenant_id') ?? null) : $this->user()->tenant_id;

        return [
            'origin_id' => [
                'sometimes',
                'required',
                'integer',
                Rule::exists('locations', 'id')->when($tenantId, function ($query) use ($tenantId) {
                    $query->where('tenant_id', $tenantId);
                })
            ],
            'destination_id' => [
                'sometimes',
                'required',
                'integer',
                'different:origin_id',
                Rule::exists('locations', 'id')->when($tenantId, function ($query) use ($tenantId) {
                    $query->where('tenant_id', $tenantId);
                })
            ],
            'distance' => 'sometimes|nullable|numeric|min:0',
            'estimated_duration' => 'sometimes|nullable|integer|min:0',
            'status' => 'sometimes|nullable|in:active,inactive',
        ];
    }
}
