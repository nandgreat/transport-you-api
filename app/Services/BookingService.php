<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Schedule;
use Illuminate\Support\Facades\DB;

class BookingService
{
    private function bookingWith(): array
    {
        return [
            'schedule.route.origin.state',
            'schedule.route.destination.state',
            'schedule.vehicleType.category',
            'schedule.vehicleType.seatArrangementTypes',
            'schedule.seatArrangement',
            'schedule.tenant',
            'returnBooking.schedule.route.origin.state',
            'returnBooking.schedule.route.destination.state',
            'returnBooking.schedule.vehicleType.category',
            'returnBooking.schedule.vehicleType.seatArrangementTypes',
            'returnBooking.schedule.seatArrangement',
            'user',
            'tenant',
        ];
    }

    public function listBookings(int $userId, ?int $tenantId, bool $isSuperAdmin, array $filters = [], int $perPage = 15)
    {
        return Booking::with($this->bookingWith())
            ->when(! $isSuperAdmin && $tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->when(! $isSuperAdmin && ! $tenantId, fn($q) => $q->where('user_id', $userId))
            ->when($filters['status'] ?? null, fn($q, $v) => $q->where('status', $v))
            ->when($filters['schedule_id'] ?? null, fn($q, $v) => $q->where('schedule_id', $v))
            ->latest()
            ->paginate($perPage);
    }

    public function getBooking(int $userId, ?int $tenantId, bool $isSuperAdmin, string $id): Booking
    {
        return Booking::with($this->bookingWith())
            ->when(! $isSuperAdmin && $tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->when(! $isSuperAdmin && ! $tenantId, fn($q) => $q->where('user_id', $userId))
            ->findOrFail($id);
    }

    public function createBooking(int $userId, array $data): Booking
    {
        return DB::transaction(function () use ($userId, $data) {
            $schedule   = Schedule::with(['route', 'vehicleType.seatArrangementTypes', 'seatArrangement'])->findOrFail($data['schedule_id']);
            $passengers = $data['passengers'];
            $seats      = count($passengers);

            $this->assertSeatsSelectable($schedule, $this->seatNumbers($passengers));

            $outbound = Booking::create([
                'tenant_id'    => $schedule->tenant_id,
                'user_id'      => $userId,
                'schedule_id'  => $schedule->id,
                'total_amount' => $schedule->fare * $seats,
                'passengers'   => $passengers,
                'notes'        => $data['notes'] ?? null,
                'status'       => $data['status'] ?? 'pending',
            ]);

            if (! empty($data['is_round_trip']) && ! empty($data['return_schedule_id'])) {
                $returnSchedule = Schedule::with(['route', 'vehicleType.seatArrangementTypes', 'seatArrangement'])->findOrFail($data['return_schedule_id']);

                $this->assertSeatsSelectable($returnSchedule, $this->seatNumbers($passengers));

                $returnBooking = Booking::create([
                    'tenant_id'    => $returnSchedule->tenant_id,
                    'user_id'      => $userId,
                    'schedule_id'  => $returnSchedule->id,
                    'total_amount' => $returnSchedule->fare * $seats,
                    'passengers'   => $passengers,
                    'notes'        => $data['notes'] ?? null,
                    'status'       => $data['status'] ?? 'pending',
                ]);

                $outbound->update(['return_booking_id' => $returnBooking->id]);
                $outbound->return_booking_id = $returnBooking->id;
            }

            return $outbound->load($this->bookingWith());
        });
    }

    public function updateStatus(int $userId, ?int $tenantId, bool $isSuperAdmin, string $id, array $data): Booking
    {
        $booking = $this->getBooking($userId, $tenantId, $isSuperAdmin, $id);
        $booking->update(array_filter([
            'status' => $data['status'],
            'notes'  => $data['notes'] ?? $booking->notes,
        ]));

        return $booking->load($this->bookingWith());
    }

    public function cancelBooking(int $userId, string $id): Booking
    {
        $booking = Booking::where('user_id', $userId)->findOrFail($id);

        abort_if(
            in_array($booking->status, ['confirmed', 'cancelled']),
            422,
            'Only pending bookings can be cancelled by the customer.'
        );

        $booking->update(['status' => 'cancelled']);

        // Cancel the linked return booking too if it exists
        if ($booking->return_booking_id) {
            Booking::where('id', $booking->return_booking_id)
                ->where('status', 'pending')
                ->update(['status' => 'cancelled']);
        }

        return $booking->load($this->bookingWith());
    }

    /**
     * Extract the selected seat numbers from a passengers payload.
     *
     * @return array<int>
     */
    private function seatNumbers(array $passengers): array
    {
        return collect($passengers)
            ->pluck('seat_number')
            ->map(fn ($s) => (int) $s)
            ->all();
    }

    /**
     * Ensure the requested seat numbers are valid, unique and still free
     * on the given schedule.
     *
     * @param array<int> $seatNumbers
     */
    private function assertSeatsSelectable(Schedule $schedule, array $seatNumbers): void
    {
        // No duplicate seats within the same request.
        abort_if(
            count($seatNumbers) !== count(array_unique($seatNumbers)),
            422,
            'The same seat cannot be selected more than once.'
        );

        // Every seat must exist on this vehicle.
        $capacity = $schedule->capacity();
        $outOfRange = array_values(array_filter($seatNumbers, fn ($n) => $n < 1 || $n > $capacity));
        abort_if(
            ! empty($outOfRange),
            422,
            'Invalid seat number(s): ' . implode(', ', $outOfRange) . ". Seats range from 1 to {$capacity}."
        );

        // Seats must still be free.
        $clash = array_values(array_intersect($seatNumbers, $schedule->takenSeats()));
        abort_if(
            ! empty($clash),
            422,
            'Seat(s) already taken: ' . implode(', ', $clash) . '.'
        );
    }
}
