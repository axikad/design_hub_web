<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\Machine;
use App\Services\OtpService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(private readonly OtpService $otp) {}

    public function registrationMachines()
    {
        $machines = Machine::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (Machine $machine) => [
                'id' => $machine->public_id,
                'name' => $machine->name,
                'imageUri' => $machine->imageUrl(),
            ]);

        return ApiResponse::data($machines->values(), message: 'Registration machines retrieved successfully.');
    }

    public function requestOtp(Request $request)
    {
        $validated = $request->validate([
            'flow' => ['required', 'in:login,register'],
            'name' => ['nullable', 'string'],
            'countryCode' => ['required', 'string', 'max:8'],
            'mobileNumber' => ['required', 'string', 'max:20'],
            'machineIds' => ['required_if:flow,register', 'array', 'min:1', 'max:50'],
            'machineIds.*' => [
                'string',
                'distinct',
                Rule::exists('machines', 'public_id')->where('is_active', true),
            ],
        ]);

        try {
            $this->otp->request(
                $validated['flow'],
                $validated['name'] ?? null,
                $validated['countryCode'],
                $validated['mobileNumber'],
                $validated['machineIds'] ?? [],
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
            'machineIds' => ['required_if:flow,register', 'array', 'min:1', 'max:50'],
            'machineIds.*' => [
                'string',
                'distinct',
                Rule::exists('machines', 'public_id')->where('is_active', true),
            ],
        ]);

        try {
            $user = $this->otp->verify(
                $validated['flow'],
                $validated['name'] ?? null,
                $validated['countryCode'],
                $validated['mobileNumber'],
                $validated['code'],
                $validated['machineIds'] ?? [],
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

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();

        return ApiResponse::success('Logged out successfully.');
    }
}
