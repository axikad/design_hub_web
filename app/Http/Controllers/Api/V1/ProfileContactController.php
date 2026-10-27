<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Services\ProfileContactOtpService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ProfileContactController extends Controller
{
    public function __construct(private readonly ProfileContactOtpService $otp) {}

    public function requestOtp(Request $request)
    {
        $validated = $this->validateContact($request);

        try {
            $result = $this->otp->request(
                $request->user(),
                $validated['type'],
                $validated['countryCode'] ?? null,
                $validated['mobileNumber'] ?? null,
                $validated['email'] ?? null,
            );
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                $validated['type'] === 'mobile' ? 'mobileNumber' : 'email' => $exception->getMessage(),
            ]);
        }

        return ApiResponse::data([
            ...$result,
            'expiresInSeconds' => (int) config('designhub.otp_ttl_minutes') * 60,
        ], message: 'OTP sent successfully.');
    }

    public function verifyOtp(Request $request)
    {
        $validated = $this->validateContact($request, true);

        try {
            $user = $this->otp->verify(
                $request->user(),
                $validated['type'],
                $validated['countryCode'] ?? null,
                $validated['mobileNumber'] ?? null,
                $validated['email'] ?? null,
                $validated['code'],
            );
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['code' => $exception->getMessage()]);
        }

        return ApiResponse::data(
            (new UserResource($user))->resolve($request),
            message: $validated['type'] === 'mobile'
                ? 'Mobile number verified and updated successfully.'
                : 'Email address verified and updated successfully.',
        );
    }

    /** @return array<string, mixed> */
    private function validateContact(Request $request, bool $withCode = false): array
    {
        $rules = [
            'type' => ['required', 'in:mobile,email'],
            'countryCode' => ['required_if:type,mobile', 'nullable', 'string', 'max:8'],
            'mobileNumber' => ['required_if:type,mobile', 'nullable', 'string', 'max:20'],
            'email' => ['required_if:type,email', 'nullable', 'string', 'email', 'max:255'],
        ];

        if ($withCode) {
            $rules['code'] = ['required', 'string', 'size:'.(int) config('designhub.otp_length')];
        }

        return $request->validate($rules);
    }
}
