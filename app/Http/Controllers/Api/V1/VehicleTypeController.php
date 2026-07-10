<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\VehicleType\StoreVehicleTypeRequest;
use App\Http\Requests\VehicleType\UpdateVehicleTypeRequest;
use App\Services\VehicleTypeService;
use Illuminate\Http\Request;

class VehicleTypeController extends Controller
{
    public function __construct(protected VehicleTypeService $service)
    {
        // Write operations: super admin only. Read: any authenticated user.
        $this->middleware(function ($request, $next) {
            if (!$request->user()->isSuperAdmin()) {
                return $this->badRequest('Only super admins can manage vehicle types.');
            }
            return $next($request);
        })->only(['store', 'update', 'destroy']);
    }

    public function index(Request $request)
    {
        $categoryId = $request->query('vehicle_category_id') ? (int) $request->query('vehicle_category_id') : null;
        return $this->success($this->service->getVehicleTypes($categoryId), 'Vehicle types retrieved successfully.');
    }

    public function store(StoreVehicleTypeRequest $request)
    {
        $vehicleType = $this->service->createVehicleType($request->validated());
        return $this->created($vehicleType, 'Vehicle type created successfully.');
    }

    public function show(string $id)
    {
        return $this->success($this->service->getVehicleType($id), 'Vehicle type retrieved successfully.');
    }

    public function update(UpdateVehicleTypeRequest $request, string $id)
    {
        $vehicleType = $this->service->updateVehicleType($id, $request->validated());
        return $this->success($vehicleType, 'Vehicle type updated successfully.');
    }

    public function destroy(string $id)
    {
        $this->service->deleteVehicleType($id);
        return $this->success(null, 'Vehicle type deleted successfully.');
    }
}
