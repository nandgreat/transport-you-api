<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class TenantController extends Controller
{
    /**
     * Return the authenticated user's tenant.
     */
    public function show(Request $request)
    {
        $tenant = $request->user()->tenant;

        if (! $tenant) {
            return $this->notFound('No tenant is associated with this account.');
        }

        return $this->success($tenant, 'Tenant retrieved successfully.');
    }

    /**
     * Update the authenticated user's tenant details.
     */
    public function update(Request $request)
    {
        $tenant = $request->user()->tenant;

        if (! $tenant) {
            return $this->notFound('No tenant is associated with this account.');
        }

        $validated = $request->validate([
            'name'  => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
        ]);

        $tenant->update($validated);

        return $this->success($tenant->fresh(), 'Tenant updated successfully.');
    }
}
