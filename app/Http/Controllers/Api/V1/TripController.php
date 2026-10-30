<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Schedule;
use App\Models\ScheduleGroup;
use App\Services\ScheduleService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

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

        $filters = $request->only(['origin_id', 'destination_id', 'origin_city', 'destination_city', 'departure_date', 'return_date', 'tenant_id']);

        // Concrete trips (one-off + materialised recurring) and virtual trips
        // expanded on the fly from recurring patterns, merged into one list.
        $concrete = $this->scheduleService->concreteTrips($filters)
            ->map(fn ($s) => $this->formatTrip($s));

        $virtual = $this->scheduleService->recurringOccurrences($filters)
            ->map(fn ($o) => $this->formatVirtualTrip($o['group'], $o['date']));

        $all = $concrete->concat($virtual)
            ->sortBy(fn ($t) => (string) $t['departure_time'])
            ->values();

        $perPage = 15;
        $page    = max(1, (int) $request->query('page', 1));

        $paginator = new LengthAwarePaginator(
            $all->forPage($page, $perPage)->values(),
            $all->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return $this->success($paginator, 'Available trips retrieved successfully');
    }

    public function show(string $id)
    {
        // Virtual (recurring) occurrence: id looks like "g{group}-{Y-m-d}".
        if ($occurrence = $this->parseOccurrenceId($id)) {
            [$group, $date] = $occurrence;
            return $this->success($this->formatVirtualTrip($group, $date), 'Trip details retrieved successfully');
        }

        $schedule = Schedule::with([
            'route.origin.state', 'route.destination.state', 'vehicleType.category', 'vehicleType.seatArrangementTypes', 'seatArrangement', 'tenant',
            'returnSchedule.route.origin.state', 'returnSchedule.route.destination.state', 'returnSchedule.vehicleType.category',
        ])->where('status', 'scheduled')
          ->where('departure_time', '>', now())
          ->findOrFail($id);

        return $this->success($this->formatTrip($schedule), 'Trip details retrieved successfully');
    }

    /**
     * Resolve a virtual trip id ("g{group}-{Y-m-d}") to its pattern and date.
     * Returns [ScheduleGroup, string $date] or null if $id is not composite.
     */
    private function parseOccurrenceId(string $id): ?array
    {
        if (! preg_match('/^g(\d+)-(\d{4}-\d{2}-\d{2})$/', $id, $m)) {
            return null;
        }

        $group = ScheduleGroup::with(['route.origin.state', 'route.destination.state', 'vehicleType.category', 'vehicleType.seatArrangementTypes', 'seatArrangement', 'tenant'])
            ->where('status', 'active')
            ->findOrFail($m[1]);

        abort_if(! $group->occursOn(Carbon::parse($m[2])), 404, 'This recurring schedule does not run on the selected date.');

        return [$group, $m[2]];
    }

    /**
     * Seat map for a schedule: each seat marked available or unavailable.
     */
    public function seats(string $id)
    {
        // Virtual (recurring) occurrence id: "g{group}-{Y-m-d}".
        if ($occurrence = $this->parseOccurrenceId($id)) {
            [$group, $date] = $occurrence;
            return $this->success($this->occurrenceSeats($group, $date), 'Seat availability retrieved successfully');
        }

        $schedule = Schedule::with(['vehicleType.seatArrangementTypes', 'seatArrangement'])
            ->where('status', 'scheduled')
            ->where('departure_time', '>', now())
            ->findOrFail($id);

        return $this->success([
            'schedule_id'            => $schedule->id,
            'schedule_group_id'      => $schedule->schedule_group_id,
            'travel_date'            => $schedule->departure_time?->format('Y-m-d'),
            'seat_selection_enabled' => (bool) $schedule->seat_selection_enabled,
            'capacity'               => $schedule->capacity(),
            'available_seats'        => $schedule->availableSeats(),
            'seats'                  => $schedule->seat_selection_enabled
                ? collect($schedule->seatMap())->map(fn ($s) => [
                    'number' => $s['number'],
                    'status' => $s['available'] ? 'available' : 'unavailable',
                ])->values()
                : [],
        ], 'Seat availability retrieved successfully');
    }

    /**
     * Seat map for one occurrence of a recurring pattern (no schedule row yet).
     */
    public function recurringSeats(Request $request, string $group)
    {
        $request->validate(['travel_date' => 'required|date_format:Y-m-d']);
        $date = $request->query('travel_date');

        $group = ScheduleGroup::with(['vehicleType.seatArrangementTypes', 'seatArrangement'])
            ->where('status', 'active')
            ->findOrFail($group);

        abort_if(! $group->occursOn(Carbon::parse($date)), 422, 'This recurring schedule does not run on the selected date.');

        return $this->success($this->occurrenceSeats($group, $date), 'Seat availability retrieved successfully');
    }

    private function occurrenceSeats(ScheduleGroup $group, string $date): array
    {
        return [
            'schedule_group_id'      => $group->id,
            'travel_date'            => $date,
            'seat_selection_enabled' => (bool) $group->seat_selection_enabled,
            'capacity'               => $group->capacity(),
            'available_seats'        => $group->availableSeatsFor($date),
            'seats'                  => $group->seat_selection_enabled
                ? collect($group->seatMapFor($date))->map(fn ($s) => [
                    'number' => $s['number'],
                    'status' => $s['available'] ? 'available' : 'unavailable',
                ])->values()
                : [],
        ];
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
            'id'                => $schedule->id,
            'type'              => $schedule->schedule_group_id ? 'recurring_instance' : 'one_off',
            'schedule_id'       => $schedule->id,
            'schedule_group_id' => $schedule->schedule_group_id,
            'travel_date'       => $schedule->departure_time?->format('Y-m-d'),
            'departure_time'  => $schedule->departure_time,
            'arrival_time'    => $schedule->arrival_time,
            'fare'            => $schedule->fare,
            'status'          => $schedule->status,
            'seat_selection_enabled' => (bool) $schedule->seat_selection_enabled,
            'capacity'        => $schedule->capacity(),
            'available_seats' => $schedule->availableSeats(),
            'seats'           => $schedule->seat_selection_enabled ? $schedule->seatMap() : [],
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

    /**
     * A virtual trip for one occurrence of a recurring pattern on $date.
     * It has no schedule_id yet — booking it materialises a concrete trip.
     */
    private function formatVirtualTrip(ScheduleGroup $group, string $date): array
    {
        $d        = Carbon::parse($date);
        $vt       = $group->vehicleType;
        $route    = $group->route;
        $applied  = $group->resolveSeatArrangement();
        $departure = $group->departureAt($d);
        $arrival   = $group->arrivalAt($d);

        return [
            'id'                => "g{$group->id}-{$date}",
            'type'              => 'recurring',
            'schedule_id'       => null,
            'schedule_group_id' => $group->id,
            'travel_date'       => $date,
            'departure_time'    => $departure->format('Y-m-d H:i:s'),
            'arrival_time'      => $arrival?->format('Y-m-d H:i:s'),
            'fare'              => $group->fare,
            'status'            => 'scheduled',
            'seat_selection_enabled' => (bool) $group->seat_selection_enabled,
            'capacity'          => $group->capacity(),
            'available_seats'   => $group->availableSeatsFor($date),
            'seats'             => $group->seat_selection_enabled
                ? collect($group->seatMapFor($date))->map(fn ($s) => [
                    'number' => $s['number'],
                    'available' => $s['available'],
                ])->values()
                : [],
            'seat_arrangement'  => $applied ? [
                'id'                => $applied->id,
                'name'              => $applied->name,
                'number_of_seats'   => $applied->number_of_seats,
                'arrangement_image' => $applied->arrangement_image,
            ] : null,
            'is_round_trip'     => false,
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
            ] : null,
            'operator' => $group->tenant ? [
                'id'   => $group->tenant->id,
                'name' => $group->tenant->name,
                'logo_url' => $group->tenant->logo_url,
            ] : null,
            'recurring' => [
                'start_date'   => $group->start_date->format('Y-m-d'),
                'end_date'     => $group->end_date->format('Y-m-d'),
                'days_of_week' => $group->days_of_week,
            ],
            'return_trip' => null,
        ];
    }
}
