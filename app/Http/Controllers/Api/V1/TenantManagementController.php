<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreTenantRequest;
use App\Http\Requests\Tenant\UpdateTenantRequest;
use App\Services\TenantManagementService;
use Illuminate\Http\Request;

/**
 * Platform-wide tenant (company) management. Every action here is restricted
 * to the global super admin.
 */
class TenantManagementController extends Controller
{
    public function __construct(protected TenantManagementService $tenants)
    {
        $this->middleware(function ($request, $next) {
            if (! $request->user() || ! $request->user()->isSuperAdmin()) {
                return $this->forbidden('Only super admins can manage tenant companies.');
            }
            return $next($request);
        });
    }

    public function index(Request $request)
    {
        $filters = $request->only(['search', 'status']);
        $perPage = (int) $request->input('per_page', 15);

        return $this->success($this->tenants->list($filters, $perPage), 'Tenants retrieved successfully');
    }

    public function store(StoreTenantRequest $request)
    {
        $tenant = $this->tenants->create($request->validated());

        return $this->created($tenant, 'Tenant company created successfully');
    }

    public function show(string $id)
    {
        return $this->success($this->tenants->get($id), 'Tenant retrieved successfully');
    }

    public function update(UpdateTenantRequest $request, string $id)
    {
        $tenant = $this->tenants->update($id, $request->validated());

        return $this->success($tenant, 'Tenant updated successfully');
    }

    public function destroy(string $id)
    {
        $this->tenants->delete($id);

        return $this->success(null, 'Tenant company deleted successfully');
    }
}
