<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\VehicleController;
use App\Http\Controllers\Api\V1\TenantOnboardingController;
use App\Http\Controllers\Api\V1\LocationController;
use App\Http\Controllers\Api\V1\RouteController;
use App\Http\Controllers\Api\V1\ScheduleController;
use App\Http\Controllers\Api\V1\StateController;
use App\Http\Controllers\Api\V1\UploadController;

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
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);

    Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
    Route::post('/resend-otp', [AuthController::class, 'resendOtp']);
    Route::post('/tenant-onboarding', [TenantOnboardingController::class, 'onboard']);

    Route::middleware('auth:api')->group(function () {
        Route::get('/user', [AuthController::class, 'user']);
        Route::post('/logout', [AuthController::class, 'logout']);


        Route::apiResource('vehicles', VehicleController::class);
        Route::apiResource('locations', LocationController::class);
        Route::apiResource('routes', RouteController::class);
        Route::apiResource('schedules', ScheduleController::class);
        Route::apiResource('states', StateController::class);

        Route::post('/upload', [UploadController::class, 'upload']);
    });
});
