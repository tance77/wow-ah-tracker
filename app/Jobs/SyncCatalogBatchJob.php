<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\CatalogItem;
use App\Services\BlizzardTokenService;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncCatalogBatchJob implements ShouldQueue
{
    use Batchable, Queueable;

    public int $timeout = 120;

    private const CLASS_MAP = [
        'Gem'             => 'gem',
        'Consumable'      => 'consumable',
        'Item Enhancement' => 'enhancement',
        'Reagent'         => 'reagent',
        'Recipe'          => 'recipe',
    ];

    private const TRADESKILL_SUBCLASS_MAP = [
        'Herb'           => 'herb',
        'Metal & Stone'  => 'ore',
        'Cloth'          => 'cloth',
        'Leather'        => 'leather',
        'Enchanting'     => 'enchanting',
        'Parts'          => 'parts',
        'Elemental'      => 'elemental',
        'Jewelcrafting'  => 'jewelcrafting',
        'Inscription'    => 'inscription',
        'Cooking'        => 'cooking',
    ];

    /**
     * @param  array<int>  $itemIds  Blizzard item IDs to fetch and upsert
     * @param  string  $region  API region (e.g. 'us')
     * @param  bool  $fresh  Whether to force-update existing items
     */
    public function __construct(
        public readonly array $itemIds,
        public readonly string $region,
        public readonly bool $fresh = false,
    ) {}

    public function handle(BlizzardTokenService $tokenService): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $token = $tokenService->getToken();
        $imported = 0;
        $failed = 0;

        foreach (collect($this->itemIds)->chunk(20) as $chunk) {
            $result = $this->processBatch($chunk->values()->all(), $token);
            $imported += $result['imported'];
            $failed += $result['failed'];

            // Retry rate-limited items once
            if (! empty($result['rateLimited'])) {
                Log::info('SyncCatalogBatchJob: rate limited, pausing 10s', [
                    'count' => count($result['rateLimited']),
                ]);
                sleep(10);

                $retry = $this->processBatch($result['rateLimited'], $token);
                $imported += $retry['imported'];
                $failed += $retry['failed'] + count($retry['rateLimited']);
            }

            usleep(1_000_000); // 1s pause between sub-batches
        }

        Log::info('SyncCatalogBatchJob: complete', [
            'imported' => $imported,
            'failed' => $failed,
            'total' => count($this->itemIds),
        ]);
    }

    /**
     * @return array{imported: int, failed: int, rateLimited: int[]}
     */
    private function processBatch(array $itemIds, string $token): array
    {
        $itemResponses = Http::pool(fn ($pool) => collect($itemIds)->map(
            fn (int $id) => $pool->as((string) $id)
                ->withToken($token)
                ->timeout(30)
                ->connectTimeout(10)
                ->get("https://{$this->region}.api.blizzard.com/data/wow/item/{$id}", [
                    'namespace' => "static-{$this->region}",
                ])
        )->all());

        $successIds = [];
        $imported = 0;
        $failed = 0;
        $rateLimited = [];
        $itemDataMap = [];

        foreach ($itemIds as $itemId) {
            $itemResponse = $itemResponses[(string) $itemId] ?? null;

            if (! $itemResponse || $itemResponse instanceof \Throwable) {
                Log::warning("SyncCatalogBatchJob: failed to fetch item {$itemId}", [
                    'error' => $itemResponse instanceof \Throwable ? $itemResponse->getMessage() : 'no response',
                ]);
                $failed++;

                continue;
            }

            if ($itemResponse->status() === 429) {
                $rateLimited[] = $itemId;

                continue;
            }

            if (! $itemResponse->successful()) {
                Log::warning("SyncCatalogBatchJob: failed to fetch item {$itemId}", [
                    'status' => $itemResponse->status(),
                ]);
                $failed++;

                continue;
            }

            $successIds[] = $itemId;
            $itemDataMap[$itemId] = $itemResponse->json();
        }

        // Brief pause before media requests to avoid rate limiting
        usleep(500_000);

        // Fetch media/icons concurrently
        $mediaResponses = [];
        if (! empty($successIds)) {
            try {
                $mediaResponses = Http::pool(fn ($pool) => collect($successIds)->map(
                    fn (int $id) => $pool->as((string) $id)
                        ->withToken($token)
                        ->timeout(10)
                        ->connectTimeout(5)
                        ->get("https://{$this->region}.api.blizzard.com/data/wow/media/item/{$id}", [
                            'namespace' => "static-{$this->region}",
                        ])
                )->all());
            } catch (\Throwable $e) {
                Log::warning('SyncCatalogBatchJob: media fetch failed', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        foreach ($successIds as $itemId) {
            $data = $itemDataMap[$itemId];
            $name = $this->resolveLocalizedName($data['name'] ?? null) ?? "Unknown Item {$itemId}";
            $category = $this->resolveCategory($data);

            $iconUrl = null;
            $mediaResponse = $mediaResponses[(string) $itemId] ?? null;
            if ($mediaResponse && ! ($mediaResponse instanceof \Throwable) && $mediaResponse->successful()) {
                $assets = $mediaResponse->json('assets', []);
                foreach ($assets as $asset) {
                    if (($asset['key'] ?? '') === 'icon') {
                        $iconUrl = $asset['value'] ?? null;
                        break;
                    }
                }
            }

            $rarity = $data['quality']['type'] ?? null;

            CatalogItem::updateOrCreate(
                ['blizzard_item_id' => $itemId],
                ['name' => $name, 'category' => $category, 'rarity' => $rarity, 'icon_url' => $iconUrl],
            );

            $imported++;
        }

        return compact('imported', 'failed', 'rateLimited');
    }

    private function resolveCategory(array $data): string
    {
        $className = $this->resolveLocalizedName($data['item_class']['name'] ?? null);
        $subclassName = $this->resolveLocalizedName($data['item_subclass']['name'] ?? null);

        if ($className === 'Tradeskill') {
            return self::TRADESKILL_SUBCLASS_MAP[$subclassName] ?? 'tradeskill';
        }

        return self::CLASS_MAP[$className] ?? 'other';
    }

    private function resolveLocalizedName(mixed $name): ?string
    {
        if (is_string($name)) {
            return $name;
        }

        if (is_array($name)) {
            return $name['en_US'] ?? reset($name) ?: null;
        }

        return null;
    }
}
