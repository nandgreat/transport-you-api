<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->isSuperAdmin();
    }

    public function rules(): array
    {
        $id = $this->route('tenant');

        return [
            'name'   => ['sometimes', 'required', 'string', 'max:255', Rule::unique('tenants', 'name')->ignore($id)],
            'email'  => ['sometimes', 'nullable', 'email', 'max:255', Rule::unique('tenants', 'email')->ignore($id)],
            'phone'  => ['sometimes', 'nullable', 'string', 'max:50'],
            'status' => ['sometimes', 'required', 'in:active,inactive'],
        ];
    }
}
