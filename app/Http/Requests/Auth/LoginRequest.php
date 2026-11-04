<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use App\Support\PhoneNormalizer;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'loginType' => ['nullable', Rule::in(['email', 'mobile'])],
            'email' => ['required_if:loginType,email', 'nullable', 'string', 'email'],
            'countryCode' => ['required_if:loginType,mobile', 'nullable', 'string', 'max:8'],
            'mobileNumber' => ['required_if:loginType,mobile', 'nullable', 'string', 'max:20'],
            'password' => ['required', 'string'],
            'remember' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $loginType = $this->loginType();
        $query = User::query()
            ->where('status', 'active')
            ->where('allow_password_login', true);

        if ($loginType === 'mobile') {
            $phoneKey = PhoneNormalizer::key(
                $this->input('countryCode'),
                $this->input('mobileNumber'),
            );

            if ($phoneKey === null) {
                throw ValidationException::withMessages([
                    'mobileNumber' => 'Enter a valid mobile number.',
                ]);
            }

            $query->where('phone_key', $phoneKey);
        } else {
            $query->where('email', Str::lower(trim($this->string('email')->toString())));
        }

        $user = $query->first();
        $authenticated = $user !== null && Auth::attempt([
            'id' => $user->id,
            'password' => $this->string('password')->toString(),
        ], $this->boolean('remember'));

        if (! $authenticated) {
            RateLimiter::hit($this->throttleKey());
            $errorKey = $loginType === 'mobile' ? 'mobileNumber' : 'email';

            throw ValidationException::withMessages([
                $errorKey => __('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        $errorKey = $this->loginType() === 'mobile' ? 'mobileNumber' : 'email';

        throw ValidationException::withMessages([
            $errorKey => __('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        $identifier = $this->loginType() === 'mobile'
            ? PhoneNormalizer::key($this->input('countryCode'), $this->input('mobileNumber'))
            : Str::lower($this->string('email')->toString());

        return Str::transliterate(($identifier ?? 'invalid').'|'.$this->ip());
    }

    private function loginType(): string
    {
        return $this->input('loginType') === 'mobile' ? 'mobile' : 'email';
    }
}
