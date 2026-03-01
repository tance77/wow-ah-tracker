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
     * Return a valid Blizzard OAuth2 access token, cached for 23 hours.
     */
    public function getToken(): string
    {
        return Cache::remember('blizzard_token', 82800, function (): string {
            Log::info('BlizzardTokenService: fetching new access token');

            $response = Http::withBasicAuth(
                config('services.blizzard.client_id'),
                config('services.blizzard.client_secret')
            )
                ->asForm()
                ->retry(2, 1000)
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
        });
    }
}
