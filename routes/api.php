<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\VehicleController;
use App\Http\Controllers\Api\V1\TenantController;
use App\Http\Controllers\Api\V1\TenantOnboardingController;
use App\Http\Controllers\Api\V1\LocationController;
use App\Http\Controllers\Api\V1\RouteController;
use App\Http\Controllers\Api\V1\ScheduleController;
use App\Http\Controllers\Api\V1\StateController;
use App\Http\Controllers\Api\V1\TripController;
use App\Http\Controllers\Api\V1\BookingController;
use App\Http\Controllers\Api\V1\UploadController;
use App\Http\Controllers\Api\V1\VehicleClassController;
use App\Http\Controllers\Api\V1\VehicleCategoryController;
use App\Http\Controllers\Api\V1\VehicleTypeController;
use App\Http\Controllers\Api\V1\SeatArrangementTypeController;
use App\Http\Controllers\Api\V1\ScheduledTripController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\TicketController;
use App\Http\Controllers\Api\V1\DriverController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::prefix('v1')->group(function () {
    // Public trip search — no auth required
    Route::get('/trips', [TripController::class, 'index']);
    Route::get('/trips/recurring/{group}/seats', [TripController::class, 'recurringSeats']);
    Route::get('/trips/{id}', [TripController::class, 'show']);
    Route::get('/trips/{id}/seats', [TripController::class, 'seats']);

    Route::apiResource('locations', LocationController::class);

    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);

    Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
    Route::post('/resend-otp', [AuthController::class, 'resendOtp']);
    Route::post('/tenant-onboarding', [TenantOnboardingController::class, 'onboard']);

    // Paystack redirects the customer's browser here after payment (public).
    Route::get('/payments/callback', [PaymentController::class, 'callback']);

    Route::middleware('auth:api')->group(function () {
        Route::get('/user', [AuthController::class, 'user']);
        Route::post('/logout', [AuthController::class, 'logout']);

        // Tenant self-service (authenticated user's own tenant)
        Route::get('/tenant', [TenantController::class, 'show']);
        Route::patch('/tenant', [TenantController::class, 'update']);


        Route::apiResource('vehicles', VehicleController::class);
        // A driver's trips, bucketed by lifecycle (assigned / ongoing / completed)
        Route::get('drivers/{id}/trips', [DriverController::class, 'trips']);
        Route::apiResource('drivers', DriverController::class);
        Route::apiResource('routes', RouteController::class);
        Route::apiResource('schedules', ScheduleController::class);

        // Scheduled trips — concrete dated trips (one-off + materialised recurring)
        Route::get('scheduled-trips', [ScheduledTripController::class, 'index']);
        Route::get('scheduled-trips/{id}', [ScheduledTripController::class, 'show']);
        Route::get('scheduled-trips/{id}/passengers', [ScheduledTripController::class, 'passengers']);
        Route::patch('scheduled-trips/{id}/status', [ScheduledTripController::class, 'updateStatus']);
        Route::patch('scheduled-trips/{id}/cancel', [ScheduledTripController::class, 'cancel']);
        // Assign / clear the physical vehicle + driver that will run the trip
        Route::patch('scheduled-trips/{id}/assignment', [ScheduledTripController::class, 'assign']);
        Route::delete('scheduled-trips/{id}/assignment', [ScheduledTripController::class, 'unassign']);
        Route::apiResource('states', StateController::class);
        Route::apiResource('vehicle-classes', VehicleClassController::class);
        Route::apiResource('vehicle-categories', VehicleCategoryController::class);
        Route::apiResource('vehicle-types', VehicleTypeController::class);
        Route::apiResource('seat-arrangement-types', SeatArrangementTypeController::class);

        Route::post('/upload/images', [UploadController::class, 'upload']);

        // Bookings
        Route::get('bookings', [BookingController::class, 'index']);
        // Aggregate totals — must precede the /{id} route so "summary" isn't
        // treated as a booking id.
        Route::get('bookings/summary', [BookingController::class, 'summary']);
        Route::post('bookings', [BookingController::class, 'store']);
        Route::get('bookings/{id}', [BookingController::class, 'show']);
        Route::patch('bookings/{id}/status', [BookingController::class, 'updateStatus']);
        Route::patch('bookings/{id}/cancel', [BookingController::class, 'cancel']);

        // Payments (Paystack)
        Route::post('bookings/{id}/pay', [PaymentController::class, 'initialize']);
        Route::get('payments/verify/{reference}', [PaymentController::class, 'verify']);

        // Tickets (QR)
        Route::get('tickets/verify/{code}', [TicketController::class, 'verify']);
        Route::patch('tickets/{code}/check-in', [TicketController::class, 'checkIn']);
        Route::post('bookings/{id}/resend-ticket', [TicketController::class, 'resend']);
    });
});
