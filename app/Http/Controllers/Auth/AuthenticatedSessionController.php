<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\OtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
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
        $validated = $request->validate([
            'countryCode' => ['required', 'string', 'max:8'],
            'mobileNumber' => ['required', 'string', 'max:20'],
        ]);

        try {
            $this->otp->request(
                'login',
                null,
                $validated['countryCode'],
                $validated['mobileNumber'],
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
        $validated = $request->validate([
            'countryCode' => ['required', 'string', 'max:8'],
            'mobileNumber' => ['required', 'string', 'max:20'],
            'code' => ['required', 'string', 'size:4'],
            'remember' => ['sometimes', 'boolean'],
        ]);

        try {
            $user = $this->otp->verify(
                'login',
                null,
                $validated['countryCode'],
                $validated['mobileNumber'],
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
