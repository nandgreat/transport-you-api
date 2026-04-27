<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginUserRequest;
use App\Http\Requests\Auth\RegisterUserRequest;
use App\Http\Requests\Auth\VerifyOtpRequest;
use App\Http\Requests\Auth\ResendOtpRequest;
use App\Services\AuthService;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    protected $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    public function register(RegisterUserRequest $request)
    {
        $this->authService->registerUser($request->validated());

        return $this->created(null, 'Registration successful. An OTP has been sent to your email.');
    }

    public function verifyOtp(VerifyOtpRequest $request)
    {
        $user = $this->authService->verifyOtp($request->validated());

        return $this->success([
            'user'  => $user->load('roles', 'tenant'),
            'token' => $user->createToken('auth_token')->accessToken,
        ], 'Email verified successfully.');
    }

    public function resendOtp(ResendOtpRequest $request)
    {
        $this->authService->resendOtp($request->validated());

        return $this->success(null, 'A new OTP has been sent to your email.');
    }

    public function login(LoginUserRequest $request)
    {
        $user = $this->authService->login($request->validated());

        return $this->success([
            'user'  => $user->load('roles', 'tenant'),
            'token' => $user->createToken('auth_token')->accessToken,
        ], 'Login successful.');
    }

    public function user(Request $request)
    {
        return $this->success(
            $request->user()->load('roles', 'tenant'),
            'User retrieved successfully.'
        );
    }

    public function logout(Request $request)
    {
        $request->user()->token()->revoke();

        return $this->success(null, 'Successfully logged out.');
    }
}
