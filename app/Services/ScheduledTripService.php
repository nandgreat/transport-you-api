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
            'vehicle.vehicleType', 'driver',
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

        // A trip can only start once both a vehicle and a driver are assigned.
        if ($status === 'ongoing' && (! $trip->vehicle_id || ! $trip->driver_id)) {
            abort(422, 'Assign a vehicle and a driver before starting this trip.');
        }

        $previous = $trip->trip_status;
        $trip->update(['trip_status' => $status]);

        // Keep the assigned driver's availability in step with the trip, and
        // credit a completed trip to the driver's record (once per completion).
        if ($trip->driver) {
            if ($status === 'ongoing') {
                $trip->driver->update(['availability' => 'on_trip']);
            } elseif ($status === 'completed') {
                $trip->driver->update(['availability' => 'available']);
                if ($previous !== 'completed') {
                    $trip->driver->increment('trips_completed');
                }
            }
        }

        return $trip;
    }

    public function cancel(?int $tenantId, string $id): Schedule
    {
        $trip = $this->get($tenantId, $id);

        // Mark the trip cancelled, drop it from public search, and cancel holds.
        $trip->update(['trip_status' => 'cancelled', 'status' => 'cancelled']);
        $trip->bookings()->whereIn('status', ['pending', 'confirmed'])->update(['status' => 'cancelled']);

        // Free up the assigned driver.
        if ($trip->driver) {
            $trip->driver->update(['availability' => 'available']);
        }

        // Reload fresh so relations reflect the now-cancelled bookings.
        return $this->get($tenantId, $id);
    }

    /**
     * Assign a physical vehicle and a driver to a trip. The vehicle must match
     * the trip's vehicle type and belong to the tenant (enforced by the request);
     * here we additionally guard against double-booking a vehicle or driver that
     * is already committed to another trip over an overlapping time window.
     */
    public function assign(?int $tenantId, string $id, int $vehicleId, int $driverId): Schedule
    {
        $trip = $this->get($tenantId, $id);

        $this->assertNoConflict($trip, 'vehicle_id', $vehicleId, 'vehicle');
        $this->assertNoConflict($trip, 'driver_id', $driverId, 'driver');

        $trip->update([
            'vehicle_id'  => $vehicleId,
            'driver_id'   => $driverId,
            'assigned_at' => now(),
        ]);

        return $this->get($tenantId, $id);
    }

    /**
     * Clear the vehicle/driver assignment from a trip.
     */
    public function unassign(?int $tenantId, string $id): Schedule
    {
        $trip = $this->get($tenantId, $id);

        $trip->update([
            'vehicle_id'  => null,
            'driver_id'   => null,
            'assigned_at' => null,
        ]);

        return $this->get($tenantId, $id);
    }

    /**
     * Reject an assignment when the vehicle or driver is already committed to
     * another non-cancelled trip whose [departure, arrival] window overlaps.
     */
    private function assertNoConflict(Schedule $trip, string $column, int $resourceId, string $label): void
    {
        $departure = $trip->departure_time;
        $arrival   = $trip->arrival_time;

        $conflict = Schedule::where('id', '!=', $trip->id)
            ->where($column, $resourceId)
            ->where('status', '!=', 'cancelled')
            ->when($departure && $arrival, function ($q) use ($departure, $arrival) {
                // Two windows overlap when each starts before the other ends.
                $q->where('departure_time', '<', $arrival)
                  ->where('arrival_time', '>', $departure);
            })
            ->exists();

        abort_if($conflict, 422, "The selected {$label} is already assigned to another trip during this time.");
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
