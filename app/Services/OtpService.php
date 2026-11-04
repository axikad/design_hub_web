<?php

namespace App\Services;

use App\Models\Machine;
use App\Models\OtpChallenge;
use App\Models\Role;
use App\Models\SiteSetting;
use App\Models\User;
use App\Support\PhoneNormalizer;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class OtpService
{
    public function request(
        string $flow,
        ?string $name,
        string $countryCode,
        string $mobileNumber,
        array $machineIds = [],
    ): void {
        $phoneKey = PhoneNormalizer::key($countryCode, $mobileNumber);
        if ($phoneKey === null) {
            throw new \InvalidArgumentException('Enter a valid mobile number.');
        }

        $existingUser = User::query()->where('phone_key', $phoneKey)->first();

        if ($flow === 'register') {
            if ($existingUser !== null) {
                throw new \InvalidArgumentException('This mobile number is already registered. Try logging in.');
            }
            $this->validateRegistrationName($name);
        } elseif ($flow === 'login') {
            if ($existingUser === null) {
                throw new \InvalidArgumentException('No account found for this mobile number. Register first.');
            }

            if ($existingUser->status !== 'active' || ! $existingUser->allow_otp_login) {
                throw new \InvalidArgumentException('OTP login is not available for this account.');
            }
        } else {
            throw new \InvalidArgumentException('Invalid authentication flow.');
        }

        $latest = OtpChallenge::query()
            ->where('phone_key', $phoneKey)
            ->whereNull('consumed_at')
            ->latest('id')
            ->first();

        if ($latest !== null) {
            $seconds = config('designhub.otp_resend_seconds');
            if ($latest->last_sent_at->diffInSeconds(now()) < $seconds) {
                throw new \InvalidArgumentException('Please wait before requesting another OTP.');
            }
        }

        $code = $this->generateCode();
        OtpChallenge::query()->create([
            'phone_key' => $phoneKey,
            'flow' => $flow,
            'name' => $flow === 'register' ? trim((string) $name) : null,
            'machine_ids' => $flow === 'register' ? array_values($machineIds) : null,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes((int) config('designhub.otp_ttl_minutes')),
            'last_sent_at' => now(),
        ]);

        if (config('designhub.otp_log_code')) {
            Log::info('DesignHub OTP issued', ['phone_key' => $phoneKey, 'code' => $code]);
        }
    }

    public function verify(
        string $flow,
        ?string $name,
        string $countryCode,
        string $mobileNumber,
        string $code,
        array $machineIds = [],
    ): User {
        $phoneKey = PhoneNormalizer::key($countryCode, $mobileNumber);
        if ($phoneKey === null) {
            throw new \InvalidArgumentException('Enter a valid mobile number.');
        }

        $challenge = OtpChallenge::query()
            ->where('phone_key', $phoneKey)
            ->where('flow', $flow)
            ->whereNull('consumed_at')
            ->latest('id')
            ->first();

        if ($challenge === null) {
            throw new \InvalidArgumentException('Request a new OTP to continue.');
        }

        if ($challenge->expires_at->isPast()) {
            throw new \InvalidArgumentException('This OTP has expired. Request a new code.');
        }

        if ($challenge->attempts >= (int) config('designhub.otp_max_attempts')) {
            throw new \InvalidArgumentException('Too many attempts. Request a new OTP.');
        }

        $challenge->increment('attempts');

        if (! Hash::check($code, $challenge->code_hash)) {
            throw new \InvalidArgumentException('The OTP you entered is incorrect.');
        }

        $challenge->update(['consumed_at' => now()]);

        if ($flow === 'register') {
            $this->validateRegistrationName($name ?? $challenge->name);
            $requestedMachineIds = collect($challenge->machine_ids ?? [])->sort()->values();
            $verifiedMachineIds = collect($machineIds)->sort()->values();

            if ($requestedMachineIds->isEmpty() || $requestedMachineIds->all() !== $verifiedMachineIds->all()) {
                throw new \InvalidArgumentException('Selected machines do not match the registration request. Request a new OTP.');
            }

            $mobileRegistrationRole = SiteSetting::current()->defaultMobileRegistrationRole;

            if ($mobileRegistrationRole === null || $mobileRegistrationRole->isSuperAdmin() || $mobileRegistrationRole->hasPermission('dashboard.read')) {
                $mobileRegistrationRole = Role::query()->firstOrCreate(
                    ['slug' => 'marketplace_user'],
                    ['name' => 'Marketplace User'],
                );
            }

            $user = User::query()->create([
                'role_id' => $mobileRegistrationRole->id,
                'name' => trim((string) ($name ?? $challenge->name)),
                'country_code' => $countryCode,
                'mobile_number' => preg_replace('/\D+/', '', $mobileNumber),
                'phone_key' => $phoneKey,
                'email' => null,
                'password' => null,
                'allow_otp_login' => true,
                'allow_password_login' => false,
            ]);

            $machineDatabaseIds = Machine::query()
                ->whereIn('public_id', $verifiedMachineIds)
                ->where('is_active', true)
                ->pluck('id');
            $user->machines()->sync($machineDatabaseIds);

            return $user->load('machines');
        }

        $user = User::query()->where('phone_key', $phoneKey)->first();
        if ($user === null) {
            throw new \InvalidArgumentException('No account found for this mobile number.');
        }

        if ($user->status !== 'active' || ! $user->allow_otp_login) {
            throw new \InvalidArgumentException('OTP login is not available for this account.');
        }

        return $user;
    }

    private function generateCode(): string
    {
        $length = (int) config('designhub.otp_length');
        $staticCode = config('designhub.otp_static_code');

        if (
            (app()->environment('local') || config('designhub.otp_static_enabled'))
            && is_string($staticCode)
            && strlen($staticCode) === $length
            && ctype_digit($staticCode)
        ) {
            return $staticCode;
        }

        return str_pad((string) random_int(0, (10 ** $length) - 1), $length, '0', STR_PAD_LEFT);
    }

    private function validateRegistrationName(?string $name): void
    {
        $trimmed = trim((string) $name);
        $len = Str::length($trimmed);
        if ($len < 2 || $len > 40) {
            throw new \InvalidArgumentException('Name must be between 2 and 40 characters.');
        }
    }
}
