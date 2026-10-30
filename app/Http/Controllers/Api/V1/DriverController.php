<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Driver\StoreDriverRequest;
use App\Http\Requests\Driver\UpdateDriverRequest;
use App\Services\DriverService;
use Illuminate\Http\Request;

class DriverController extends Controller
{
    public function __construct(protected DriverService $driverService)
    {
        $this->middleware(function ($request, $next) {
            if (! $request->user()) {
                return $this->notFound('Unauthorized.');
            }
            if (! $request->user()->isSuperAdmin() && ! $request->user()->tenant_id) {
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
        return $this->success($this->driverService->getDrivers($tenantId), 'Drivers retrieved successfully');
    }

    public function store(StoreDriverRequest $request)
    {
        $tenantId = $this->resolveTenantId($request);
        $driver   = $this->driverService->createDriver($tenantId, $request->validated());
        return $this->created($driver, 'Driver created successfully');
    }

    public function show(Request $request, string $id)
    {
        $tenantId = $this->resolveTenantId($request);
        return $this->success($this->driverService->getDriver($tenantId, $id), 'Driver retrieved successfully');
    }

    public function update(UpdateDriverRequest $request, string $id)
    {
        $tenantId = $this->resolveTenantId($request);
        $driver   = $this->driverService->updateDriver($tenantId, $id, $request->validated());
        return $this->success($driver, 'Driver updated successfully');
    }

    public function destroy(Request $request, string $id)
    {
        $tenantId = $this->resolveTenantId($request);
        $this->driverService->deleteDriver($tenantId, $id);
        return $this->success(null, 'Driver deleted successfully');
    }

    /**
     * A driver's trips bucketed by lifecycle: assigned (not started), ongoing
     * and completed, with per-bucket counts.
     */
    public function trips(Request $request, string $id)
    {
        $tenantId = $this->resolveTenantId($request);
        $trips    = $this->driverService->getDriverTrips($tenantId, $id);

        return $this->success([
            'counts' => [
                'assigned'  => $trips->where('trip_status', 'not_started')->count(),
                'ongoing'   => $trips->where('trip_status', 'ongoing')->count(),
                'completed' => $trips->where('trip_status', 'completed')->count(),
            ],
            'trips' => $trips->map(fn ($t) => $this->formatTrip($t))->values(),
        ], 'Driver trips retrieved successfully');
    }

    private function formatTrip(\App\Models\Schedule $trip): array
    {
        $route = $trip->route;

        return [
            'id'             => $trip->id,
            'source'         => $trip->schedule_group_id ? 'recurring' : 'one_off',
            'travel_date'    => $trip->departure_time?->format('Y-m-d'),
            'departure_time' => $trip->departure_time,
            'arrival_time'   => $trip->arrival_time,
            'trip_status'    => $trip->trip_status,
            'fare'           => $trip->fare,
            'capacity'       => $trip->capacity(),
            'booked_seats'   => $trip->bookedSeatCount(),
            'route' => $route ? [
                'id'          => $route->id,
                'origin'      => $this->formatLocation($route->origin),
                'destination' => $this->formatLocation($route->destination),
            ] : null,
            'vehicle' => $trip->vehicle ? [
                'id'                  => $trip->vehicle->id,
                'registration_number' => $trip->vehicle->registration_number,
            ] : null,
            'vehicle_type' => $trip->vehicleType ? [
                'id'           => $trip->vehicleType->id,
                'vehicle_name' => $trip->vehicleType->vehicle_name,
            ] : null,
        ];
    }

    private function formatLocation(?\App\Models\Location $location): ?array
    {
        if (! $location) return null;

        return ['id' => $location->id, 'name' => $location->name, 'city' => $location->city];
    }
}
