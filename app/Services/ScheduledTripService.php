<?php

namespace App\Services;

use App\Models\Schedule;

/**
 * Manages concrete scheduled trips: one-off schedules and recurring instances
 * materialised by bookings.
 */
class ScheduledTripService
{
    private function with(): array
    {
        return [
            'route.origin.state', 'route.destination.state',
            'vehicleType.category', 'vehicleType.seatArrangementTypes', 'seatArrangement',
            'tenant', 'scheduleGroup',
        ];
    }

    public function list(?int $tenantId, array $filters = [], int $perPage = 15)
    {
        return Schedule::with($this->with())
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->when($filters['status'] ?? null,            fn ($q, $v) => $q->where('status', $v))
            ->when($filters['schedule_group_id'] ?? null, fn ($q, $v) => $q->where('schedule_group_id', $v))
            ->when(($filters['source'] ?? null) === 'one_off',   fn ($q) => $q->whereNull('schedule_group_id'))
            ->when(($filters['source'] ?? null) === 'recurring', fn ($q) => $q->whereNotNull('schedule_group_id'))
            ->when($filters['departure_date'] ?? null,    fn ($q, $v) => $q->whereDate('departure_time', $v))
            ->when($filters['from_date'] ?? null,         fn ($q, $v) => $q->whereDate('departure_time', '>=', $v))
            ->when($filters['to_date'] ?? null,           fn ($q, $v) => $q->whereDate('departure_time', '<=', $v))
            ->when($filters['origin_id'] ?? null,         fn ($q, $v) => $q->whereHas('route', fn ($r) => $r->where('origin_id', $v)))
            ->when($filters['destination_id'] ?? null,    fn ($q, $v) => $q->whereHas('route', fn ($r) => $r->where('destination_id', $v)))
            ->orderBy('departure_time')
            ->paginate($perPage);
    }

    public function get(?int $tenantId, string $id): Schedule
    {
        return Schedule::with(array_merge($this->with(), ['bookings' => fn ($q) => $q->whereIn('status', ['pending', 'confirmed']), 'bookings.user']))
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->findOrFail($id);
    }

    public function updateStatus(?int $tenantId, string $id, string $status): Schedule
    {
        $trip = $this->get($tenantId, $id);
        $trip->update(['status' => $status]);
        return $trip;
    }

    public function cancel(?int $tenantId, string $id): Schedule
    {
        $trip = $this->get($tenantId, $id);
        $trip->update(['status' => 'cancelled']);
        $trip->bookings()->whereIn('status', ['pending', 'confirmed'])->update(['status' => 'cancelled']);
        return $trip;
    }
}
