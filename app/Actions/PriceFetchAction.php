<?php

declare(strict_types=1);

namespace App\Actions;

use App\Services\BlizzardTokenService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class PriceFetchAction
{
    public function __construct(
        private readonly BlizzardTokenService $tokenService,
    ) {}

    /**
     * Fetch commodity listings from Blizzard and filter to watched item IDs.
     *
     * @param  int[]  $itemIds  Blizzard item IDs to include in the result
     * @return array<int, array<string, mixed>>  Filtered auction listings
     */
    public function __invoke(array $itemIds): array
    {
        $region = config('services.blizzard.region', 'us');
        $token = $this->tokenService->getToken();

        $response = Http::withToken($token)
            ->retry(2, 1000, throw: false)
            ->timeout(30)
            ->get("https://{$region}.api.blizzard.com/data/wow/auctions/commodities", [
                'namespace' => "dynamic-{$region}",
            ]);

        if (! $response->successful()) {
            Log::error("PriceFetchAction: commodities fetch failed", [
                'status' => $response->status(),
            ]);

            throw new RuntimeException(
                "Blizzard commodities fetch failed: HTTP {$response->status()}"
            );
        }

        $auctions = $response->json('auctions', []);

        Log::info(sprintf(
            'PriceFetchAction: %d total listings, filtering to %d watched items',
            count($auctions),
            count($itemIds),
        ));

        return array_values(array_filter(
            $auctions,
            fn (array $entry): bool => in_array($entry['item']['id'], $itemIds, strict: true)
        ));
    }
}
