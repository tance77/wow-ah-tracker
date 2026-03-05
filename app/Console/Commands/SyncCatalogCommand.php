<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\CatalogItem;
use App\Services\BlizzardTokenService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncCatalogCommand extends Command
{
    protected $signature = 'blizzard:sync-catalog
                            {--fresh : Re-sync all items, ignoring existing catalog entries}
                            {--dry-run : Show what would be imported without writing to the database}
                            {--tiers-only : Only run quality tier assignment (no API calls)}
                            {--rarity-only : Re-fetch item data to populate missing rarity values}
                            {--realm : Also fetch item IDs from the connected-realm auctions endpoint (for BoE gear)}
                            {--limit=0 : Max new items to process per run (0 = unlimited, use to avoid server timeouts)}';

    protected $description = 'Import commodity and realm auction items from the Blizzard Auction House API into the catalog';

    /**
     * Map Blizzard item_class.name → category slug.
     * Subclass name is used for finer-grained mapping within Tradeskill.
     */
    private const CLASS_MAP = [
        'Gem'            => 'gem',
        'Consumable'     => 'consumable',
        'Item Enhancement'=> 'enhancement',
        'Reagent'        => 'reagent',
        'Recipe'         => 'recipe',
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

    public function handle(BlizzardTokenService $tokenService): int
    {
        if ($this->option('tiers-only')) {
            $this->assignQualityTiers();

            return self::SUCCESS;
        }

        if ($this->option('rarity-only')) {
            return $this->backfillRarity($tokenService);
        }

        $region = config('services.blizzard.region', 'us');
        $token = $tokenService->getToken();
        $dryRun = $this->option('dry-run');
        $fresh = $this->option('fresh');

        // Step 1: Fetch commodities or reuse cached ID list from a previous run.
        $cacheFile = storage_path('app/sync-catalog-ids.json');

        if (file_exists($cacheFile) && ! $fresh) {
            $uniqueIds = collect(json_decode(file_get_contents($cacheFile), true));
            $this->info(sprintf('Resuming with %s cached item IDs from previous run.', number_format($uniqueIds->count())));
        } else {
            Log::info('SyncCatalog: starting', ['fresh' => $fresh, 'dry_run' => $dryRun]);
            $this->info('Fetching commodities from Blizzard API (this may take a minute)...');

            $tempFile = tempnam(sys_get_temp_dir(), 'wow_commodities_');

            $response = Http::withToken($token)
                ->retry(2, 5000, throw: false)
                ->timeout(120)
                ->connectTimeout(15)
                ->sink($tempFile)
                ->get("https://{$region}.api.blizzard.com/data/wow/auctions/commodities", [
                    'namespace' => "dynamic-{$region}",
                ]);

            if (! $response->successful()) {
                Log::error('SyncCatalog: commodities fetch failed', ['status' => $response->status()]);
                $this->error("Commodities fetch failed: HTTP {$response->status()}");
                @unlink($tempFile);

                return self::FAILURE;
            }

            unset($response);
            $fileSize = filesize($tempFile);
            $this->info(sprintf('Response saved (%s MB), extracting item IDs...', round($fileSize / 1048576, 1)));

            $uniqueIds = [];
            $handle = fopen($tempFile, 'r');
            $buffer = '';

            while (! feof($handle)) {
                $buffer .= fread($handle, 65536);

                if (preg_match_all('/"item":\{"id":(\d+)/', $buffer, $matches)) {
                    foreach ($matches[1] as $id) {
                        $uniqueIds[(int) $id] = true;
                    }
                    $lastItem = strrpos($buffer, '"item"');
                    $buffer = $lastItem !== false ? substr($buffer, $lastItem) : '';
                }
            }

            fclose($handle);
            @unlink($tempFile);

            $uniqueIds = collect(array_keys($uniqueIds))->values();

            // Cache the ID list so resumed runs skip the download
            file_put_contents($cacheFile, $uniqueIds->toJson());

            Log::info('SyncCatalog: commodities parsed', ['unique_items' => $uniqueIds->count()]);
            $this->info(sprintf('Found %s unique item IDs (cached for resume).', number_format($uniqueIds->count())));
        }

        // Step 1b: Optionally fetch realm auctions for BoE items
        if ($this->option('realm')) {
            $connectedRealmId = config('services.blizzard.connected_realm_id');
            $this->info("Fetching realm auctions for connected-realm {$connectedRealmId}...");

            $realmTempFile = tempnam(sys_get_temp_dir(), 'wow_realm_auctions_');

            $realmResponse = Http::withToken($token)
                ->retry(2, 5000, throw: false)
                ->timeout(120)
                ->connectTimeout(15)
                ->sink($realmTempFile)
                ->get("https://{$region}.api.blizzard.com/data/wow/connected-realm/{$connectedRealmId}/auctions", [
                    'namespace' => "dynamic-{$region}",
                ]);

            if (! $realmResponse->successful()) {
                $this->warn("Realm auctions fetch failed: HTTP {$realmResponse->status()} — continuing with commodities only.");
                Log::warning('SyncCatalog: realm auctions fetch failed', [
                    'status' => $realmResponse->status(),
                    'connected_realm_id' => $connectedRealmId,
                ]);
                @unlink($realmTempFile);
            } else {
                unset($realmResponse);
                $realmFileSize = filesize($realmTempFile);
                $this->info(sprintf('Realm response saved (%s MB), extracting item IDs...', round($realmFileSize / 1048576, 1)));

                $realmItemIds = [];
                $handle = fopen($realmTempFile, 'r');
                $buffer = '';

                while (! feof($handle)) {
                    $buffer .= fread($handle, 65536);

                    if (preg_match_all('/"item":\{"id":(\d+)/', $buffer, $matches)) {
                        foreach ($matches[1] as $id) {
                            $realmItemIds[(int) $id] = true;
                        }
                        $lastItem = strrpos($buffer, '"item"');
                        $buffer = $lastItem !== false ? substr($buffer, $lastItem) : '';
                    }
                }

                fclose($handle);
                @unlink($realmTempFile);

                $realmItemIds = collect(array_keys($realmItemIds))->values();

                // Merge with commodity IDs (dedup)
                $beforeCount = $uniqueIds->count();
                $uniqueIds = $uniqueIds->merge($realmItemIds)->unique()->values();
                $realmOnly = $uniqueIds->count() - $beforeCount;

                $this->info(sprintf(
                    'Realm auctions: %s unique items (%s new, not in commodities).',
                    number_format($realmItemIds->count()),
                    number_format($realmOnly),
                ));

                Log::info('SyncCatalog: realm auctions parsed', [
                    'connected_realm_id' => $connectedRealmId,
                    'realm_items' => $realmItemIds->count(),
                    'new_items' => $realmOnly,
                ]);
            }
        }

        // Step 2: Filter out existing items (unless --fresh)
        $existingIds = CatalogItem::pluck('blizzard_item_id')->toArray();
        $newIds = ($fresh ? $uniqueIds : $uniqueIds->diff($existingIds)->values())->sortDesc()->values();

        $limit = (int) $this->option('limit');
        $totalNew = $newIds->count();

        if ($limit > 0 && $totalNew > $limit) {
            $newIds = $newIds->take($limit);
            $this->info(sprintf(
                'Skipping %s existing items. %s remaining — limited to %s this run (%s deferred).',
                number_format(count($existingIds)),
                number_format($totalNew),
                number_format($limit),
                number_format($totalNew - $limit),
            ));
        } else {
            $this->info(sprintf(
                'Skipping %s existing items. %s remaining to look up.',
                number_format(count($existingIds)),
                number_format($newIds->count()),
            ));
        }

        if ($newIds->isEmpty()) {
            $this->info('Nothing to import — catalog is up to date.');

            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->warn('DRY RUN — no database writes will be made.');
        }

        // Step 4: Look up each item and upsert using concurrent requests.
        // Each item requires 2 requests (item data + media icon).
        // Blizzard allows 100 req/s — send 20 items (40 requests) per batch
        // with a 1s pause between batches to stay safely under the limit.
        $bar = $this->output->createProgressBar($newIds->count());
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% — %message%');
        $bar->setMessage('Starting...');
        $bar->start();

        $imported = 0;
        $failed = 0;
        $retryQueue = [];

        foreach ($newIds->chunk(20) as $chunk) {
            $itemIds = $chunk->values()->all();
            $batchResult = $this->processBatch($itemIds, $token, $region, $dryRun, $bar);

            $imported += $batchResult['imported'];
            $failed += $batchResult['failed'];

            // Collect rate-limited items for retry
            if (! empty($batchResult['rateLimited'])) {
                $retryQueue = array_merge($retryQueue, $batchResult['rateLimited']);
                $bar->setMessage('Rate limited — pausing...');
                Log::warning('SyncCatalog: rate limited, pausing 10s', [
                    'queued_for_retry' => count($batchResult['rateLimited']),
                ]);
                sleep(10);
            }

            // Pause between batches to stay under rate limit
            usleep(1_000_000); // 1s
        }

        // Retry rate-limited items in smaller batches
        if (! empty($retryQueue)) {
            $bar->setMessage('Retrying rate-limited items...');
            $this->newLine();
            $this->warn(sprintf('Retrying %s rate-limited items...', number_format(count($retryQueue))));

            foreach (collect($retryQueue)->chunk(10) as $chunk) {
                $itemIds = $chunk->values()->all();
                $batchResult = $this->processBatch($itemIds, $token, $region, $dryRun, $bar);

                $imported += $batchResult['imported'];
                $failed += $batchResult['failed'];

                // If still rate-limited, just count as failed
                $failed += count($batchResult['rateLimited']);

                sleep(2);
            }
        }

        $bar->setMessage('Done!');
        $bar->finish();
        $this->newLine(2);

        // Summary
        $action = $dryRun ? 'Would import' : 'Imported';
        $this->info("{$action} {$imported} items. Failed: {$failed}.");

        Log::info('SyncCatalog: finished', [
            'imported' => $imported,
            'failed' => $failed,
            'dry_run' => $dryRun,
        ]);

        // Clean up cached ID list on successful completion
        $hasMoreItems = $limit > 0 && $totalNew > $limit;
        if ($failed === 0 && ! $hasMoreItems) {
            @unlink($cacheFile);
        } else {
            if ($hasMoreItems) {
                $remaining = $totalNew - $limit;
                $this->warn(sprintf('%s items remaining — re-run to continue.', number_format($remaining)));
            }
            if ($failed > 0) {
                $this->warn('Some items could not be fetched — re-run to resume where you left off.');
            }
        }

        $this->assignQualityTiers();

        return self::SUCCESS;
    }

    /**
     * Process a batch of item IDs concurrently and return results.
     *
     * @return array{imported: int, failed: int, rateLimited: int[]}
     */
    private function processBatch(
        array $itemIds,
        string $token,
        string $region,
        bool $dryRun,
        $bar,
    ): array {
        // Fetch item data concurrently
        $itemResponses = Http::pool(fn ($pool) => collect($itemIds)->map(
            fn (int $id) => $pool->as((string) $id)
                ->withToken($token)
                ->timeout(30)
                ->connectTimeout(10)
                ->get("https://{$region}.api.blizzard.com/data/wow/item/{$id}", [
                    'namespace' => "static-{$region}",
                ])
        )->all());

        // Collect IDs that succeeded so we only fetch media for those
        $successIds = [];
        $imported = 0;
        $failed = 0;
        $rateLimited = [];
        $itemDataMap = [];

        foreach ($itemIds as $itemId) {
            $itemResponse = $itemResponses[(string) $itemId] ?? null;

            if (! $itemResponse || $itemResponse instanceof \Throwable) {
                Log::warning("SyncCatalog: failed to fetch item {$itemId}", [
                    'error' => $itemResponse instanceof \Throwable ? $itemResponse->getMessage() : 'no response',
                ]);
                $failed++;
                $bar->advance();

                continue;
            }

            if ($itemResponse->status() === 429) {
                $rateLimited[] = $itemId;
                $bar->advance();

                continue;
            }

            if (! $itemResponse->successful()) {
                Log::warning("SyncCatalog: failed to fetch item {$itemId}", [
                    'status' => $itemResponse->status(),
                ]);
                $failed++;
                $bar->advance();

                continue;
            }

            $successIds[] = $itemId;
            $itemDataMap[$itemId] = $itemResponse->json();
        }

        // Fetch media/icons concurrently for all successful items
        $mediaResponses = [];
        if (! empty($successIds)) {
            try {
                $mediaResponses = Http::pool(fn ($pool) => collect($successIds)->map(
                    fn (int $id) => $pool->as((string) $id)
                        ->withToken($token)
                        ->timeout(10)
                        ->connectTimeout(5)
                        ->get("https://{$region}.api.blizzard.com/data/wow/media/item/{$id}", [
                            'namespace' => "static-{$region}",
                        ])
                )->all());
            } catch (\Throwable) {
                // Icons are optional — continue without them
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

            $bar->setMessage($name);

            $bar->clear();
            $this->line("  [{$itemId}] {$name} <fg=gray>({$category})</>");
            $bar->display();

            if (! $dryRun) {
                CatalogItem::updateOrCreate(
                    ['blizzard_item_id' => $itemId],
                    ['name' => $name, 'category' => $category, 'rarity' => $rarity, 'icon_url' => $iconUrl],
                );
            }

            $imported++;
            $bar->advance();
        }

        return compact('imported', 'failed', 'rateLimited');
    }

    /**
     * Backfill rarity for catalog items that have null rarity.
     */
    private function backfillRarity(BlizzardTokenService $tokenService): int
    {
        $region = config('services.blizzard.region', 'us');
        $token = $tokenService->getToken();

        $items = CatalogItem::whereNull('rarity')->pluck('blizzard_item_id');

        if ($items->isEmpty()) {
            $this->info('All catalog items already have rarity data.');

            return self::SUCCESS;
        }

        $this->info(sprintf('Backfilling rarity for %s items...', number_format($items->count())));

        $bar = $this->output->createProgressBar($items->count());
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% — %message%');
        $bar->setMessage('Starting...');
        $bar->start();

        $updated = 0;
        $failed = 0;
        $retryQueue = [];

        foreach ($items->chunk(20) as $chunk) {
            $itemIds = $chunk->values()->all();
            $result = $this->fetchRarityBatch($itemIds, $token, $region, $bar);

            $updated += $result['updated'];
            $failed += $result['failed'];

            if (! empty($result['rateLimited'])) {
                $retryQueue = array_merge($retryQueue, $result['rateLimited']);
                $bar->setMessage('Rate limited — pausing...');
                Log::warning('SyncCatalog rarity: rate limited, pausing 10s', [
                    'queued_for_retry' => count($result['rateLimited']),
                ]);
                sleep(10);
            }

            usleep(1_000_000);
        }

        // Retry rate-limited items in smaller batches
        if (! empty($retryQueue)) {
            $bar->setMessage('Retrying rate-limited items...');
            $this->newLine();
            $this->warn(sprintf('Retrying %s rate-limited items...', number_format(count($retryQueue))));

            foreach (collect($retryQueue)->chunk(10) as $chunk) {
                $itemIds = $chunk->values()->all();
                $result = $this->fetchRarityBatch($itemIds, $token, $region, $bar);

                $updated += $result['updated'];
                $failed += $result['failed'];
                $failed += count($result['rateLimited']);

                sleep(2);
            }
        }

        $bar->setMessage('Done!');
        $bar->finish();
        $this->newLine(2);
        $this->info("Rarity backfill complete. Updated: {$updated}. Failed: {$failed}.");

        return self::SUCCESS;
    }

    /**
     * Fetch rarity data for a batch of item IDs concurrently.
     *
     * @return array{updated: int, failed: int, rateLimited: int[]}
     */
    private function fetchRarityBatch(array $itemIds, string $token, string $region, $bar): array
    {
        $responses = Http::pool(fn ($pool) => collect($itemIds)->map(
            fn (int $id) => $pool->as((string) $id)
                ->withToken($token)
                ->timeout(30)
                ->connectTimeout(10)
                ->get("https://{$region}.api.blizzard.com/data/wow/item/{$id}", [
                    'namespace' => "static-{$region}",
                ])
        )->all());

        $updated = 0;
        $failed = 0;
        $rateLimited = [];

        foreach ($itemIds as $itemId) {
            $response = $responses[(string) $itemId] ?? null;

            if (! $response || $response instanceof \Throwable) {
                $failed++;
                $bar->advance();

                continue;
            }

            if ($response->status() === 429) {
                $rateLimited[] = $itemId;
                $bar->advance();

                continue;
            }

            if (! $response->successful()) {
                $failed++;
                $bar->advance();

                continue;
            }

            $rarity = $response->json('quality.type');
            if ($rarity) {
                CatalogItem::where('blizzard_item_id', $itemId)->update(['rarity' => $rarity]);
                $bar->setMessage("Item {$itemId}");
                $updated++;
            }

            $bar->advance();
        }

        return compact('updated', 'failed', 'rateLimited');
    }

    /**
     * Assign quality tiers to items that share the same name.
     * Items with unique names get null (no tier).
     */
    private function assignQualityTiers(): void
    {
        $this->info('Assigning quality tiers...');

        // Find names that appear more than once
        $duplicateNames = CatalogItem::selectRaw('name')
            ->groupBy('name')
            ->havingRaw('count(*) > 1')
            ->pluck('name');

        if ($duplicateNames->isEmpty()) {
            $this->info('No duplicate item names found — skipping tier assignment.');

            return;
        }

        // Clear tiers for unique items that may have had a tier from a previous run
        CatalogItem::whereNotIn('name', $duplicateNames)
            ->whereNotNull('quality_tier')
            ->update(['quality_tier' => null]);

        $assigned = 0;

        foreach ($duplicateNames as $name) {
            $items = CatalogItem::where('name', $name)
                ->orderBy('blizzard_item_id')
                ->get();

            $tier = 1;
            foreach ($items as $item) {
                $item->update(['quality_tier' => $tier]);
                $tier++;
            }

            $assigned += $items->count();
        }

        $this->info("Assigned quality tiers to {$assigned} items across {$duplicateNames->count()} groups.");
    }

    /**
     * Resolve a category string from the Blizzard item response.
     */
    private function resolveCategory(array $data): string
    {
        $className = $this->resolveLocalizedName($data['item_class']['name'] ?? null);
        $subclassName = $this->resolveLocalizedName($data['item_subclass']['name'] ?? null);

        // Tradeskill items get subclass-based categories
        if ($className === 'Tradeskill') {
            return self::TRADESKILL_SUBCLASS_MAP[$subclassName] ?? 'tradeskill';
        }

        return self::CLASS_MAP[$className] ?? 'other';
    }

    /**
     * Blizzard API returns name as either a plain string or a localized
     * object like {"en_US": "Gem", "es_MX": "Gema", ...}.
     */
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
