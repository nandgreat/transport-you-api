<?php

namespace App\Services;

use App\Mail\TicketIssued;
use App\Models\Booking;
use App\Models\Ticket;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class TicketService
{
    /**
     * Issue tickets for a confirmed booking (one per passenger) and email them
     * to the customer. Safe to call more than once — tickets are only created
     * the first time, and this is how payment retries / duplicate callbacks are
     * handled.
     */
    public function issueAndEmail(Booking $booking): Collection
    {
        $booking->loadMissing('returnBooking', 'user', 'schedule.route.origin.state', 'schedule.route.destination.state', 'schedule.vehicleType');

        $tickets = $this->issueForBooking($booking);

        if ($booking->returnBooking) {
            $tickets = $tickets->merge($this->issueForBooking($booking->returnBooking));
        }

        $this->email($booking, $tickets);

        return $tickets;
    }

    /**
     * Create one ticket per passenger for a booking. Idempotent: returns the
     * existing tickets if they have already been issued.
     */
    public function issueForBooking(Booking $booking): Collection
    {
        $existing = $booking->tickets()->get();
        if ($existing->isNotEmpty()) {
            return $existing;
        }

        $passengers = $booking->passengers ?? [];

        return collect($passengers)->values()->map(function ($passenger, $index) use ($booking) {
            return Ticket::create([
                'tenant_id'       => $booking->tenant_id,
                'booking_id'      => $booking->id,
                'seat_number'     => $passenger['seat_number'] ?? null,
                'passenger_name'  => $passenger['name'] ?? null,
                'passenger_phone' => $passenger['phone'] ?? null,
                // The scannable code encodes the special booking reference + seat index.
                'qr_code'         => $booking->reference . '-' . ($index + 1),
                'status'          => 'valid',
                'issued_at'       => now(),
            ]);
        });
    }

    /**
     * Email the issued tickets to the booking's customer. Failures are logged
     * but never bubble up — a mail outage must not undo a confirmed booking.
     */
    private function email(Booking $booking, Collection $tickets): void
    {
        $email = $booking->user?->email;
        if (! $email || $tickets->isEmpty()) {
            return;
        }

        try {
            Mail::to($email)->send(new TicketIssued($booking, $tickets));
        } catch (\Throwable $e) {
            Log::error('Failed to email tickets for booking ' . $booking->reference . ': ' . $e->getMessage());
        }
    }
}
