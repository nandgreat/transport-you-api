<?php

namespace App\Services;

use App\Models\User;
use App\Models\Otp;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;
use App\Traits\ManagesOtp;

class AuthService
{
    use ManagesOtp;

    public function registerUser(array $data)
    {
        $user = User::create([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'phone' => $data['phone'] ?? null,
            'status' => 'active',
        ]);

        $this->generateAndSendOtp($user);

        return $user;
    }

    public function verifyOtp(array $data)
    {
        $user = User::where('email', $data['email'])->first();

        $otpRecord = Otp::where('user_id', $user->id)
                        ->where('otp', $data['otp'])
                        ->first();

        if (!$otpRecord) {
            throw ValidationException::withMessages(['otp' => ['Invalid OTP.']]);
        }

        if (Carbon::now()->greaterThan($otpRecord->expires_at)) {
            throw ValidationException::withMessages(['otp' => ['OTP has expired.']]);
        }

        $user->email_verified_at = now();
        $user->save();
        $otpRecord->delete();

        return $user;
    }

    public function resendOtp(array $data)
    {
        $user = User::where('email', $data['email'])->first();

        if ($user->email_verified_at) {
            throw ValidationException::withMessages(['email' => ['Email is already verified.']]);
        }

        $this->generateAndSendOtp($user);
    }

    public function login(array $data)
    {
        $user = User::where('email', $data['email'])->first();

        if (!$user || !Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if ($user->status !== 'active') {
            throw ValidationException::withMessages([
                'email' => ['Your account is inactive.'],
            ]);
        }

        if (!$user->email_verified_at) {
            throw ValidationException::withMessages([
                'email' => ['Please verify your email address before logging in.']
            ]);
        }

        return $user;
    }
}
