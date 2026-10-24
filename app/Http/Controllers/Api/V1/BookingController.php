<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Booking\StoreBookingRequest;
use App\Http\Requests\Booking\UpdateBookingStatusRequest;
use App\Models\Booking;
use App\Services\BookingService;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    public function __construct(protected BookingService $bookingService) {}

    public function index(Request $request)
    {
        $request->validate([
            'status'      => 'nullable|in:pending,confirmed,cancelled',
            'schedule_id' => 'nullable|integer|exists:schedules,id',
        ]);

        $user      = $request->user();
        $tenantId  = $user->tenant_id;
        $bookings  = $this->bookingService->listBookings(
            $user->id, $tenantId, $user->isSuperAdmin(),
            $request->only(['status', 'schedule_id'])
        );

        $bookings->setCollection(
            $bookings->getCollection()->map(fn($b) => $this->formatBooking($b))
        );

        return $this->success($bookings, 'Bookings retrieved successfully');
    }

    public function store(StoreBookingRequest $request)
    {
        $user    = $request->user();
        $data    = $request->validated();

        // Customers are always forced to pending regardless of what they send
        if (! $user->tenant_id && ! $user->isSuperAdmin()) {
            $data['status'] = 'pending';
        }

        $booking = $this->bookingService->createBooking($user->id, $data);

        return $this->created($this->formatBooking($booking), 'Booking created successfully');
    }

    public function show(Request $request, string $id)
    {
        $user    = $request->user();
        $booking = $this->bookingService->getBooking($user->id, $user->tenant_id, $user->isSuperAdmin(), $id);

        return $this->success($this->formatBooking($booking), 'Booking retrieved successfully');
    }

    public function updateStatus(UpdateBookingStatusRequest $request, string $id)
    {
        $user    = $request->user();
        $booking = $this->bookingService->updateStatus(
            $user->id, $user->tenant_id, $user->isSuperAdmin(), $id, $request->validated()
        );

        return $this->success($this->formatBooking($booking), 'Booking status updated successfully');
    }

    public function cancel(Request $request, string $id)
    {
        $booking = $this->bookingService->cancelBooking($request->user()->id, $id);

        return $this->success($this->formatBooking($booking), 'Booking cancelled successfully');
    }

    // -------------------------------------------------------------------------

    private function formatBooking(Booking $booking, bool $includeReturn = true): array
    {
        $data = [
            'id'           => $booking->id,
            'status'       => $booking->status,
            'total_amount' => $booking->total_amount,
            'passengers'   => $booking->passengers,
            'notes'        => $booking->notes,
            'is_round_trip' => $booking->return_booking_id !== null,
            'schedule'     => $this->formatScheduleSummary($booking->schedule),
            'customer'     => $booking->user ? [
                'id'    => $booking->user->id,
                'name'  => $booking->user->first_name . ' ' . $booking->user->last_name,
                'email' => $booking->user->email,
                'phone' => $booking->user->phone,
            ] : null,
            'operator' => $booking->tenant ? [
                'id'      => $booking->tenant->id,
                'name'    => $booking->tenant->name,
                'logo_url' => $booking->tenant->logo_url,
            ] : null,
            'created_at' => $booking->created_at,
        ];

        if ($includeReturn && $booking->relationLoaded('returnBooking') && $booking->returnBooking) {
            $data['return_booking'] = $this->formatBooking($booking->returnBooking, false);
        } else {
            $data['return_booking'] = null;
        }

        return $data;
    }

    private function formatScheduleSummary(?\App\Models\Schedule $schedule): ?array
    {
        if (! $schedule) return null;

        $route = $schedule->route;

        return [
            'id'             => $schedule->id,
            'departure_time' => $schedule->departure_time,
            'arrival_time'   => $schedule->arrival_time,
            'fare'           => $schedule->fare,
            'status'         => $schedule->status,
            'seat_arrangement_label' => $schedule->seatArrangementLabel(),
            'capacity'        => $schedule->capacity(),
            'available_seats' => $schedule->availableSeats(),
            'seats'           => $schedule->seatMap(),
            'route' => $route ? [
                'origin'      => $this->formatLocation($route->origin),
                'destination' => $this->formatLocation($route->destination),
                'distance'           => $route->distance,
                'estimated_duration' => $route->estimated_duration,
            ] : null,
            'vehicle_type' => $schedule->vehicleType ? [
                'id'           => $schedule->vehicleType->id,
                'vehicle_name' => $schedule->vehicleType->vehicle_name,
                'seat_arrangement' => $schedule->resolveSeatArrangement() ? [
                    'id'              => $schedule->resolveSeatArrangement()->id,
                    'name'            => $schedule->resolveSeatArrangement()->name,
                    'number_of_seats' => $schedule->resolveSeatArrangement()->number_of_seats,
                ] : null,
            ] : null,
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
}
