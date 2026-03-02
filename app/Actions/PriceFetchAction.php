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
     * Streams the large (~50MB+) response to a temp file to avoid holding
     * the entire payload in PHP memory, then extracts only watched listings
     * by reading the file in chunks.
     *
     * @param  int[]  $itemIds  Blizzard item IDs to include in the result
     * @return array{listings: array<int, array<string, mixed>>, lastModified: ?string, responseHash: string}
     */
    public function __invoke(array $itemIds): array
    {
        $region = config('services.blizzard.region', 'us');
        $token = $this->tokenService->getToken();

        $tempFile = tempnam(sys_get_temp_dir(), 'wow_prices_');

        $response = Http::withToken($token)
            ->retry(2, 5000, throw: false)
            ->timeout(120)
            ->connectTimeout(15)
            ->sink($tempFile)
            ->get("https://{$region}.api.blizzard.com/data/wow/auctions/commodities", [
                'namespace' => "dynamic-{$region}",
            ]);

        if (! $response->successful()) {
            @unlink($tempFile);
            Log::error('PriceFetchAction: commodities fetch failed', [
                'status' => $response->status(),
            ]);

            throw new RuntimeException(
                "Blizzard commodities fetch failed: HTTP {$response->status()}"
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

        $watchedSet = array_flip($itemIds);
        $listings = $this->extractListings($tempFile, $watchedSet);

        @unlink($tempFile);

        Log::info(sprintf(
            'PriceFetchAction: %d watched listings extracted for %d watched items',
            count($listings),
            count($itemIds),
        ));

        return [
            'listings'     => $listings,
            'lastModified' => $lastModified,
            'responseHash' => $responseHash,
        ];
    }

    /**
     * Stream through the temp file in chunks, decode individual auction
     * objects, and keep only watched items. Memory stays constant because
     * we never hold more than one 128KB chunk + a small carry buffer.
     */
    private function extractListings(string $filePath, array $watchedSet): array
    {
        $handle = fopen($filePath, 'r');
        $listings = [];
        $buffer = '';

        while (! feof($handle)) {
            $buffer .= fread($handle, 131072); // 128KB chunks

            // Match each top-level object in the auctions array.
            // Objects are delimited by },{ or }] at the end.
            while (preg_match('/\{[^{}]*\{"id":(\d+)\}[^{}]*\}/', $buffer, $match, PREG_OFFSET_CAPTURE)) {
                $fullMatch = $match[0][0];
                $offset = $match[0][1];
                $itemId = (int) $match[1][0];

                if (isset($watchedSet[$itemId])) {
                    $entry = json_decode($fullMatch, true);
                    if ($entry) {
                        $listings[] = [
                            'item'       => ['id' => $itemId],
                            'quantity'   => (int) ($entry['quantity'] ?? 0),
                            'unit_price' => (int) ($entry['unit_price'] ?? 0),
                        ];
                    }
                }

                // Advance buffer past this match
                $buffer = substr($buffer, $offset + strlen($fullMatch));
            }

            // Keep tail that might contain a partial object
            if (strlen($buffer) > 500) {
                $lastBrace = strrpos($buffer, '}');
                if ($lastBrace !== false) {
                    $buffer = substr($buffer, $lastBrace + 1);
                }
            }
        }

        fclose($handle);

        return $listings;
    }
}
