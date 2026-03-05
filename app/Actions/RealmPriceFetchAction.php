<?php

declare(strict_types=1);

namespace App\Actions;

use App\Services\BlizzardTokenService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class RealmPriceFetchAction
{
    public function __construct(
        private readonly BlizzardTokenService $tokenService,
    ) {}

    /**
     * Download realm auction data from Blizzard and persist to a temp file.
     *
     * Streams the response to a temp file. Returns the file path,
     * Last-Modified header, and response hash for gate checks.
     *
     * @return array{tempFilePath: string, lastModified: ?string, responseHash: string}
     */
    public function __invoke(): array
    {
        $region = config('services.blizzard.region', 'us');
        $connectedRealmId = config('services.blizzard.connected_realm_id');
        $token = $this->tokenService->getToken();

        $tempFile = tempnam(sys_get_temp_dir(), 'wow_realm_prices_');

        $response = Http::withToken($token)
            ->retry(2, 5000, throw: false)
            ->timeout(120)
            ->connectTimeout(15)
            ->sink($tempFile)
            ->get("https://{$region}.api.blizzard.com/data/wow/connected-realm/{$connectedRealmId}/auctions", [
                'namespace' => "dynamic-{$region}",
            ]);

        if (! $response->successful()) {
            @unlink($tempFile);
            Log::error('RealmPriceFetchAction: realm auctions fetch failed', [
                'status' => $response->status(),
            ]);

            throw new RuntimeException(
                "Blizzard realm auctions fetch failed: HTTP {$response->status()}"
            );
        }

        $lastModified = $response->header('Last-Modified') ?: null;

        // If sink produced an empty file (Http::fake consumes the stream),
        // fall back to writing body() to the file so tests still work.
        if (filesize($tempFile) === 0) {
            file_put_contents($tempFile, $response->body());
        }

        unset($response);

        $responseHash = md5_file($tempFile);

        // Move to persistent storage so downstream jobs can access it
        $storagePath = 'temp/realm_auctions_'.md5(uniqid((string) mt_rand(), true)).'.json';
        Storage::disk('local')->makeDirectory('temp');
        Storage::disk('local')->put($storagePath, file_get_contents($tempFile));
        @unlink($tempFile);

        $persistedPath = Storage::disk('local')->path($storagePath);

        Log::info('RealmPriceFetchAction: realm auction data downloaded', [
            'path' => $persistedPath,
        ]);

        return [
            'tempFilePath' => $persistedPath,
            'lastModified' => $lastModified,
            'responseHash' => $responseHash,
        ];
    }
}
