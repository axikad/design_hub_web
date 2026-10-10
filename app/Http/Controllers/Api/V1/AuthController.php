<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Services\OtpService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(private readonly OtpService $otp) {}

    public function requestOtp(Request $request)
    {
        $validated = $request->validate([
            'flow' => ['required', 'in:login,register'],
            'name' => ['nullable', 'string'],
            'countryCode' => ['required', 'string', 'max:8'],
            'mobileNumber' => ['required', 'string', 'max:20'],
        ]);

        try {
            $this->otp->request(
                $validated['flow'],
                $validated['name'] ?? null,
                $validated['countryCode'],
                $validated['mobileNumber'],
            );
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['mobileNumber' => $exception->getMessage()]);
        }

        return ApiResponse::success('OTP sent successfully.');
    }

    public function verifyOtp(Request $request)
    {
        $validated = $request->validate([
            'flow' => ['required', 'in:login,register'],
            'name' => ['nullable', 'string'],
            'countryCode' => ['required', 'string', 'max:8'],
            'mobileNumber' => ['required', 'string', 'max:20'],
            'code' => ['required', 'string', 'size:4'],
        ]);

        try {
            $user = $this->otp->verify(
                $validated['flow'],
                $validated['name'] ?? null,
                $validated['countryCode'],
                $validated['mobileNumber'],
                $validated['code'],
            );
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['code' => $exception->getMessage()]);
        }

        $token = $user->createToken('mobile')->plainTextToken;

        return ApiResponse::data([
            'user' => (new UserResource($user))->resolve($request),
            'accessToken' => $token,
        ], message: 'OTP verified successfully.');
    }
}
