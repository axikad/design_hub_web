<?php

namespace App\Services;

use App\Models\ProfileContactChallenge;
use App\Models\User;
use App\Support\PhoneNormalizer;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ProfileContactOtpService
{
    /** @return array{type: string, destination: string} */
    public function request(
        User $user,
        string $type,
        ?string $countryCode,
        ?string $mobileNumber,
        ?string $email,
    ): array {
        $contact = $this->contact($type, $countryCode, $mobileNumber, $email);
        $this->ensureAvailable($user, $contact);

        $latest = ProfileContactChallenge::query()
            ->where('user_id', $user->id)
            ->where('type', $type)
            ->where('target', $contact['target'])
            ->whereNull('consumed_at')
            ->latest('id')
            ->first();

        if ($latest !== null) {
            $seconds = (int) config('designhub.otp_resend_seconds');
            if ($latest->last_sent_at->diffInSeconds(now()) < $seconds) {
                throw new \InvalidArgumentException('Please wait before requesting another OTP.');
            }
        }

        $code = $this->generateCode();

        ProfileContactChallenge::query()->create([
            'user_id' => $user->id,
            'type' => $type,
            'target' => $contact['target'],
            'country_code' => $contact['countryCode'],
            'mobile_number' => $contact['mobileNumber'],
            'email' => $contact['email'],
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes((int) config('designhub.otp_ttl_minutes')),
            'last_sent_at' => now(),
        ]);

        if ($type === 'email') {
            Mail::raw(
                "Your Textile Design Hub verification code is {$code}. It expires in ".config('designhub.otp_ttl_minutes').' minutes.',
                fn ($message) => $message
                    ->to($contact['email'])
                    ->subject('Verify your Textile Design Hub email'),
            );
        }

        if (config('designhub.otp_log_code')) {
            Log::info('DesignHub profile contact OTP issued', [
                'user_id' => $user->id,
                'type' => $type,
                'target' => $contact['target'],
                'code' => $code,
            ]);
        }

        return [
            'type' => $type,
            'destination' => $this->mask($type, $contact['target']),
        ];
    }

    public function verify(
        User $user,
        string $type,
        ?string $countryCode,
        ?string $mobileNumber,
        ?string $email,
        string $code,
    ): User {
        $contact = $this->contact($type, $countryCode, $mobileNumber, $email);

        $challenge = ProfileContactChallenge::query()
            ->where('user_id', $user->id)
            ->where('type', $type)
            ->where('target', $contact['target'])
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

        $this->ensureAvailable($user, $contact);

        if ($type === 'mobile') {
            $user->forceFill([
                'country_code' => $contact['countryCode'],
                'mobile_number' => $contact['mobileNumber'],
                'phone_key' => $contact['target'],
            ]);
        } else {
            $user->forceFill([
                'email' => $contact['email'],
                'email_verified_at' => now(),
            ]);
        }

        $user->save();
        $challenge->update(['consumed_at' => now()]);

        ProfileContactChallenge::query()
            ->where('user_id', $user->id)
            ->where('type', $type)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);

        return $user->fresh();
    }

    /** @return array{type: string, target: string, countryCode: ?string, mobileNumber: ?string, email: ?string} */
    private function contact(string $type, ?string $countryCode, ?string $mobileNumber, ?string $email): array
    {
        if ($type === 'mobile') {
            $phoneKey = PhoneNormalizer::key($countryCode, $mobileNumber);
            if ($phoneKey === null) {
                throw new \InvalidArgumentException('Enter a valid mobile number.');
            }

            return [
                'type' => $type,
                'target' => $phoneKey,
                'countryCode' => '+'.(preg_replace('/\D+/', '', (string) $countryCode) ?? ''),
                'mobileNumber' => preg_replace('/\D+/', '', (string) $mobileNumber) ?? '',
                'email' => null,
            ];
        }

        $normalizedEmail = strtolower(trim((string) $email));
        if ($normalizedEmail === '') {
            throw new \InvalidArgumentException('Enter a valid email address.');
        }

        return [
            'type' => $type,
            'target' => $normalizedEmail,
            'countryCode' => null,
            'mobileNumber' => null,
            'email' => $normalizedEmail,
        ];
    }

    /** @param array{type: string, target: string, countryCode: ?string, mobileNumber: ?string, email: ?string} $contact */
    private function ensureAvailable(User $user, array $contact): void
    {
        if ($contact['type'] === 'mobile') {
            if ($user->phone_key === $contact['target']) {
                throw new \InvalidArgumentException('This is already your current mobile number.');
            }

            if (User::query()->where('phone_key', $contact['target'])->where('id', '!=', $user->id)->exists()) {
                throw new \InvalidArgumentException('This mobile number is already registered.');
            }

            return;
        }

        if (strtolower((string) $user->email) === $contact['target']) {
            throw new \InvalidArgumentException('This is already your current email address.');
        }

        if (User::query()->where('email', $contact['target'])->where('id', '!=', $user->id)->exists()) {
            throw new \InvalidArgumentException('This email address is already registered.');
        }
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

    private function mask(string $type, string $target): string
    {
        if ($type === 'mobile') {
            return str_repeat('*', max(strlen($target) - 4, 0)).substr($target, -4);
        }

        [$name, $domain] = explode('@', $target, 2);

        return substr($name, 0, 1).str_repeat('*', max(strlen($name) - 1, 1)).'@'.$domain;
    }
}
