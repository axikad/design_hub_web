<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Services\OtpService;
use App\Support\PhoneNormalizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    public function __construct(private readonly OtpService $otp) {}

    /**
     * Show the login page.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('auth/login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => $request->session()->get('status'),
            'loginResolution' => $request->session()->get('loginResolution'),
        ]);
    }

    public function resolveLogin(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'loginType' => ['required', Rule::in(['email', 'mobile'])],
            'email' => ['required_if:loginType,email', 'nullable', 'string', 'email'],
            'countryCode' => ['required_if:loginType,mobile', 'nullable', 'string', 'max:8'],
            'mobileNumber' => ['required_if:loginType,mobile', 'nullable', 'string', 'max:20'],
        ]);

        $query = User::query()->where('status', 'active');
        $errorKey = $validated['loginType'] === 'mobile' ? 'mobileNumber' : 'email';

        if ($validated['loginType'] === 'mobile') {
            $phoneKey = PhoneNormalizer::key($validated['countryCode'], $validated['mobileNumber']);
            if ($phoneKey === null) {
                throw ValidationException::withMessages(['mobileNumber' => 'Enter a valid mobile number.']);
            }
            $query->where('phone_key', $phoneKey);
        } else {
            $query->where('email', Str::lower(trim($validated['email'])));
        }

        $user = $query->first();
        if ($user === null || ! $user->canAccessPanel() || (! $user->allow_otp_login && ! $user->allow_password_login)) {
            throw ValidationException::withMessages([
                $errorKey => 'No active account is available for these login details.',
            ]);
        }

        $otpSent = false;
        if ($user->allow_otp_login && ! $user->allow_password_login) {
            if ($user->country_code === null || $user->mobile_number === null) {
                throw ValidationException::withMessages([
                    $errorKey => 'OTP login is not available for this account.',
                ]);
            }

            try {
                $this->otp->request(
                    'login',
                    null,
                    $user->country_code,
                    $user->mobile_number,
                );
                $otpSent = true;
            } catch (\InvalidArgumentException $exception) {
                throw ValidationException::withMessages([
                    $errorKey => $exception->getMessage(),
                ]);
            }
        }

        return back()->with('loginResolution', [
            'password' => (bool) $user->allow_password_login,
            'otp' => (bool) $user->allow_otp_login,
            'otpSent' => $otpSent,
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();
        $request->user()?->forceFill(['last_login_at' => now()])->save();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    public function requestOtp(Request $request): RedirectResponse
    {
        $contact = $this->otpContact($request);

        try {
            $this->otp->request(
                'login',
                null,
                $contact['countryCode'],
                $contact['mobileNumber'],
            );
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'mobileNumber' => $exception->getMessage(),
            ]);
        }

        return back()->with('status', 'OTP sent successfully.');
    }

    public function verifyOtp(Request $request): RedirectResponse
    {
        $contact = $this->otpContact($request);
        $validated = $request->validate([
            'code' => ['required', 'string', 'size:4'],
            'remember' => ['sometimes', 'boolean'],
        ]);

        try {
            $user = $this->otp->verify(
                'login',
                null,
                $contact['countryCode'],
                $contact['mobileNumber'],
                $validated['code'],
            );
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'code' => $exception->getMessage(),
            ]);
        }

        Auth::guard('web')->login($user, $request->boolean('remember'));
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /** @return array{countryCode: string, mobileNumber: string} */
    private function otpContact(Request $request): array
    {
        $loginType = $request->input('loginType') === 'email' ? 'email' : 'mobile';
        $request->merge(['loginType' => $loginType]);

        $validated = $request->validate([
            'loginType' => ['required', Rule::in(['email', 'mobile'])],
            'email' => ['required_if:loginType,email', 'nullable', 'string', 'email'],
            'countryCode' => ['required_if:loginType,mobile', 'nullable', 'string', 'max:8'],
            'mobileNumber' => ['required_if:loginType,mobile', 'nullable', 'string', 'max:20'],
        ]);

        if ($loginType === 'email') {
            $user = User::query()
                ->where('status', 'active')
                ->where('allow_otp_login', true)
                ->where('email', Str::lower(trim($validated['email'])))
                ->first();

            if ($user === null || ! $user->canAccessPanel() || $user->country_code === null || $user->mobile_number === null) {
                throw ValidationException::withMessages([
                    'email' => 'OTP login is not available for this account.',
                ]);
            }

            return [
                'countryCode' => $user->country_code,
                'mobileNumber' => $user->mobile_number,
            ];
        }

        $phoneKey = PhoneNormalizer::key($validated['countryCode'], $validated['mobileNumber']);
        $user = $phoneKey === null
            ? null
            : User::query()
                ->where('status', 'active')
                ->where('allow_otp_login', true)
                ->where('phone_key', $phoneKey)
                ->first();

        if ($user === null || ! $user->canAccessPanel() || $user->country_code === null || $user->mobile_number === null) {
            throw ValidationException::withMessages([
                'mobileNumber' => 'OTP login is not available for this account.',
            ]);
        }

        return [
            'countryCode' => $user->country_code,
            'mobileNumber' => $user->mobile_number,
        ];
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
