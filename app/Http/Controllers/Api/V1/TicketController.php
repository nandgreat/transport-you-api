<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Ticket;
use App\Services\TicketService;
use Illuminate\Http\Request;

class TicketController extends Controller
{
    public function __construct(protected TicketService $ticketService) {}

    /**
     * Verify a ticket by its QR code (the scannable code). Returns the ticket,
     * its validity and a short trip/passenger summary — for boarding staff.
     */
    public function verify(Request $request, string $code)
    {
        $ticket = $this->scopedTicketQuery($request)
            ->with('booking.schedule.route.origin', 'booking.schedule.route.destination', 'booking.user')
            ->where('qr_code', $code)
            ->first();

        if (! $ticket) {
            return $this->notFound('Ticket not found.');
        }

        return $this->success([
            'ticket'  => $ticket,
            'valid'   => $ticket->status === 'valid',
            'booking' => [
                'reference' => $ticket->booking?->reference,
                'status'    => $ticket->booking?->status,
            ],
        ], 'Ticket retrieved successfully.');
    }

    /**
     * Mark a valid ticket as used at boarding.
     */
    public function checkIn(Request $request, string $code)
    {
        $ticket = $this->scopedTicketQuery($request)->where('qr_code', $code)->first();

        if (! $ticket) {
            return $this->notFound('Ticket not found.');
        }

        if ($ticket->status !== 'valid') {
            return $this->badRequest("Ticket cannot be checked in (status: {$ticket->status}).");
        }

        $ticket->update(['status' => 'used']);

        return $this->success($ticket->fresh(), 'Ticket checked in.');
    }

    /**
     * Re-send the ticket email for a confirmed booking.
     */
    public function resend(Request $request, string $bookingId)
    {
        $user    = $request->user();
        $booking = Booking::with('user', 'returnBooking')->find($bookingId);

        if (! $booking) {
            return $this->notFound('Booking not found.');
        }

        $owns = $booking->user_id === $user->id
            || $user->isSuperAdmin()
            || ($user->tenant_id && $booking->tenant_id === $user->tenant_id);

        if (! $owns) {
            return $this->notFound('Booking not found.');
        }

        if ($booking->status !== 'confirmed') {
            return $this->badRequest('Tickets are only available for confirmed bookings.');
        }

        $tickets = $this->ticketService->issueAndEmail($booking);

        return $this->success(['count' => $tickets->count()], 'Ticket email re-sent.');
    }

    /**
     * Restrict ticket lookups to what the caller may see: super admins see all,
     * tenant users see their tenant's tickets, customers see their own.
     */
    private function scopedTicketQuery(Request $request)
    {
        $user = $request->user();

        return Ticket::query()
            ->when(! $user->isSuperAdmin() && $user->tenant_id, fn ($q) => $q->where('tenant_id', $user->tenant_id))
            ->when(! $user->isSuperAdmin() && ! $user->tenant_id, fn ($q) =>
                $q->whereHas('booking', fn ($b) => $b->where('user_id', $user->id))
            );
    }
}
