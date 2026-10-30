<?php

namespace App\Services;

use App\Models\Schedule;
use App\Models\ScheduleGroup;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class ScheduleService
{
    public function __construct(protected RouteResolverService $routeResolver) {}

    private function withRelations(): \Illuminate\Database\Eloquent\Builder
    {
        return Schedule::with([
            'route.origin.state',
            'route.destination.state',
            'vehicleType.category', 'vehicleType.seatArrangementTypes', 'seatArrangement',
            'tenant',
            'returnSchedule.route.origin.state',
            'returnSchedule.route.destination.state',
            'returnSchedule.vehicleType.category',
            'scheduleGroup',
        ]);
    }

    private function applyFilters(\Illuminate\Database\Eloquent\Builder $q, array $filters): \Illuminate\Database\Eloquent\Builder
    {
        return $q
            ->when($filters['origin_id'] ?? null,       fn($q, $v) => $q->whereHas('route', fn($r) => $r->where('origin_id', $v)))
            ->when($filters['destination_id'] ?? null,  fn($q, $v) => $q->whereHas('route', fn($r) => $r->where('destination_id', $v)))
            ->when($filters['origin_city'] ?? null,      fn($q, $v) => $q->whereHas('route.origin',      fn($r) => $r->whereRaw('LOWER(city) = ?', [strtolower(trim($v))])))
            ->when($filters['destination_city'] ?? null, fn($q, $v) => $q->whereHas('route.destination', fn($r) => $r->whereRaw('LOWER(city) = ?', [strtolower(trim($v))])))
            ->when($filters['departure_date'] ?? null,  fn($q, $v) => $q->whereDate('departure_time', $v))
            ->when($filters['status'] ?? null,          fn($q, $v) => $q->where('status', $v))
            ->when(isset($filters['round_trip']) && $filters['round_trip'], fn($q) => $q->whereNotNull('return_schedule_id'));
    }

    public function getSchedules(?int $tenantId, array $filters = [], int $perPage = 15)
    {
        return $this->applyFilters(
            $this->withRelations()->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId)),
            $filters
        )->orderBy('departure_time')->paginate($perPage);
    }

    /**
     * Standalone one-off schedules (excludes recurring instances that belong to
     * a group — those are represented by the group row instead).
     */
    public function getStandaloneSchedules(?int $tenantId, array $filters = []): \Illuminate\Support\Collection
    {
        return $this->applyFilters(
            $this->withRelations()
                ->whereNull('schedule_group_id')
                ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId)),
            $filters
        )->orderByDesc('created_at')->get();
    }

    /** Recurring schedule patterns (groups), for the unified management list. */
    public function getScheduleGroups(?int $tenantId, array $filters = []): \Illuminate\Support\Collection
    {
        return ScheduleGroup::with([
            'route.origin.state',
            'route.destination.state',
            'vehicleType.category',
            'vehicleType.seatArrangementTypes',
            'seatArrangement',
            'tenant',
        ])
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->when($filters['origin_city'] ?? null, fn($q, $v) =>
                $q->whereHas('route.origin', fn($r) => $r->whereRaw('LOWER(city) = ?', [strtolower(trim($v))])))
            ->when($filters['destination_city'] ?? null, fn($q, $v) =>
                $q->whereHas('route.destination', fn($r) => $r->whereRaw('LOWER(city) = ?', [strtolower(trim($v))])))
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Concrete (materialised) trips matching the search: one-off schedules and
     * recurring instances that have already been booked into existence.
     *
     * @return \Illuminate\Support\Collection<int, Schedule>
     */
    public function concreteTrips(array $filters = []): \Illuminate\Support\Collection
    {
        return $this->applyFilters(
            $this->withRelations()
                ->where('status', 'scheduled')
                ->where('departure_time', '>', now())
                ->when($filters['tenant_id'] ?? null, fn($q, $v) => $q->where('tenant_id', $v))
                ->when($filters['return_date'] ?? null, fn($q, $v) =>
                    $q->whereHas('returnSchedule', fn($r) => $r->whereDate('departure_time', $v))
                ),
            $filters
        )->orderBy('departure_time')->get();
    }

    /**
     * Virtual trips expanded from recurring patterns for the search window.
     * A pattern matches when the searched date (or, with no date, the window)
     * falls inside its interval on a selected weekday. Occurrences already
     * materialised and still scheduled are skipped (the concrete list covers them).
     *
     * @return \Illuminate\Support\Collection<int, array{group: ScheduleGroup, date: string}>
     */
    public function recurringOccurrences(array $filters = []): \Illuminate\Support\Collection
    {
        $date  = $filters['departure_date'] ?? null;
        $today = Carbon::today();

        $groups = ScheduleGroup::with([
                'route.origin.state', 'route.destination.state',
                'vehicleType.category', 'vehicleType.seatArrangementTypes', 'seatArrangement', 'tenant',
            ])
            ->where('status', 'active')
            ->whereDate('end_date', '>=', $today)
            ->when($filters['tenant_id'] ?? null,       fn($q, $v) => $q->where('tenant_id', $v))
            ->when($filters['origin_id'] ?? null,       fn($q, $v) => $q->whereHas('route', fn($r) => $r->where('origin_id', $v)))
            ->when($filters['destination_id'] ?? null,  fn($q, $v) => $q->whereHas('route', fn($r) => $r->where('destination_id', $v)))
            ->when($filters['origin_city'] ?? null,      fn($q, $v) => $q->whereHas('route.origin',      fn($r) => $r->whereRaw('LOWER(city) = ?', [strtolower(trim($v))])))
            ->when($filters['destination_city'] ?? null, fn($q, $v) => $q->whereHas('route.destination', fn($r) => $r->whereRaw('LOWER(city) = ?', [strtolower(trim($v))])))
            ->get();

        $out = collect();

        foreach ($groups as $group) {
            if ($date) {
                $d = Carbon::parse($date);
                if ($d->lt($today) || ! $group->occursOn($d)) {
                    continue;
                }
                $dates = collect([$d]);
            } else {
                $next  = $group->nextOccurrence($today);
                $dates = $next ? collect([$next]) : collect();
            }

            foreach ($dates as $d) {
                $ds  = $d->format('Y-m-d');
                $mat = $group->materializedScheduleFor($ds);
                if ($mat && $mat->status === 'scheduled') {
                    continue; // already a concrete trip
                }
                $out->push(['group' => $group, 'date' => $ds]);
            }
        }

        return $out;
    }

    public function createRecurringSchedules(?int $tenantId, array $data): array
    {
        if ($tenantId) {
            $data['route_id']  = $this->routeResolver->resolve($tenantId, $data);
            $data['tenant_id'] = $tenantId;
        }

        $this->assertArrangementForType($data['vehicle_type_id'], $data['seat_arrangement_type_id'] ?? null);
        $this->assertCapacityDeterminable($data['vehicle_type_id'], $data['seat_arrangement_type_id'] ?? null);

        $group = ScheduleGroup::create([
            'tenant_id'        => $data['tenant_id'],
            'route_id'         => $data['route_id'],
            'vehicle_type_id'  => $data['vehicle_type_id'],
            'seat_arrangement_type_id' => $data['seat_arrangement_type_id'] ?? null,
            'seat_selection_enabled'   => $data['seat_selection_enabled'] ?? true,
            'start_date'       => $data['start_date'],
            'end_date'        => $data['end_date'],
            'days_of_week'    => $data['days_of_week'],
            'departure_time'  => $data['departure_time'],
            'arrival_time'    => $data['arrival_time'] ?? null,
            'fare'            => $data['fare'],
            'status'          => 'active',
        ]);

        $returnGroup = null;
        if (! empty($data['is_round_trip'])) {
            $returnRouteId = $this->routeResolver->resolve($data['tenant_id'], [
                'origin_id'      => $group->route->destination_id,
                'destination_id' => $group->route->origin_id,
            ]);

            $returnGroup = ScheduleGroup::create([
                'tenant_id'        => $data['tenant_id'],
                'route_id'         => $returnRouteId,
                'vehicle_type_id'  => $data['vehicle_type_id'],
                'seat_arrangement_type_id' => $data['seat_arrangement_type_id'] ?? null,
                'seat_selection_enabled'   => $data['seat_selection_enabled'] ?? true,
                'start_date'       => $data['start_date'],
                'end_date'        => $data['end_date'],
                'days_of_week'    => $data['days_of_week'],
                'departure_time'  => $data['return_departure_time'],
                'arrival_time'    => $data['return_arrival_time'] ?? null,
                'fare'            => $data['return_fare'] ?? $data['fare'],
                'status'          => 'active',
            ]);
        }

        // Recurring schedules are stored as a single pattern record — no per-day
        // schedule rows are created. Concrete trips are materialised lazily when
        // a customer books a specific date (see materializeOccurrence()).

        $group->load(['route.origin.state', 'route.destination.state', 'vehicleType.category', 'vehicleType.seatArrangementTypes', 'seatArrangement', 'tenant']);
        $returnGroup?->load(['route.origin.state', 'route.destination.state', 'vehicleType.category', 'vehicleType.seatArrangementTypes', 'seatArrangement', 'tenant']);

        return ['group' => $group, 'return_group' => $returnGroup];
    }

    /**
     * Turn a recurring pattern occurrence into a concrete schedule (scheduled
     * trip). Idempotent per (pattern, date) — reuses an existing instance.
     */
    public function materializeOccurrence(ScheduleGroup $group, string $date): Schedule
    {
        $dateC = Carbon::parse($date);

        abort_if(! $group->occursOn($dateC), 422, 'This recurring schedule does not run on the selected date.');
        abort_if($dateC->copy()->startOfDay()->lt(Carbon::today()), 422, 'Cannot book a trip in the past.');

        return Schedule::firstOrCreate(
            [
                'schedule_group_id' => $group->id,
                'departure_time'    => $group->departureAt($dateC),
            ],
            [
                'tenant_id'                => $group->tenant_id,
                'route_id'                 => $group->route_id,
                'vehicle_type_id'          => $group->vehicle_type_id,
                'seat_arrangement_type_id' => $group->seat_arrangement_type_id,
                'seat_selection_enabled'   => $group->seat_selection_enabled ?? true,
                'arrival_time'             => $group->arrivalAt($dateC),
                'fare'                     => $group->fare,
                'status'                   => 'scheduled',
            ]
        );
    }

    public function createSchedule(?int $tenantId, array $data)
    {
        if ($tenantId) {
            $data['route_id']  = $this->routeResolver->resolve($tenantId, $data);
            $data['tenant_id'] = $tenantId;
        }

        $this->assertArrangementForType($data['vehicle_type_id'] ?? null, $data['seat_arrangement_type_id'] ?? null);
        $this->assertCapacityDeterminable($data['vehicle_type_id'] ?? null, $data['seat_arrangement_type_id'] ?? null);

        $scheduleData = array_intersect_key($data, array_flip([
            'tenant_id', 'route_id', 'vehicle_type_id', 'seat_arrangement_type_id', 'seat_selection_enabled',
            'departure_time', 'arrival_time', 'fare', 'status',
        ]));

        $schedule = Schedule::create($scheduleData);

        if (! empty($data['is_round_trip'])) {
            $returnSchedule = $this->createReturnSchedule($tenantId, $schedule, $data);
            $schedule->update(['return_schedule_id' => $returnSchedule->id]);
            $schedule->return_schedule_id = $returnSchedule->id;
        }

        return $schedule->load([
            'route.origin.state', 'route.destination.state', 'vehicleType.category', 'vehicleType.seatArrangementTypes', 'seatArrangement', 'tenant',
            'returnSchedule.route.origin.state', 'returnSchedule.route.destination.state', 'returnSchedule.vehicleType.category',
            'scheduleGroup',
        ]);
    }

    private function createReturnSchedule(int $tenantId, Schedule $outbound, array $data): Schedule
    {
        $returnRouteId = $this->routeResolver->resolve($tenantId, [
            'origin_id'      => $outbound->route->destination_id,
            'destination_id' => $outbound->route->origin_id,
        ]);

        return Schedule::create([
            'tenant_id'        => $outbound->tenant_id,
            'route_id'         => $returnRouteId,
            'vehicle_type_id'  => $outbound->vehicle_type_id,
            'seat_arrangement_type_id' => $outbound->seat_arrangement_type_id,
            'seat_selection_enabled'   => $outbound->seat_selection_enabled,
            'departure_time'   => $data['return_departure_time'],
            'arrival_time'     => $data['return_arrival_time'] ?? null,
            'fare'             => $data['return_fare'] ?? $outbound->fare,
            'status'           => 'scheduled',
        ]);
    }

    public function getSchedule(?int $tenantId, string $id)
    {
        return Schedule::with([
            'route.origin.state', 'route.destination.state', 'vehicleType.category', 'vehicleType.seatArrangementTypes', 'seatArrangement', 'tenant',
            'returnSchedule.route.origin.state', 'returnSchedule.route.destination.state', 'returnSchedule.vehicleType.category',
            'scheduleGroup',
        ])->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))->findOrFail($id);
    }

    public function updateSchedule(?int $tenantId, string $id, array $data)
    {
        $schedule = $this->getSchedule($tenantId, $id);

        $routeFields = ['route_id', 'origin_id', 'destination_id', 'origin_city', 'destination_city',
                        'external_route_ref', 'distance', 'estimated_duration'];

        if ($tenantId && array_intersect_key($data, array_flip($routeFields))) {
            $data['route_id'] = $this->routeResolver->resolve($tenantId, array_merge(
                ['route_id' => $schedule->route_id],
                $data
            ));
        }

        if (array_key_exists('seat_arrangement_type_id', $data)) {
            $this->assertArrangementForType(
                $data['vehicle_type_id'] ?? $schedule->vehicle_type_id,
                $data['seat_arrangement_type_id']
            );
        }

        $scheduleData = array_intersect_key($data, array_flip([
            'route_id', 'vehicle_type_id', 'seat_arrangement_type_id', 'seat_selection_enabled',
            'departure_time', 'arrival_time', 'fare', 'status',
        ]));

        $schedule->update($scheduleData);
        return $schedule->load(['route.origin.state', 'route.destination.state', 'vehicleType.category', 'vehicleType.seatArrangementTypes', 'seatArrangement', 'tenant']);
    }

    /**
     * A chosen seat arrangement must be one the vehicle type actually offers.
     */
    private function assertArrangementForType(?int $vehicleTypeId, ?int $arrangementId): void
    {
        if (! $arrangementId || ! $vehicleTypeId) {
            return;
        }

        $attached = \Illuminate\Support\Facades\DB::table('seat_arrangement_type_vehicle_type')
            ->where('vehicle_type_id', $vehicleTypeId)
            ->where('seat_arrangement_type_id', $arrangementId)
            ->exists();

        abort_if(! $attached, 422, 'The selected seat arrangement is not available for this vehicle type.');
    }

    /**
     * Capacity is derived from the seat arrangement, so a schedule can only be
     * created when one can be resolved: either chosen explicitly, or available
     * as the vehicle type's default.
     */
    private function assertCapacityDeterminable(?int $vehicleTypeId, ?int $chosenId): void
    {
        if (! $vehicleTypeId || $chosenId) {
            return;
        }

        $hasArrangement = \Illuminate\Support\Facades\DB::table('seat_arrangement_type_vehicle_type')
            ->where('vehicle_type_id', $vehicleTypeId)
            ->exists();

        abort_if(
            ! $hasArrangement,
            422,
            'Cannot determine capacity: select a seat_arrangement_type_id or add at least one seat arrangement to the vehicle type.'
        );
    }

    public function deleteSchedule(?int $tenantId, string $id)
    {
        $schedule = $this->getSchedule($tenantId, $id);
        $schedule->delete();
        return true;
    }
}
