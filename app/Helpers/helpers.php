<?php

use App\Http\Controllers\ThirdParty\FlutterwaveController;
use App\Http\Controllers\ThirdParty\PaystackController;
use App\Http\Controllers\UserEnd\BookingController;
use App\Models\Booking;
use App\Models\Passenger;
use App\Services\BookingService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

function moneyFormat($amount, $decimal)
{
    return "₦" . number_format($amount, $decimal);
}

function getFormattedTime($departureTime)
{
    $hourValue = substr($departureTime, 0, 2);
    $minuteValue = substr($departureTime, 3, 2);
    $hourValue = (int) $hourValue;

    return $hourValue < 12 ? $departureTime . "am" : ($hourValue % 12) . ":" . $minuteValue . "pm";
}

function switchRoute($routeTxt)
{
    $route_txt = session('route');

    $splitted = explode(" - ", $route_txt);

    return $splitted[1] . " - " . $splitted[0];
}

function checkWhetherTodayTripWillWork($travel_date, $travelTime)
{

    logInfo(Carbon::now(), "Current Date");
    if (Carbon::parse($travel_date)->isToday()) {
        $hourMin = date('H:i');

        $firstHourValue = substr($hourMin, 0, 2);

        $hourValue = substr($travelTime, 0, 2);

        // Check if difference in the time from pickup is greater than 6 hours
        return ($firstHourValue - $hourValue) > 1;
    }
    return true;
}

function verifyTransactionWithReference($bookingReference, $transactionId)
{
    Log::info($bookingReference);

    $booking = Booking::leftJoin('passengers as ps', 'ps.booking_id', '=', 'bookings.id')
        ->leftJoin('travel_schedules as ts', 'ts.id', '=', 'bookings.travel_schedule_id')
        ->leftJoin('routes as rt', 'rt.id', '=', 'ts.route_id')
        ->leftJoin('locations as pk', 'pk.id', '=', 'rt.departure_location_id')
        ->leftJoin('locations as dp', 'dp.id', '=', 'rt.destination_location_id')
        ->where('booking_reference', $bookingReference)
        ->select('bookings.*', 'ts.departure_time', 'pk.pickup_address', 'pk.location as pickupLocation', 'dp.location as dropOffLocation')
        ->first();

    if ($booking) {

        if ($booking->status_id != 3) {
            return redirect('/')->withErrors("Booking has already been processed");
        }

        $paystackController = new PaystackController();

        $verifyTransaction = $paystackController->verifyTransaction($transactionId);

        if ($verifyTransaction['status'] == "00") {

            if ($verifyTransaction['data']['amount'] < $booking->amount_paid) {
                return redirect('/')->withErrors("Invalid booking amount... Kindly contact support");
            }

            $booking->payment_status_id = 4;
            $booking->status_id = 5;
            $booking->save();

            $newTime = getFormattedTime($booking->departure_time);


            $passengers = Passenger::where('booking_id', $booking->id)->get();

            $qr = QrCode::format('png')->size(200)->generate('https://globecabservices.com/bookings/checkin');

            $data = [
                'contactName' => $booking->contact_name,
                'contactPhone' => $booking->contact_phone,
                'contactEmail' => $booking->contact_email,
                'nextOfKinName' => $booking->next_of_kin_name,
                'nextOfKinPhone' => $booking->next_of_kin_phone,
                'travelDate' => $booking->travel_date,
                'travelTime' => $newTime,
                'bookingReference' => $booking->booking_reference,
                'bookingAmount' => $booking->amount_paid,
                'pickupAddress' => $booking->pickup_address,
                'pickupLocation' => $booking->pickupLocation,
                'dropOffLocation' => $booking->dropOffLocation,
                'passengers' => $passengers,
                'qr' => $qr
            ];

            // Mail::to($booking->contact_email)->send(new BookingTicketMail($data));

            return ['status' => true, 'message' => 'Booking Successful. Your booking reference is: ' . $booking->booking_reference . '. Your booking ticket has been sent to your mail'];
        } else if ($verifyTransaction['status'] == "04") {
            return ['status' => false, 'message' => "Your transaction is ongoing. Your booking Ticket will be sent to your mail once it is successful"];
        } else {
            $bookingController = new BookingService();
            $bookingController->undoBooking($booking);
            return ['status' => true, 'message' => 'Failed to place booking. Initial booking has been revoked, Kindly try again'];
        }
    } else {
        return ['status' => true, 'message' => 'Booking reference not found'];
    }
}

function splitMyArray(array $input_array, int $size, $preserve_keys = null): array
{
    $nr = (int)ceil(count($input_array) / $size);

    if ($nr > 0) {
        return array_chunk($input_array, $nr, $preserve_keys);
    }

    return $input_array;
}


function logInfo($content, $title = "No Title")
{
    Log::info("<<<<<<<<<<<<<");
    Log::info($title);
    Log::info(">>>>>>>>>>>>>>>>>>>>>>");
    Log::info($content);
}


function getEloquentSqlWithBindings($query)
{
    return vsprintf(str_replace('?', '%s', $query->toSql()), collect($query->getBindings())->map(function ($binding) {
        return is_numeric($binding) ? $binding : "'{$binding}'";
    })->toArray());
}
