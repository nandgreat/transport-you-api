<?php

namespace App\Http\Requests\Route;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRouteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && ($this->user()->isSuperAdmin() || $this->user()->tenant_id !== null);
    }

    public function rules(): array
    {
        $tenantId = $this->user()->isSuperAdmin() ? $this->input('tenant_id') : $this->user()->tenant_id;

        $rules = [
            'origin_id' => [
                'required',
                'integer',
                Rule::exists('locations', 'id')->when($tenantId, function ($query) use ($tenantId) {
                    $query->where('tenant_id', $tenantId);
                })
            ],
            'destination_id' => [
                'required',
                'integer',
                'different:origin_id',
                Rule::exists('locations', 'id')->when($tenantId, function ($query) use ($tenantId) {
                    $query->where('tenant_id', $tenantId);
                })
            ],
            'distance' => 'nullable|numeric|min:0',
            'estimated_duration' => 'nullable|integer|min:0',
            'status' => 'nullable|in:active,inactive',
        ];

        if ($this->user()->isSuperAdmin() && $this->isMethod('post')) {
            $rules['tenant_id'] = 'required|integer|exists:tenants,id';
        }

        return $rules;
    }
}
