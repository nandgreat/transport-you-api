<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Vehicle\StoreVehicleRequest;
use App\Http\Requests\Vehicle\UpdateVehicleRequest;
use App\Services\VehicleService;
use Illuminate\Http\Request;

class VehicleController extends Controller
{
    protected $vehicleService;

    public function __construct(VehicleService $vehicleService)
    {
        $this->vehicleService = $vehicleService;

        $this->middleware(function ($request, $next) {
            if (!$request->user()) {
                return $this->notFound('Unauthorized.');
            }
            if (!$request->user()->isSuperAdmin() && !$request->user()->tenant_id) {
                return $this->badRequest('Unauthorized. Must belong to a tenant.');
            }
            return $next($request);
        });
    }

    private function resolveTenantId(Request $request, ?string $inputKey = 'tenant_id'): ?int
    {
        if ($request->user()->isSuperAdmin()) {
            return $request->input($inputKey) ? (int) $request->input($inputKey) : null;
        }
        return $request->user()->tenant_id;
    }

    public function index(Request $request)
    {
        $tenantId = $this->resolveTenantId($request);
        return $this->success($this->vehicleService->getVehicles($tenantId), 'Vehicles retrieved successfully');
    }

    public function store(StoreVehicleRequest $request)
    {
        $tenantId = $this->resolveTenantId($request);
        $vehicle = $this->vehicleService->createVehicle($tenantId, $request->validated());
        return $this->created($vehicle, 'Vehicle created successfully');
    }

    public function show(Request $request, string $id)
    {
        $tenantId = $this->resolveTenantId($request);
        return $this->success($this->vehicleService->getVehicle($tenantId, $id), 'Vehicle retrieved successfully');
    }

    public function update(UpdateVehicleRequest $request, string $id)
    {
        $tenantId = $this->resolveTenantId($request);
        $vehicle = $this->vehicleService->updateVehicle($tenantId, $id, $request->validated());
        return $this->success($vehicle, 'Vehicle updated successfully');
    }

    public function destroy(Request $request, string $id)
    {
        $tenantId = $this->resolveTenantId($request);
        $this->vehicleService->deleteVehicle($tenantId, $id);
        return $this->success(null, 'Vehicle deleted successfully');
    }
}
