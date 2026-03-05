<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class BlizzardTokenService
{
    /**
     * Return a valid Blizzard OAuth2 access token.
     *
     * Validates the cached token against the Blizzard check-token endpoint.
     * If invalid or expired, fetches a new one automatically.
     */
    public function getToken(): string
    {
        $token = Cache::get('blizzard_token');

        if ($token && $this->isTokenValid($token)) {
            return $token;
        }

        if ($token) {
            Log::info('BlizzardTokenService: cached token invalid, refreshing');
            Cache::forget('blizzard_token');
        }

        return Cache::remember('blizzard_token', 82800, fn () => $this->fetchNewToken());
    }

    private function isTokenValid(string $token): bool
    {
        $response = Http::timeout(5)
            ->connectTimeout(3)
            ->post('https://oauth.battle.net/check_token', [
                'token' => $token,
            ]);

        return $response->successful();
    }

    private function fetchNewToken(): string
    {
        Log::info('BlizzardTokenService: fetching new access token');

        $response = Http::withBasicAuth(
            config('services.blizzard.client_id'),
            config('services.blizzard.client_secret')
        )
            ->asForm()
            ->retry(2, 1000, throw: false)
            ->timeout(30)
            ->post('https://oauth.battle.net/token', [
                'grant_type' => 'client_credentials',
            ]);

        if (! $response->successful()) {
            throw new RuntimeException(
                "Blizzard token request failed: HTTP {$response->status()}"
            );
        }

        $token = $response->json('access_token');

        if (empty($token)) {
            throw new RuntimeException('Blizzard token response missing access_token');
        }

        Log::info('BlizzardTokenService: new token acquired');

        return $token;
    }
}
