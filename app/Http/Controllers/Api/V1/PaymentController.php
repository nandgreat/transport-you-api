<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\PaymentService;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(protected PaymentService $paymentService) {}

    /**
     * Initialize a Paystack payment for a booking (server-side).
     * Returns the authorization_url for the frontend to redirect to.
     */
    public function initialize(Request $request, string $bookingId)
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

        $result = $this->paymentService->initializeForBooking($booking);

        return $this->created($result, 'Payment initialized. Redirect the customer to authorization_url.');
    }

    /**
     * Paystack redirects the customer's browser here after payment (public).
     * Verifies the transaction, confirms the booking, then redirects to the
     * frontend (if configured) or returns JSON.
     */
    public function callback(Request $request)
    {
        $reference = $request->query('reference') ?? $request->query('trxref');

        if (! $reference) {
            return $this->badRequest('Missing payment reference.');
        }

        $result   = $this->paymentService->verifyAndConfirm($reference);
        $frontend = config('services.paystack.frontend_redirect');

        if ($frontend) {
            $sep = str_contains($frontend, '?') ? '&' : '?';
            return redirect()->away("{$frontend}{$sep}reference={$reference}&status={$result['status']}");
        }

        return $this->success($result, 'Payment ' . $result['status'] . '.');
    }

    /**
     * Verify a payment on demand (e.g. frontend polling) and confirm the booking.
     */
    public function verify(Request $request, string $reference)
    {
        $result = $this->paymentService->verifyAndConfirm($reference);

        return $this->success($result, 'Payment ' . $result['status'] . '.');
    }
}
