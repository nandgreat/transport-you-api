<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Ticket</title>
</head>
<body style="margin:0; padding:0; background:#f4f5f7; font-family:Arial, Helvetica, sans-serif; color:#1f2937;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f5f7; padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px; width:100%; background:#ffffff; border-radius:10px; overflow:hidden;">
                    {{-- Header --}}
                    <tr>
                        <td style="background:#0f172a; padding:24px 28px; color:#ffffff;">
                            <div style="font-size:20px; font-weight:bold;">{{ config('app.name') }}</div>
                            <div style="font-size:13px; opacity:.8; margin-top:4px;">Booking confirmed — your tickets are below</div>
                        </td>
                    </tr>

                    {{-- Trip summary --}}
                    <tr>
                        <td style="padding:24px 28px 8px;">
                            <p style="margin:0 0 4px;">Hi {{ $booking->user->first_name ?? 'there' }},</p>
                            <p style="margin:0 0 16px; color:#4b5563; font-size:14px;">
                                Thank you for booking with us. Present the QR code for each passenger at boarding.
                            </p>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px;">
                                <tr>
                                    <td style="padding:6px 0; color:#6b7280;">Booking reference</td>
                                    <td style="padding:6px 0; text-align:right; font-weight:bold; letter-spacing:1px;">{{ $booking->reference }}</td>
                                </tr>
                                @if($booking->schedule && $booking->schedule->route)
                                <tr>
                                    <td style="padding:6px 0; color:#6b7280;">Route</td>
                                    <td style="padding:6px 0; text-align:right;">
                                        {{ optional($booking->schedule->route->origin)->name ?? $booking->schedule->route->origin_city ?? '—' }}
                                        &rarr;
                                        {{ optional($booking->schedule->route->destination)->name ?? $booking->schedule->route->destination_city ?? '—' }}
                                    </td>
                                </tr>
                                @endif
                                @if($booking->schedule && $booking->schedule->departure_time)
                                <tr>
                                    <td style="padding:6px 0; color:#6b7280;">Departure</td>
                                    <td style="padding:6px 0; text-align:right;">{{ \Illuminate\Support\Carbon::parse($booking->schedule->departure_time)->format('D, d M Y • H:i') }}</td>
                                </tr>
                                @endif
                                <tr>
                                    <td style="padding:6px 0; color:#6b7280;">Total paid</td>
                                    <td style="padding:6px 0; text-align:right;">&#8358;{{ number_format((float) $booking->total_amount, 2) }}</td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Tickets --}}
                    @foreach($tickets as $ticket)
                    <tr>
                        <td style="padding:12px 28px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e5e7eb; border-radius:10px;">
                                <tr>
                                    <td style="padding:16px; vertical-align:middle;">
                                        <div style="font-size:12px; color:#6b7280; text-transform:uppercase; letter-spacing:1px;">Passenger</div>
                                        <div style="font-size:16px; font-weight:bold; margin:2px 0 10px;">{{ $ticket->passenger_name ?? 'Passenger' }}</div>

                                        @if($ticket->seat_number)
                                        <div style="font-size:13px; color:#374151;">Seat: <strong>{{ $ticket->seat_number }}</strong></div>
                                        @endif
                                        <div style="font-size:13px; color:#374151;">Ticket: <strong>{{ $ticket->qr_code }}</strong></div>
                                    </td>
                                    <td style="padding:16px; text-align:right; vertical-align:middle; width:150px;">
                                        <img src="{{ $message->embedData($ticket->qrPng(), 'qr-' . $ticket->qr_code . '.png', 'image/png') }}"
                                             alt="QR code for {{ $ticket->qr_code }}"
                                             width="130" height="130"
                                             style="display:inline-block; border:1px solid #e5e7eb; border-radius:8px;">
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    @endforeach

                    {{-- Footer --}}
                    <tr>
                        <td style="padding:16px 28px 28px; color:#9ca3af; font-size:12px; line-height:1.5;">
                            Keep this email safe. Each QR code is valid for a single boarding.
                            If you did not make this booking, please contact support.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
