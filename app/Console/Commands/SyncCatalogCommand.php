<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\SyncCatalogBatchJob;
use App\Models\CatalogItem;
use App\Services\BlizzardTokenService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;
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
                            {--icons-only : Backfill missing icon URLs for existing catalog items}';

    protected $description = 'Import commodity and realm auction items from the Blizzard Auction House API into the catalog';

    public function handle(BlizzardTokenService $tokenService): int
    {
        if ($this->option('tiers-only')) {
            $this->assignQualityTiers();

            return self::SUCCESS;
        }

        if ($this->option('rarity-only')) {
            return $this->backfillRarity($tokenService);
        }

        if ($this->option('icons-only')) {
            return $this->backfillIcons($tokenService);
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

        $this->info(sprintf(
            'Skipping %s existing items. %s remaining to look up.',
            number_format(count($existingIds)),
            number_format($newIds->count()),
        ));

        if ($newIds->isEmpty()) {
            $this->info('Nothing to import — catalog is up to date.');
            @unlink($cacheFile);

            return self::SUCCESS;
        }

        if ($dryRun) {
            $jobCount = (int) ceil($newIds->count() / 200);
            $this->warn("DRY RUN — would dispatch {$jobCount} batch jobs for {$newIds->count()} items.");

            return self::SUCCESS;
        }

        // Step 3: Dispatch batched jobs (200 items per job ≈ under 1 minute each)
        $jobs = [];
        foreach ($newIds->chunk(200) as $chunk) {
            $jobs[] = new SyncCatalogBatchJob(
                $chunk->values()->all(),
                $region,
                $fresh,
            );
        }

        $cacheFilePath = $cacheFile;

        Bus::batch($jobs)
            ->name('sync-catalog')
            ->then(function () use ($cacheFilePath) {
                @unlink($cacheFilePath);

                // Assign quality tiers after all items are imported
                self::runQualityTierAssignment();

                Log::info('SyncCatalog: all batch jobs completed, tiers assigned, cache cleaned.');
            })
            ->catch(function (\Illuminate\Bus\Batch $batch, \Throwable $e) {
                Log::error('SyncCatalog: batch job failed', [
                    'error' => $e->getMessage(),
                ]);
            })
            ->dispatch();

        $this->info(sprintf(
            'Dispatched %s batch jobs for %s items. Jobs will process in the background.',
            number_format(count($jobs)),
            number_format($newIds->count()),
        ));

        Log::info('SyncCatalog: dispatched batch jobs', [
            'job_count' => count($jobs),
            'item_count' => $newIds->count(),
        ]);

        return self::SUCCESS;
    }

    /**
     * Backfill missing icon URLs for existing catalog items.
     */
    private function backfillIcons(BlizzardTokenService $tokenService): int
    {
        $region = config('services.blizzard.region', 'us');
        $token = $tokenService->getToken();

        $items = CatalogItem::whereNull('icon_url')->pluck('blizzard_item_id');

        if ($items->isEmpty()) {
            $this->info('All catalog items already have icon URLs.');

            return self::SUCCESS;
        }

        $this->info(sprintf('Backfilling icons for %s items...', number_format($items->count())));

        $bar = $this->output->createProgressBar($items->count());
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% — %message%');
        $bar->setMessage('Starting...');
        $bar->start();

        $updated = 0;
        $failed = 0;

        foreach ($items->chunk(20) as $chunk) {
            $itemIds = $chunk->values()->all();

            $responses = Http::pool(fn ($pool) => collect($itemIds)->map(
                fn (int $id) => $pool->as((string) $id)
                    ->withToken($token)
                    ->timeout(10)
                    ->connectTimeout(5)
                    ->get("https://{$region}.api.blizzard.com/data/wow/media/item/{$id}", [
                        'namespace' => "static-{$region}",
                    ])
            )->all());

            foreach ($itemIds as $itemId) {
                $response = $responses[(string) $itemId] ?? null;

                if (! $response || $response instanceof \Throwable || ! $response->successful()) {
                    $failed++;
                    $bar->advance();

                    continue;
                }

                $iconUrl = null;
                $assets = $response->json('assets', []);
                foreach ($assets as $asset) {
                    if (($asset['key'] ?? '') === 'icon') {
                        $iconUrl = $asset['value'] ?? null;
                        break;
                    }
                }

                if ($iconUrl) {
                    CatalogItem::where('blizzard_item_id', $itemId)->update(['icon_url' => $iconUrl]);
                    $updated++;
                } else {
                    $failed++;
                }

                $bar->setMessage("Item {$itemId}");
                $bar->advance();
            }

            usleep(1_000_000);
        }

        $bar->setMessage('Done!');
        $bar->finish();
        $this->newLine(2);
        $this->info("Icon backfill complete. Updated: {$updated}. Failed: {$failed}.");

        return self::SUCCESS;
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
     * Run quality tier assignment (callable from batch callback without console context).
     */
    public static function runQualityTierAssignment(): void
    {
        $duplicateNames = CatalogItem::selectRaw('name')
            ->groupBy('name')
            ->havingRaw('count(*) > 1')
            ->pluck('name');

        if ($duplicateNames->isEmpty()) {
            Log::info('SyncCatalog: no duplicate item names — skipping tier assignment.');

            return;
        }

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

        Log::info('SyncCatalog: assigned quality tiers', [
            'items' => $assigned,
            'groups' => $duplicateNames->count(),
        ]);
    }

    /**
     * Assign quality tiers (console-friendly wrapper).
     */
    private function assignQualityTiers(): void
    {
        $this->info('Assigning quality tiers...');
        self::runQualityTierAssignment();
        $this->info('Quality tier assignment complete.');
    }
}
