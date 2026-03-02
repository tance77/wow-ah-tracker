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
                            {--dry-run : Show what would be imported without writing to the database}';

    protected $description = 'Import commodity items from the Blizzard Auction House API into the catalog';

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
        $region = config('services.blizzard.region', 'us');
        $token = $tokenService->getToken();
        $dryRun = $this->option('dry-run');
        $fresh = $this->option('fresh');

        // Step 1: Fetch commodities
        // The commodities endpoint returns 50MB+ of JSON, so we bump limits
        // and use a long timeout to handle the large download.
        Log::info('SyncCatalog: starting', ['fresh' => $fresh, 'dry_run' => $dryRun]);
        $this->info('Fetching commodities from Blizzard API (this may take a minute)...');

        // Stream the large (~50MB+) commodities response to a temp file
        // to avoid loading it all into PHP memory at once.
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

        // Step 2: Read the temp file in chunks and extract unique item IDs.
        // This avoids loading the entire 50MB+ payload into memory.
        $uniqueIds = [];
        $handle = fopen($tempFile, 'r');
        $buffer = '';

        while (! feof($handle)) {
            $buffer .= fread($handle, 65536); // 64KB chunks

            // Extract all item IDs found in the current buffer
            if (preg_match_all('/"item":\{"id":(\d+)\}/', $buffer, $matches)) {
                foreach ($matches[1] as $id) {
                    $uniqueIds[(int) $id] = true;
                }
                // Keep only the tail that might contain a partial match
                $lastBrace = strrpos($buffer, '}');
                $buffer = $lastBrace !== false ? substr($buffer, $lastBrace + 1) : '';
            }
        }

        fclose($handle);
        @unlink($tempFile);

        $uniqueIds = collect(array_keys($uniqueIds))->values();

        Log::info('SyncCatalog: commodities parsed', ['unique_items' => $uniqueIds->count()]);
        $this->info(sprintf('Found %s unique item IDs.', number_format($uniqueIds->count())));

        // Step 3: Filter out existing items (unless --fresh)
        if (! $fresh) {
            $existingIds = CatalogItem::pluck('blizzard_item_id')->toArray();
            $newIds = $uniqueIds->diff($existingIds)->values();
            $this->info(sprintf(
                'Skipping %s existing items. %s new items to look up.',
                number_format(count($existingIds)),
                number_format($newIds->count()),
            ));
        } else {
            $newIds = $uniqueIds;
            $this->info(sprintf('--fresh mode: looking up all %s items.', number_format($newIds->count())));
        }

        if ($newIds->isEmpty()) {
            $this->info('Nothing to import — catalog is up to date.');

            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->warn('DRY RUN — no database writes will be made.');
        }

        // Step 4: Look up each item and upsert using concurrent requests.
        // Blizzard allows 100 req/s — send 50 concurrent requests per batch.
        $bar = $this->output->createProgressBar($newIds->count());
        $bar->start();

        $imported = 0;
        $failed = 0;

        foreach ($newIds->chunk(50) as $chunk) {
            $itemIds = $chunk->values()->all();

            // Fire all requests in this batch concurrently
            $responses = Http::pool(fn ($pool) => collect($itemIds)->map(
                fn (int $id) => $pool->as((string) $id)
                    ->withToken($token)
                    ->timeout(30)
                    ->connectTimeout(10)
                    ->get("https://{$region}.api.blizzard.com/data/wow/item/{$id}", [
                        'namespace' => "static-{$region}",
                    ])
            )->all());

            $rateLimited = false;

            foreach ($itemIds as $itemId) {
                $itemResponse = $responses[(string) $itemId] ?? null;

                if (! $itemResponse || $itemResponse instanceof \Throwable) {
                    Log::warning("SyncCatalog: failed to fetch item {$itemId}", [
                        'error' => $itemResponse instanceof \Throwable ? $itemResponse->getMessage() : 'no response',
                    ]);
                    $failed++;
                    $bar->advance();

                    continue;
                }

                if ($itemResponse->status() === 429) {
                    $rateLimited = true;
                    $failed++;
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

                $data = $itemResponse->json();
                $name = $this->resolveLocalizedName($data['name'] ?? null) ?? "Unknown Item {$itemId}";
                $category = $this->resolveCategory($data);

                $bar->clear();
                $this->line("  [{$itemId}] {$name} <fg=gray>({$category})</>");
                $bar->display();

                if ($dryRun) {
                    // dry run — skip DB write
                } else {
                    CatalogItem::updateOrCreate(
                        ['blizzard_item_id' => $itemId],
                        ['name' => $name, 'category' => $category],
                    );
                }

                $imported++;
                $bar->advance();
            }

            if ($rateLimited) {
                Log::warning('SyncCatalog: rate limited by Blizzard API, pausing 10s');
                $this->newLine();
                $this->warn('Rate limited — pausing 10 seconds...');
                sleep(10);
            }

            // Brief pause between batches to stay under rate limit
            usleep(600_000); // 600ms
        }

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

        if ($failed > 0) {
            $this->warn("Some items could not be fetched — re-run to retry.");
        }

        return self::SUCCESS;
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
