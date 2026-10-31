<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

class StoreTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->isSuperAdmin();
    }

    public function rules(): array
    {
        return [
            // Company
            'name'  => 'required|string|max:255|unique:tenants,name',
            'email' => 'nullable|email|max:255|unique:tenants,email',
            'phone' => 'nullable|string|max:50',

            // Optional admin user to create alongside the company. Supplying any
            // admin field requires the full set so the login actually works.
            'admin_first_name' => 'nullable|required_with:admin_email|string|max:255',
            'admin_last_name'  => 'nullable|required_with:admin_email|string|max:255',
            'admin_email'      => 'nullable|email|max:255|unique:users,email',
            'admin_phone'      => 'nullable|string|max:50',
            'admin_password'   => 'nullable|required_with:admin_email|string|min:8',
        ];
    }
}
