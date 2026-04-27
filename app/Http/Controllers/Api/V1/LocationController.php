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

        $this->middleware(function ($request, $next) {

            if (!$request->user()->isSuperAdmin()) {
                return $this->badRequest('You are not authorized to perform this action.');
            }
            return $next($request);
        })->except(['index', 'show']);
    }
    public function index(Request $request)
    {
        return $this->success($this->locationService->getLocations(), 'Locations retrieved successfully');
    }

    public function store(StoreLocationRequest $request)
    {
        $location = $this->locationService->createLocation($request->validated());
        return $this->created($location, 'Location created successfully');
    }

    public function show(Request $request, string $id)
    {
        return $this->success($this->locationService->getLocation($id), 'Location retrieved successfully');
    }

    public function update(UpdateLocationRequest $request, string $id)
    {
        $location = $this->locationService->updateLocation($id, $request->validated());
        return $this->success($location, 'Location updated successfully');
    }

    public function destroy(string $id)
    {
        $this->locationService->deleteLocation($id);
        return $this->success(null, 'Location deleted successfully');
    }
}
