<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Schedule;
use App\Services\ScheduleService;
use Illuminate\Http\Request;

class TripController extends Controller
{
    public function __construct(protected ScheduleService $scheduleService) {}

    public function index(Request $request)
    {
        $request->validate([
            'origin_id'        => 'nullable|integer|exists:locations,id',
            'destination_id'   => 'nullable|integer|exists:locations,id',
            'origin_city'      => 'nullable|string|max:100',
            'destination_city' => 'nullable|string|max:100',
            'departure_date'   => 'nullable|date_format:Y-m-d',
            'return_date'      => 'nullable|date_format:Y-m-d|after_or_equal:departure_date',
            'tenant_id'        => 'nullable|integer|exists:tenants,id',
        ]);

        $filters   = $request->only(['origin_id', 'destination_id', 'origin_city', 'destination_city', 'departure_date', 'return_date', 'tenant_id']);
        $schedules = $this->scheduleService->searchAvailableTrips($filters);

        $schedules->setCollection(
            $schedules->getCollection()->map(fn($s) => $this->formatTrip($s))
        );

        return $this->success($schedules, 'Available trips retrieved successfully');
    }

    public function show(string $id)
    {
        $schedule = Schedule::with([
            'route.origin.state', 'route.destination.state', 'vehicleType.category', 'vehicleType.seatArrangementTypes', 'seatArrangement', 'tenant',
            'returnSchedule.route.origin.state', 'returnSchedule.route.destination.state', 'returnSchedule.vehicleType.category',
        ])->where('status', 'scheduled')
          ->where('departure_time', '>', now())
          ->findOrFail($id);

        return $this->success($this->formatTrip($schedule), 'Trip details retrieved successfully');
    }

    private function formatLocation(?\App\Models\Location $location): ?array
    {
        if (! $location) return null;

        return [
            'id'    => $location->id,
            'name'  => $location->name,
            'city'  => $location->city,
            'state' => $location->state?->name,
        ];
    }

    private function formatTrip(Schedule $schedule, bool $includeReturn = true): array
    {
        $vt       = $schedule->vehicleType;
        $route    = $schedule->route;
        $applied  = $schedule->resolveSeatArrangement();

        $data = [
            'id'              => $schedule->id,
            'departure_time'  => $schedule->departure_time,
            'arrival_time'    => $schedule->arrival_time,
            'fare'            => $schedule->fare,
            'status'          => $schedule->status,
            'capacity'        => $schedule->capacity(),
            'available_seats' => $schedule->availableSeats(),
            'seats'           => $schedule->seatMap(),
            'seat_arrangement' => $applied ? [
                'id'                => $applied->id,
                'name'              => $applied->name,
                'number_of_seats'   => $applied->number_of_seats,
                'arrangement_image' => $applied->arrangement_image,
            ] : null,
            'is_round_trip'   => $schedule->return_schedule_id !== null,
            'route' => $route ? [
                'id'                 => $route->id,
                'distance'           => $route->distance,
                'estimated_duration' => $route->estimated_duration,
                'origin'             => $this->formatLocation($route->origin),
                'destination'        => $this->formatLocation($route->destination),
            ] : null,
            'vehicle_type' => $vt ? [
                'id'           => $vt->id,
                'vehicle_name' => $vt->vehicle_name,
                'year'         => $vt->year,
                'image_url'    => $vt->image_url,
                'category'     => $vt->category,
                'seat_arrangements' => $vt->relationLoaded('seatArrangementTypes')
                    ? $vt->seatArrangementTypes->map(fn ($sa) => [
                        'id'                => $sa->id,
                        'name'              => $sa->name,
                        'number_of_seats'   => $sa->number_of_seats,
                        'arrangement_image' => $sa->arrangement_image,
                        'is_default'        => (bool) $sa->pivot->is_default,
                    ])->values()
                    : [],
            ] : null,
            'operator' => $schedule->tenant ? [
                'id'   => $schedule->tenant->id,
                'name' => $schedule->tenant->name,
                'logo_url' => $schedule->tenant->logo_url,
            ] : null,
        ];

        if ($includeReturn && $schedule->relationLoaded('returnSchedule') && $schedule->returnSchedule) {
            $data['return_trip'] = $this->formatTrip($schedule->returnSchedule, false);
        } else {
            $data['return_trip'] = null;
        }

        return $data;
    }
}
