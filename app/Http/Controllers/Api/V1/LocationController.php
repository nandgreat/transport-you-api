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

        $this->middleware('auth:api')->except(['index', 'show']);

        $this->middleware(function ($request, $next) {

            if (!$request->user() || !$request->user()->isSuperAdmin()) {
                return $this->badRequest('You are not authorized to perform this action.');
            }
            return $next($request);
        })->except(['index', 'show']);
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
            'per_page'      => 'nullable|integer|min:1|max:100',
        ]);

        $filters = $request->only(['search', 'q', 'query', 'city', 'name', 'location_name']);
        $perPage = (int) $request->input('per_page', 30);

        return $this->success(
            $this->locationService->getLocations($filters, $perPage),
            'Locations retrieved successfully'
        );
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
