<?php

namespace App\Traits;

use App\Models\Otp;
use App\Models\User;
use App\Mail\SendOtpMail;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

trait ManagesOtp
{
    /**
     * Helper to generate and email OTP.
     */
    public function generateAndSendOtp(User $user)
    {
        // Delete any existing OTPs for the user
        Otp::where('user_id', $user->id)->delete();

        $otpCode = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);

        logInfo($otpCode, "otpCode");
        logInfo($user->id, "user_id");

        Otp::create([
            'user_id' => $user->id,
            'otp' => $otpCode,
            'expires_at' => Carbon::now()->addMinutes(15),
        ]);

        Mail::to($user->email)->send(new SendOtpMail($otpCode));
    }
}
