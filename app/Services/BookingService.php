<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Schedule;
use App\Models\ScheduleGroup;
use Illuminate\Support\Facades\DB;

class BookingService
{
    public function __construct(
        protected ScheduleService $scheduleService,
        protected TicketService $ticketService,
    ) {}

    /**
     * Resolve the target schedule for a (return) booking leg: an existing
     * concrete schedule, or a recurring pattern + date materialised into one.
     */
    private function resolveSchedule(array $data, string $prefix = ''): Schedule
    {
        $scheduleId = $data[$prefix . 'schedule_id'] ?? null;
        $groupId    = $data[$prefix . 'schedule_group_id'] ?? null;
        $date       = $data[$prefix . 'travel_date'] ?? null;

        if ($scheduleId) {
            return Schedule::with(['route', 'vehicleType.seatArrangementTypes', 'seatArrangement'])->findOrFail($scheduleId);
        }

        abort_if(! $groupId || ! $date, 422, 'Provide a schedule_id, or a schedule_group_id with travel_date.');

        $group    = ScheduleGroup::with(['vehicleType.seatArrangementTypes', 'seatArrangement'])->findOrFail($groupId);
        $schedule = $this->scheduleService->materializeOccurrence($group, $date);

        return $schedule->load(['route', 'vehicleType.seatArrangementTypes', 'seatArrangement']);
    }

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
            'tickets',
            'returnBooking.tickets',
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

    /**
     * Aggregate booking figures for the dashboard. `total_amount` is the value
     * of live bookings — cancelled and expired ones carry no value and are
     * excluded; `confirmed_amount` is the realised revenue.
     *
     * @return array{total_bookings:int, total_amount:string, confirmed_amount:string}
     */
    public function summary(int $userId, ?int $tenantId, bool $isSuperAdmin): array
    {
        $scoped = fn () => Booking::query()
            ->when(! $isSuperAdmin && $tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->when(! $isSuperAdmin && ! $tenantId, fn ($q) => $q->where('user_id', $userId));

        return [
            'total_bookings'   => $scoped()->count(),
            'total_amount'     => (string) $scoped()->whereNotIn('status', ['cancelled', 'expired'])->sum('total_amount'),
            'confirmed_amount' => (string) $scoped()->where('status', 'confirmed')->sum('total_amount'),
        ];
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
            $schedule   = $this->resolveSchedule($data);
            $passengers = $data['passengers'];
            $seats      = count($passengers);
            $status     = $data['status'] ?? 'pending';

            $outboundPassengers = $this->prepareSeats($schedule, $passengers);

            $outbound = Booking::create([
                'tenant_id'    => $schedule->tenant_id,
                'user_id'      => $userId,
                'schedule_id'  => $schedule->id,
                'total_amount' => $schedule->fare * $seats,
                'passengers'   => $outboundPassengers,
                'notes'        => $data['notes'] ?? null,
                'status'       => $status,
                'expires_at'   => $this->holdExpiry($status),
            ]);

            if (! empty($data['is_round_trip']) && (($data['return_schedule_id'] ?? null) || ($data['return_schedule_group_id'] ?? null))) {
                $returnSchedule = $this->resolveSchedule($data, 'return_');

                $returnPassengers = $this->prepareSeats($returnSchedule, $passengers);

                $returnBooking = Booking::create([
                    'tenant_id'    => $returnSchedule->tenant_id,
                    'user_id'      => $userId,
                    'schedule_id'  => $returnSchedule->id,
                    'total_amount' => $returnSchedule->fare * $seats,
                    'passengers'   => $returnPassengers,
                    'notes'        => $data['notes'] ?? null,
                    'status'       => $status,
                    'expires_at'   => $this->holdExpiry($status),
                ]);

                $outbound->update(['return_booking_id' => $returnBooking->id]);
                $outbound->return_booking_id = $returnBooking->id;
            }

            return $outbound->load($this->bookingWith());
        });
    }

    /**
     * When a freshly created booking should release its seats if left unpaid.
     * Only pending holds expire; confirmed bookings never do. Returns null when
     * expiry is disabled (hold_minutes = 0) or the booking is not pending.
     */
    private function holdExpiry(string $status): ?\Illuminate\Support\Carbon
    {
        $minutes = (int) config('booking.hold_minutes', 15);

        if ($status !== 'pending' || $minutes < 1) {
            return null;
        }

        return now()->addMinutes($minutes);
    }

    public function updateStatus(int $userId, ?int $tenantId, bool $isSuperAdmin, string $id, array $data): Booking
    {
        $booking = $this->getBooking($userId, $tenantId, $isSuperAdmin, $id);
        $wasConfirmed = $booking->status === 'confirmed';

        $booking->update(array_filter([
            'status' => $data['status'],
            'notes'  => $data['notes'] ?? $booking->notes,
        ]));

        // Confirming a booking (e.g. an operator marking a cash payment) issues
        // and emails the QR tickets, just like a successful online payment.
        if (! $wasConfirmed && $booking->status === 'confirmed') {
            $booking->update(['expires_at' => null]);
            $this->ticketService->issueAndEmail($booking->fresh());
        }

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
     * Validate a booking's passengers against a schedule and return the
     * passenger list to persist.
     *
     * Capacity (by headcount) is always checked. When the schedule has seat
     * selection enabled, every passenger must pick a valid, free, unique seat;
     * when it is disabled, any supplied seat numbers are dropped.
     *
     * @return array<int, array<string, mixed>>
     */
    private function prepareSeats(Schedule $schedule, array $passengers): array
    {
        $requested = count($passengers);
        $available = $schedule->availableSeats();

        abort_if(
            $available < $requested,
            422,
            "Not enough seats available. Requested: {$requested}, Available: {$available}."
        );

        if (! $schedule->seat_selection_enabled) {
            // Seat selection is off for this schedule — ignore any seat numbers.
            return array_map(function ($p) {
                unset($p['seat_number']);
                return $p;
            }, $passengers);
        }

        $seatNumbers = $this->seatNumbers($passengers);

        abort_if(
            count(array_filter($seatNumbers, fn ($n) => $n > 0)) !== $requested,
            422,
            'Seat selection is required for this schedule: every passenger must choose a seat.'
        );

        $this->assertSeatsSelectable($schedule, $seatNumbers);

        return $passengers;
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
