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

    public function searchAvailableTrips(array $filters = [], int $perPage = 15)
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
        )->orderBy('departure_time')->paginate($perPage);
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
                'start_date'       => $data['start_date'],
                'end_date'        => $data['end_date'],
                'days_of_week'    => $data['days_of_week'],
                'departure_time'  => $data['return_departure_time'],
                'arrival_time'    => $data['return_arrival_time'] ?? null,
                'fare'            => $data['return_fare'] ?? $data['fare'],
                'status'          => 'active',
            ]);
        }

        $schedules = $this->generateSchedulesForGroup($group, $returnGroup);

        $group->load(['route.origin.state', 'route.destination.state', 'vehicleType.category', 'vehicleType.seatArrangementTypes', 'seatArrangement', 'tenant']);
        $returnGroup?->load(['route.origin.state', 'route.destination.state', 'vehicleType.category', 'vehicleType.seatArrangementTypes', 'seatArrangement', 'tenant']);

        return ['group' => $group, 'return_group' => $returnGroup, 'schedules' => $schedules];
    }

    public function generateSchedulesForGroup(ScheduleGroup $group, ?ScheduleGroup $returnGroup = null): \Illuminate\Support\Collection
    {
        $selectedDays = collect($group->days_of_week)->map(fn($d) => strtolower($d));
        $period       = CarbonPeriod::create($group->start_date, $group->end_date);
        $schedules    = collect();

        foreach ($period as $date) {
            if (! $selectedDays->contains(strtolower($date->englishDayOfWeek))) {
                continue;
            }

            $departure = Carbon::parse($date->format('Y-m-d') . ' ' . $group->departure_time);
            $arrival   = $this->resolveArrival($date, $group->arrival_time, $departure);

            $outbound = Schedule::create([
                'tenant_id'         => $group->tenant_id,
                'schedule_group_id' => $group->id,
                'route_id'          => $group->route_id,
                'vehicle_type_id'   => $group->vehicle_type_id,
                'seat_arrangement_type_id' => $group->seat_arrangement_type_id,
                'departure_time'    => $departure,
                'arrival_time'      => $arrival,
                'fare'              => $group->fare,
                'status'            => 'scheduled',
            ]);

            if ($returnGroup) {
                $retDeparture = Carbon::parse($date->format('Y-m-d') . ' ' . $returnGroup->departure_time);
                $retArrival   = $this->resolveArrival($date, $returnGroup->arrival_time, $retDeparture);

                $returnSchedule = Schedule::create([
                    'tenant_id'         => $returnGroup->tenant_id,
                    'schedule_group_id' => $returnGroup->id,
                    'route_id'          => $returnGroup->route_id,
                    'vehicle_type_id'   => $returnGroup->vehicle_type_id,
                    'seat_arrangement_type_id' => $returnGroup->seat_arrangement_type_id,
                    'departure_time'    => $retDeparture,
                    'arrival_time'      => $retArrival,
                    'fare'              => $returnGroup->fare,
                    'status'            => 'scheduled',
                ]);

                $outbound->update(['return_schedule_id' => $returnSchedule->id]);
                $outbound->return_schedule_id = $returnSchedule->id;
            }

            $schedules->push($outbound);
        }

        return $schedules;
    }

    private function resolveArrival(\Carbon\Carbon $date, ?string $arrivalTime, Carbon $departure): ?Carbon
    {
        if (! $arrivalTime) {
            return null;
        }
        $arrival = Carbon::parse($date->format('Y-m-d') . ' ' . $arrivalTime);
        if ($arrival->lte($departure)) {
            $arrival->addDay();
        }
        return $arrival;
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
            'tenant_id', 'route_id', 'vehicle_type_id', 'seat_arrangement_type_id',
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
            'route_id', 'vehicle_type_id', 'seat_arrangement_type_id', 'departure_time', 'arrival_time', 'fare', 'status',
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
