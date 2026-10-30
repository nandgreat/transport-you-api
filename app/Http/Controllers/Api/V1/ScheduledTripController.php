<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ScheduledTrip\AssignTripResourcesRequest;
use App\Http\Requests\ScheduledTrip\UpdateScheduledTripStatusRequest;
use App\Models\Schedule;
use App\Services\ScheduledTripService;
use Illuminate\Http\Request;

class ScheduledTripController extends Controller
{
    public function __construct(protected ScheduledTripService $service)
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

    private function resolveTenantId(Request $request): ?int
    {
        if ($request->user()->isSuperAdmin()) {
            return $request->input('tenant_id') ? (int) $request->input('tenant_id') : null;
        }
        return $request->user()->tenant_id;
    }

    public function index(Request $request)
    {
        $request->validate([
            'trip_status'       => 'nullable|in:not_started,ongoing,completed,cancelled',
            'source'            => 'nullable|in:one_off,recurring',
            'schedule_group_id' => 'nullable|integer|exists:schedule_groups,id',
            'departure_date'    => 'nullable|date_format:Y-m-d',
            'from_date'         => 'nullable|date_format:Y-m-d',
            'to_date'           => 'nullable|date_format:Y-m-d',
            'origin_id'         => 'nullable|integer|exists:locations,id',
            'destination_id'    => 'nullable|integer|exists:locations,id',
            'tenant_id'         => 'nullable|integer|exists:tenants,id',
        ]);

        $tenantId = $this->resolveTenantId($request);
        $filters  = $request->only(['trip_status', 'source', 'schedule_group_id', 'departure_date', 'from_date', 'to_date', 'origin_id', 'destination_id']);

        $trips = $this->service->list($tenantId, $filters);
        $trips->setCollection($trips->getCollection()->map(fn ($t) => $this->formatTrip($t)));

        return $this->success($trips, 'Scheduled trips retrieved successfully');
    }

    public function show(Request $request, string $id)
    {
        $tenantId = $this->resolveTenantId($request);
        $trip     = $this->service->get($tenantId, $id);

        return $this->success($this->formatTrip($trip, true), 'Scheduled trip retrieved successfully');
    }

    public function updateStatus(UpdateScheduledTripStatusRequest $request, string $id)
    {
        $tenantId = $this->resolveTenantId($request);
        $trip     = $this->service->updateStatus($tenantId, $id, $request->validated()['status']);

        return $this->success($this->formatTrip($trip), 'Scheduled trip status updated successfully');
    }

    public function cancel(Request $request, string $id)
    {
        $tenantId = $this->resolveTenantId($request);
        $trip     = $this->service->cancel($tenantId, $id);

        return $this->success($this->formatTrip($trip), 'Scheduled trip cancelled successfully');
    }

    /**
     * Assign a physical vehicle and driver to a trip.
     */
    public function assign(AssignTripResourcesRequest $request, string $id)
    {
        $tenantId = $this->resolveTenantId($request);
        $data     = $request->validated();
        $trip     = $this->service->assign($tenantId, $id, (int) $data['vehicle_id'], (int) $data['driver_id']);

        return $this->success($this->formatTrip($trip), 'Vehicle and driver assigned successfully');
    }

    /**
     * Remove the vehicle/driver assignment from a trip.
     */
    public function unassign(Request $request, string $id)
    {
        $tenantId = $this->resolveTenantId($request);
        $trip     = $this->service->unassign($tenantId, $id);

        return $this->success($this->formatTrip($trip), 'Vehicle and driver assignment cleared successfully');
    }

    /**
     * Passenger manifest for a trip: the number of passengers and whether each
     * one has checked in (boarded) yet.
     */
    public function passengers(Request $request, string $id)
    {
        $tenantId = $this->resolveTenantId($request);
        $trip     = $this->service->get($tenantId, $id);
        $manifest = $this->service->manifest($trip);

        return $this->success([
            'id'                   => $trip->id,
            'travel_date'          => $trip->departure_time?->format('Y-m-d'),
            'trip_status'          => $trip->trip_status,
            'passenger_count'      => $manifest['passenger_count'],
            'checked_in_count'     => $manifest['checked_in_count'],
            'not_checked_in_count' => $manifest['not_checked_in_count'],
            'passengers'           => $manifest['passengers'],
        ], 'Passenger manifest retrieved successfully');
    }

    // -------------------------------------------------------------------------

    private function formatTrip(Schedule $trip, bool $withManifest = false): array
    {
        $route    = $trip->route;
        $applied  = $trip->resolveSeatArrangement();
        $booked   = $trip->bookedSeatCount();
        $manifest = $this->service->manifest($trip);

        $data = [
            'id'              => $trip->id,
            'source'          => $trip->schedule_group_id ? 'recurring' : 'one_off',
            'schedule_group_id' => $trip->schedule_group_id,
            'travel_date'     => $trip->departure_time?->format('Y-m-d'),
            'departure_time'  => $trip->departure_time,
            'arrival_time'    => $trip->arrival_time,
            'fare'            => $trip->fare,
            'trip_status'     => $trip->trip_status,
            'status'          => $trip->status,
            'seat_selection_enabled' => (bool) $trip->seat_selection_enabled,
            'seat_arrangement' => $applied ? ['id' => $applied->id, 'name' => $applied->name, 'number_of_seats' => $applied->number_of_seats] : null,
            'capacity'        => $trip->capacity(),
            'booked_seats'    => $booked,
            'available_seats' => max(0, $trip->capacity() - $booked),
            'passenger_count'      => $manifest['passenger_count'],
            'checked_in_count'     => $manifest['checked_in_count'],
            'not_checked_in_count' => $manifest['not_checked_in_count'],
            'route' => $route ? [
                'id'          => $route->id,
                'origin'      => $this->formatLocation($route->origin),
                'destination' => $this->formatLocation($route->destination),
            ] : null,
            'vehicle_type' => $trip->vehicleType ? [
                'id'           => $trip->vehicleType->id,
                'vehicle_name' => $trip->vehicleType->vehicle_name,
            ] : null,
            'assignment' => [
                'assigned'    => (bool) ($trip->vehicle_id && $trip->driver_id),
                'assigned_at' => $trip->assigned_at,
                'vehicle'     => $trip->vehicle ? [
                    'id'                  => $trip->vehicle->id,
                    'registration_number' => $trip->vehicle->registration_number,
                    'vehicle_type'        => $trip->vehicle->vehicleType?->vehicle_name,
                    'status'              => $trip->vehicle->status,
                ] : null,
                'driver'      => $trip->driver ? [
                    'id'              => $trip->driver->id,
                    'name'            => $trip->driver->name,
                    'phone'           => $trip->driver->phone,
                    'licence_number'  => $trip->driver->licence_number,
                    'availability'    => $trip->driver->availability,
                    'trips_completed' => $trip->driver->trips_completed,
                ] : null,
            ],
            'operator' => $trip->tenant ? ['id' => $trip->tenant->id, 'name' => $trip->tenant->name] : null,
        ];

        if ($withManifest) {
            $data['manifest'] = $manifest['passengers'];
        }

        return $data;
    }

    private function formatLocation(?\App\Models\Location $location): ?array
    {
        if (! $location) return null;

        return ['id' => $location->id, 'name' => $location->name, 'city' => $location->city, 'state' => $location->state?->name];
    }
}
