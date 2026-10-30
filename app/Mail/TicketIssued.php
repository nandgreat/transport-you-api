<?php

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class TicketIssued extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Booking $booking,
        public Collection $tickets,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your ticket(s) — Booking ' . $this->booking->reference,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.tickets',
            with: [
                'booking' => $this->booking,
                'tickets' => $this->tickets,
            ],
        );
    }
}
