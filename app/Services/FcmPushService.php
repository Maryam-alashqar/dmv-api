<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Sends push notifications via Firebase Cloud Messaging's HTTP v1 API.
 *
 * Implemented as a direct HTTP + manually-signed service-account JWT (RS256
 * via openssl, same approach as the Apple identity token verification in
 * AuthController) instead of the Firebase Admin SDK, which pulls in
 * lcobucci/jwt >= 5.3 and therefore requires the sodium PHP extension —
 * not guaranteed to be enabled on every deployment target.
 */
class FcmPushService
{
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    private const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    /**
     * Send a push notification to a single device. Returns false (and logs)
     * on any failure — push delivery is best-effort and must never break
     * the caller (e.g. saving a Notification row).
     */
    public function send(string $deviceToken, string $title, string $body, array $data = []): bool
    {
        $credentials = $this->credentials();

        if (! $credentials) {
            return false;
        }

        $accessToken = $this->accessToken($credentials);

        if (! $accessToken) {
            return false;
        }

        $response = Http::withToken($accessToken)
            ->post("https://fcm.googleapis.com/v1/projects/{$credentials['project_id']}/messages:send", [
                'message' => [
                    'token' => $deviceToken,
                    'notification' => [
                        'title' => $title,
                        'body' => $body,
                    ],
                    'data' => array_map('strval', $data),
                ],
            ]);

        if ($response->failed()) {
            Log::warning('FCM push send failed', ['response' => $response->json()]);

            return false;
        }

        return true;
    }

    private function credentials(): ?array
    {
        $path = config('services.fcm.credentials_path');

        if (! $path || ! is_file($path)) {
            return null;
        }

        $credentials = json_decode((string) file_get_contents($path), true);

        if (! is_array($credentials) || empty($credentials['client_email']) || empty($credentials['private_key']) || empty($credentials['project_id'])) {
            return null;
        }

        return $credentials;
    }

    private function accessToken(array $credentials): ?string
    {
        $cacheKey = 'fcm_access_token:' . $credentials['project_id'];

        if ($cached = Cache::get($cacheKey)) {
            return $cached;
        }

        $now = time();

        $header = $this->base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $payload = $this->base64UrlEncode(json_encode([
            'iss' => $credentials['client_email'],
            'scope' => self::SCOPE,
            'aud' => self::TOKEN_URL,
            'exp' => $now + 3600,
            'iat' => $now,
        ]));

        $signingInput = "{$header}.{$payload}";

        $signed = openssl_sign($signingInput, $signature, $credentials['private_key'], OPENSSL_ALGO_SHA256);

        if (! $signed) {
            Log::warning('FCM service account JWT signing failed.');

            return null;
        }

        $assertion = $signingInput . '.' . $this->base64UrlEncode($signature);

        $response = Http::asForm()->post(self::TOKEN_URL, [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $assertion,
        ]);

        if ($response->failed()) {
            Log::warning('FCM OAuth2 token exchange failed', ['response' => $response->json()]);

            return null;
        }

        $accessToken = $response->json('access_token');
        $expiresIn = (int) $response->json('expires_in', 3600);

        Cache::put($cacheKey, $accessToken, max(60, $expiresIn - 60));

        return $accessToken;
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
