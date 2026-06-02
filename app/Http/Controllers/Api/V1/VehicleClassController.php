<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\VehicleClass\StoreVehicleClassRequest;
use App\Http\Requests\VehicleClass\UpdateVehicleClassRequest;
use App\Services\VehicleClassService;
use Illuminate\Http\Request;

class VehicleClassController extends Controller
{
    protected $vehicleClassService;

    public function __construct(VehicleClassService $vehicleClassService)
    {
        $this->vehicleClassService = $vehicleClassService;

        $this->middleware(function ($request, $next) {
            if (!$request->user()->isSuperAdmin()) {
                return $this->badRequest('You are not authorized to perform this action.');
            }
            return $next($request);
        })->except(['index', 'show']);
    }

    public function index(Request $request)
    {
        return $this->success($this->vehicleClassService->getVehicleClasses(), 'Vehicle classes retrieved successfully');
    }

    public function store(StoreVehicleClassRequest $request)
    {
        $vehicleClass = $this->vehicleClassService->createVehicleClass($request->validated());
        return $this->created($vehicleClass, 'Vehicle class created successfully');
    }

    public function show(string $id)
    {
        return $this->success($this->vehicleClassService->getVehicleClass($id), 'Vehicle class retrieved successfully');
    }

    public function update(UpdateVehicleClassRequest $request, string $id)
    {
        $vehicleClass = $this->vehicleClassService->updateVehicleClass($id, $request->validated());
        return $this->success($vehicleClass, 'Vehicle class updated successfully');
    }

    public function destroy(string $id)
    {
        $this->vehicleClassService->deleteVehicleClass($id);
        return $this->success(null, 'Vehicle class deleted successfully');
    }
}
