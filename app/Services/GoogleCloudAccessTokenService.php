<?php

namespace App\Services;

use Google\Auth\Credentials\ServiceAccountCredentials;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

class GoogleCloudAccessTokenService
{
    public function token(): string
    {
        return Cache::remember('designhub.google_cloud.access_token', now()->addMinutes(50), function (): string {
            $credentialsPath = (string) config('designhub.image_matching.google.credentials');

            if ($credentialsPath === '' || ! is_file($credentialsPath)) {
                throw new RuntimeException('Google Cloud service-account credentials are unavailable.');
            }

            $credentials = new ServiceAccountCredentials(
                'https://www.googleapis.com/auth/cloud-platform',
                $credentialsPath,
            );
            $token = $credentials->fetchAuthToken();
            $accessToken = $token['access_token'] ?? null;

            if (! is_string($accessToken) || $accessToken === '') {
                throw new RuntimeException('Google Cloud did not return an access token.');
            }

            return $accessToken;
        });
    }
}
