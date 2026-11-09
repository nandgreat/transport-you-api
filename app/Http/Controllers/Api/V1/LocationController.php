<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Location\StoreLocationRequest;
use App\Http\Requests\Location\UpdateLocationRequest;
use App\Services\LocationService;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    protected $locationService;

    public function __construct(LocationService $locationService)
    {
        $this->locationService = $locationService;

        // The whole resource is already behind auth:api (route group). Writes
        // are further limited to tenant admins (scoped to their own tenant) and
        // super admins.
        $this->middleware(function ($request, $next) {
            $user = $request->user();
            if (! $user || (! $user->isSuperAdmin() && ! $user->tenant_id)) {
                return $this->badRequest('You are not authorized to perform this action.');
            }
            return $next($request);
        })->only(['store', 'update', 'destroy']);
    }

    public function index(Request $request)
    {
        $request->validate([
            'search'        => 'nullable|string|max:100',
            'q'             => 'nullable|string|max:100',
            'query'         => 'nullable|string|max:100',
            'city'          => 'nullable|string|max:100',
            'name'          => 'nullable|string|max:100',
            'location_name' => 'nullable|string|max:100',
            'tenant_id'     => 'nullable|integer|exists:tenants,id',
            'per_page'      => 'nullable|integer|min:1|max:100',
        ]);

        $filters = $request->only(['search', 'q', 'query', 'city', 'name', 'location_name']);

        // A tenant admin only ever sees their own locations. A super admin sees
        // all, or a single tenant's when tenant_id is supplied. Anyone without a
        // tenant (and not a super admin) sees none.
        $filters['tenant_id'] = $this->scopeTenantId($request, true);

        $perPage = (int) $request->input('per_page', 30);

        return $this->success(
            $this->locationService->getLocations($filters, $perPage),
            'Locations retrieved successfully'
        );
    }

    /**
     * Public, read-only list of all locations for the customer app. Unlike
     * index() it is not tenant-scoped and needs no login.
     */
    public function publicIndex(Request $request)
    {
        $request->validate([
            'search'   => 'nullable|string|max:100',
            'city'     => 'nullable|string|max:100',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        return $this->success(
            $this->locationService->getLocations(
                $request->only(['search', 'city']),
                (int) $request->input('per_page', 100)
            ),
            'Locations retrieved successfully'
        );
    }

    public function store(StoreLocationRequest $request)
    {
        $data = $request->validated();
        $user = $request->user();

        // Tenant admins can only create under their own tenant.
        if (! $user->isSuperAdmin()) {
            $data['tenant_id'] = $user->tenant_id;
        }

        $location = $this->locationService->createLocation($data);

        return $this->created($location->load('state'), 'Location created successfully');
    }

    public function show(Request $request, string $id)
    {
        return $this->success(
            $this->locationService->getLocation($this->scopeTenantId($request), $id),
            'Location retrieved successfully'
        );
    }

    public function update(UpdateLocationRequest $request, string $id)
    {
        $location = $this->locationService->updateLocation($this->scopeTenantId($request), $id, $request->validated());

        return $this->success($location, 'Location updated successfully');
    }

    public function destroy(Request $request, string $id)
    {
        $this->locationService->deleteLocation($this->scopeTenantId($request), $id);

        return $this->success(null, 'Location deleted successfully');
    }

    /**
     * The tenant a request is restricted to:
     *   - super admin  → null (unscoped; may filter by tenant_id on index)
     *   - tenant admin → their own tenant id
     *   - anyone else  → -1 (matches no tenant, so they see/manage nothing)
     *
     * When $allowSuperAdminFilter is set (index only), a super admin may narrow
     * to a single tenant via the tenant_id query parameter.
     */
    private function scopeTenantId(Request $request, bool $allowSuperAdminFilter = false): ?int
    {
        $user = $request->user();

        if ($user->isSuperAdmin()) {
            return $allowSuperAdminFilter && $request->filled('tenant_id')
                ? $request->integer('tenant_id')
                : null;
        }

        return $user->tenant_id ?? -1;
    }
}
