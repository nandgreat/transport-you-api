<?php

namespace App\Services;

use App\Models\Schedule;
use Illuminate\Support\Collection;

/**
 * Manages scheduled trips: concrete, dated schedules (one-off or recurring
 * instances) that carry at least one confirmed booking. Each trip moves through
 * an operational lifecycle — not_started → ongoing → completed (or cancelled) —
 * and exposes a passenger manifest with per-passenger check-in state.
 */
class ScheduledTripService
{
    public const STATUSES = ['not_started', 'ongoing', 'completed', 'cancelled'];

    private function with(): array
    {
        return [
            'route.origin.state', 'route.destination.state',
            'vehicleType.category', 'vehicleType.seatArrangementTypes', 'seatArrangement',
            'tenant', 'scheduleGroup',
        ];
    }

    /**
     * Confirmed bookings and their issued tickets — the source of truth for the
     * manifest and the passenger / check-in counts.
     */
    private function manifestRelations(): array
    {
        return [
            'bookings' => fn ($q) => $q->where('status', 'confirmed'),
            'bookings.tickets',
            'bookings.user',
        ];
    }

    public function list(?int $tenantId, array $filters = [], int $perPage = 15)
    {
        return Schedule::with(array_merge($this->with(), $this->manifestRelations()))
            // A schedule is a "scheduled trip" only once it has a confirmed booking.
            ->whereHas('bookings', fn ($q) => $q->where('status', 'confirmed'))
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->when($filters['trip_status'] ?? null,       fn ($q, $v) => $q->where('trip_status', $v))
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
        return Schedule::with(array_merge($this->with(), $this->manifestRelations()))
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->findOrFail($id);
    }

    public function updateStatus(?int $tenantId, string $id, string $status): Schedule
    {
        // Cancelling is a heavier action — fan it out to the schedule + bookings.
        if ($status === 'cancelled') {
            return $this->cancel($tenantId, $id);
        }

        $trip = $this->get($tenantId, $id);
        $trip->update(['trip_status' => $status]);

        return $trip;
    }

    public function cancel(?int $tenantId, string $id): Schedule
    {
        $trip = $this->get($tenantId, $id);

        // Mark the trip cancelled, drop it from public search, and cancel holds.
        $trip->update(['trip_status' => 'cancelled', 'status' => 'cancelled']);
        $trip->bookings()->whereIn('status', ['pending', 'confirmed'])->update(['status' => 'cancelled']);

        // Reload fresh so relations reflect the now-cancelled bookings.
        return $this->get($tenantId, $id);
    }

    /**
     * Build the passenger manifest for a trip from its confirmed bookings'
     * tickets, with each passenger's check-in state (a ticket is "checked in"
     * once it has been marked used at boarding).
     *
     * @return array{passenger_count:int, checked_in_count:int, not_checked_in_count:int, passengers:Collection}
     */
    public function manifest(Schedule $trip): array
    {
        $trip->loadMissing($this->manifestRelations());

        $passengers = collect($trip->bookings)->flatMap(function ($booking) {
            $tickets = collect($booking->tickets ?? []);

            // Confirmed bookings have one ticket per passenger; fall back to the
            // raw passengers array if tickets were never issued.
            if ($tickets->isNotEmpty()) {
                return $tickets->map(fn ($t) => [
                    'booking_id'        => $booking->id,
                    'booking_reference' => $booking->reference,
                    'ticket_code'       => $t->qr_code,
                    'name'              => $t->passenger_name,
                    'phone'             => $t->passenger_phone,
                    'seat_number'       => $t->seat_number,
                    'checked_in'        => $t->status === 'used',
                    'ticket_status'     => $t->status,
                ]);
            }

            return collect($booking->passengers ?? [])->map(fn ($p) => [
                'booking_id'        => $booking->id,
                'booking_reference' => $booking->reference,
                'ticket_code'       => null,
                'name'              => $p['name'] ?? null,
                'phone'             => $p['phone'] ?? null,
                'seat_number'       => $p['seat_number'] ?? null,
                'checked_in'        => false,
                'ticket_status'     => null,
            ]);
        })->sortBy(fn ($p) => $p['seat_number'] ?? PHP_INT_MAX)->values();

        $checkedIn = $passengers->where('checked_in', true)->count();

        return [
            'passenger_count'      => $passengers->count(),
            'checked_in_count'     => $checkedIn,
            'not_checked_in_count' => $passengers->count() - $checkedIn,
            'passengers'           => $passengers,
        ];
    }
}
