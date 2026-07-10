<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\VehicleCategory\StoreVehicleCategoryRequest;
use App\Http\Requests\VehicleCategory\UpdateVehicleCategoryRequest;
use App\Services\VehicleCategoryService;
use Illuminate\Http\Request;

class VehicleCategoryController extends Controller
{
    public function __construct(protected VehicleCategoryService $service)
    {
        // Write operations: super admin only. Read: any authenticated user.
        $this->middleware(function ($request, $next) {
            if (!$request->user()->isSuperAdmin()) {
                return $this->badRequest('Only super admins can manage vehicle categories.');
            }
            return $next($request);
        })->only(['store', 'update', 'destroy']);
    }

    public function index()
    {
        return $this->success($this->service->getCategories(), 'Vehicle categories retrieved successfully.');
    }

    public function store(StoreVehicleCategoryRequest $request)
    {
        $category = $this->service->createCategory($request->validated());
        return $this->created($category, 'Vehicle category created successfully.');
    }

    public function show(string $id)
    {
        return $this->success($this->service->getCategory($id), 'Vehicle category retrieved successfully.');
    }

    public function update(UpdateVehicleCategoryRequest $request, string $id)
    {
        $category = $this->service->updateCategory($id, $request->validated());
        return $this->success($category, 'Vehicle category updated successfully.');
    }

    public function destroy(string $id)
    {
        $this->service->deleteCategory($id);
        return $this->success(null, 'Vehicle category deleted successfully.');
    }
}
