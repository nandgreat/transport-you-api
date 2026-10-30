<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PaymentService
{
    public function __construct(
        protected PaystackService $paystack,
        protected TicketService $tickets,
    ) {
    }

    /**
     * Start a Paystack transaction for a booking and return the authorization
     * URL the frontend redirects the customer to.
     */
    public function initializeForBooking(Booking $booking): array
    {
        abort_if($booking->status !== 'pending', 422, 'Only pending bookings can be paid for.');
        abort_if($booking->expires_at && $booking->expires_at->isPast(), 422, 'This booking hold has expired. Please book again.');
        abort_if(!$booking->user || !$booking->user->email, 422, 'The booking has no customer email to charge.');

        // Round trips are charged as one transaction (outbound + return legs).
        $amount = (float) $booking->total_amount;
        if ($booking->return_booking_id && $booking->returnBooking) {
            $amount += (float) $booking->returnBooking->total_amount;
        }

        $reference = 'txy_' . Str::lower(Str::random(24));

        $data = $this->paystack->initialize([
            'email' => $booking->user->email,
            'amount' => (int) round($amount * 100), // kobo
            'currency' => 'NGN',
            'reference' => $reference,
            'callback_url' => config('services.paystack.callback_url') . "/$booking->id",
            'metadata' => [
                'booking_id' => $booking->id,
                'user_id' => $booking->user_id,
            ],
        ]);

        Payment::create([
            'tenant_id' => $booking->tenant_id,
            'booking_id' => $booking->id,
            'user_id' => $booking->user_id,
            'amount' => $amount,
            'currency' => 'NGN',
            'payment_method' => 'paystack',
            'payment_status' => 'pending',
            'transaction_reference' => $reference,
            'authorization_url' => $data['authorization_url'] ?? null,
            'access_code' => $data['access_code'] ?? null,
        ]);

        return [
            'authorization_url' => $data['authorization_url'] ?? null,
            'access_code' => $data['access_code'] ?? null,
            'reference' => $reference,
            'amount' => $amount,
            'currency' => 'NGN',
            'booking_id' => $booking->id,
        ];
    }

    /**
     * Verify a transaction with Paystack and, on success, confirm the booking
     * (and its linked return booking). Idempotent.
     */
    public function verifyAndConfirm(string $reference): array
    {
        $payment = Payment::with('booking.returnBooking')->where('transaction_reference', $reference)->firstOrFail();

        if ($payment->payment_status === 'successful') {
            return ['status' => 'successful', 'already_processed' => true, 'payment' => $payment->fresh(), 'booking' => $payment->booking?->fresh()];
        }

        $data = $this->paystack->verify($reference);
        $success = ($data['status'] ?? null) === 'success';

        $result = DB::transaction(function () use ($payment, $data, $success) {
            $payment->update([
                'payment_status' => $success ? 'successful' : 'failed',
                'channel' => $data['channel'] ?? null,
                'gateway_response' => $data['gateway_response'] ?? null,
                'paid_at' => $success ? now() : null,
            ]);

            if ($success && $payment->booking) {
                // Payment captured — confirm and lift the hold expiry.
                $payment->booking->update(['status' => 'confirmed', 'expires_at' => null]);
                if ($payment->booking->return_booking_id) {
                    Booking::where('id', $payment->booking->return_booking_id)
                        ->update(['status' => 'confirmed', 'expires_at' => null]);
                }
            }

            return [
                'status' => $success ? 'successful' : 'failed',
                'payment' => $payment->fresh(),
                'booking' => $payment->booking?->fresh(),
            ];
        });

        // Issue QR tickets and email them once the booking is confirmed. Done
        // after the transaction commits so a mail hiccup can't roll back payment.
        if ($success && $payment->booking) {
            $this->tickets->issueAndEmail($payment->booking->fresh());
        }

        return $result;
    }
}
