<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\CatalogItem;
use App\Models\Profession;
use App\Models\Recipe;
use App\Models\RecipeReagent;
use App\Services\BlizzardTokenService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncRecipesCommand extends Command
{
    protected $signature = 'blizzard:sync-recipes
                            {--dry-run : Traverse API but write zero DB rows}
                            {--report-gaps : Output per-profession table with missing field counts}';

    protected $description = 'Sync Midnight expansion crafting recipes from the Blizzard API';

    /**
     * Blizzard profession IDs for crafting professions (filtering out gathering).
     */
    private const CRAFTING_PROFESSION_IDS = [171, 164, 333, 202, 773, 755, 165, 197, 185];

    public function handle(BlizzardTokenService $tokenService): int
    {
        $token = $tokenService->getToken();
        $region = config('services.blizzard.region', 'us');
        $dryRun = (bool) $this->option('dry-run');
        $reportGaps = (bool) $this->option('report-gaps');

        if ($dryRun) {
            $this->warn('DRY RUN — no database writes will be made.');
        }

        $gapStats = [];
        $totalRecipesSynced = 0;

        // -------------------------------------------------------------------
        // Level 1: Profession index
        // -------------------------------------------------------------------
        $indexResponse = Http::withToken($token)
            ->timeout(30)
            ->get("https://{$region}.api.blizzard.com/data/wow/profession/index", [
                'namespace' => "static-{$region}",
                'locale' => 'en_US',
            ]);

        if (! $indexResponse->successful()) {
            $this->error('Failed to fetch profession index: HTTP ' . $indexResponse->status());

            return self::FAILURE;
        }

        $allProfessions = $indexResponse->json('professions', []);
        $craftingProfessions = array_filter(
            $allProfessions,
            fn (array $p) => in_array($p['id'], self::CRAFTING_PROFESSION_IDS, true)
        );

        $this->info(sprintf(
            'Found %d crafting professions out of %d total.',
            count($craftingProfessions),
            count($allProfessions)
        ));

        // -------------------------------------------------------------------
        // Level 2: For each crafting profession
        // -------------------------------------------------------------------
        foreach ($craftingProfessions as $professionData) {
            $professionId = (int) $professionData['id'];
            $professionName = $this->resolveLocalizedName($professionData['name'] ?? '') ?? "Profession {$professionId}";

            $this->info("Processing profession: {$professionName} (ID: {$professionId})");

            // Fetch profession detail (skill tiers)
            $profResponse = Http::withToken($token)
                ->timeout(30)
                ->get("https://{$region}.api.blizzard.com/data/wow/profession/{$professionId}", [
                    'namespace' => "static-{$region}",
                    'locale' => 'en_US',
                ]);

            if (! $profResponse->successful()) {
                $this->warn("Failed to fetch profession {$professionId}: HTTP " . $profResponse->status());

                continue;
            }

            $skillTiers = $profResponse->json('skill_tiers', []);

            if (empty($skillTiers)) {
                $this->warn("No skill tiers for profession {$professionName} — skipping.");

                continue;
            }

            // Select highest-ID skill tier
            $selectedTier = collect($skillTiers)->sortByDesc('id')->first();
            $tierId = (int) $selectedTier['id'];
            $tierName = $this->resolveLocalizedName($selectedTier['name'] ?? '') ?? "Tier {$tierId}";

            $this->line("  Selected tier: {$tierName} (ID: {$tierId})");

            // Fetch profession media for icon
            $iconUrl = null;
            $mediaResponse = Http::withToken($token)
                ->timeout(15)
                ->get("https://{$region}.api.blizzard.com/data/wow/media/profession/{$professionId}", [
                    'namespace' => "static-{$region}",
                ]);

            if ($mediaResponse->successful()) {
                $assets = $mediaResponse->json('assets', []);
                foreach ($assets as $asset) {
                    if (($asset['key'] ?? '') === 'icon') {
                        $iconUrl = $asset['value'] ?? null;
                        break;
                    }
                }
            }

            // Upsert profession
            if (! $dryRun) {
                Profession::updateOrCreate(
                    ['blizzard_profession_id' => $professionId],
                    [
                        'name' => $professionName,
                        'icon_url' => $iconUrl,
                        'last_synced_at' => now(),
                    ]
                );
            }

            // Fetch skill tier recipe list
            $tierResponse = Http::withToken($token)
                ->timeout(30)
                ->get("https://{$region}.api.blizzard.com/data/wow/profession/{$professionId}/skill-tier/{$tierId}", [
                    'namespace' => "static-{$region}",
                    'locale' => 'en_US',
                ]);

            if (! $tierResponse->successful()) {
                $this->warn("Failed to fetch skill tier {$tierId}: HTTP " . $tierResponse->status());

                continue;
            }

            // Flatten categories[].recipes[] into list of recipe IDs
            $categories = $tierResponse->json('categories', []);
            $recipeList = [];
            foreach ($categories as $category) {
                foreach ($category['recipes'] ?? [] as $recipe) {
                    $recipeList[] = [
                        'id' => (int) $recipe['id'],
                        'name' => $this->resolveLocalizedName($recipe['name'] ?? '') ?? "Recipe {$recipe['id']}",
                    ];
                }
            }

            $this->info("  Found " . count($recipeList) . " recipes in tier.");

            // Initialize gap stats for this profession
            $gapStats[$professionName] = [
                'total' => 0,
                'missing_item' => 0,
                'missing_qty' => 0,
            ];

            // -------------------------------------------------------------------
            // Level 3: Recipe detail batches
            // -------------------------------------------------------------------
            $recipeIds = array_column($recipeList, 'id');
            $recipeNameMap = array_column($recipeList, 'name', 'id');

            $bar = $this->output->createProgressBar(count($recipeIds));
            $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% — %message%');
            $bar->setMessage('Fetching recipes...');
            $bar->start();

            $retryQueue = [];

            foreach (array_chunk($recipeIds, 20) as $batch) {
                $responses = Http::pool(fn ($pool) => collect($batch)->map(
                    fn (int $id) => $pool->as((string) $id)
                        ->withToken($token)
                        ->timeout(30)
                        ->connectTimeout(10)
                        ->get("https://{$region}.api.blizzard.com/data/wow/recipe/{$id}", [
                            'namespace' => "static-{$region}",
                            'locale' => 'en_US',
                        ])
                )->all());

                foreach ($batch as $recipeId) {
                    $response = $responses[(string) $recipeId] ?? null;

                    if (! $response || $response instanceof \Throwable) {
                        Log::warning("SyncRecipes: failed to fetch recipe {$recipeId}", [
                            'error' => $response instanceof \Throwable ? $response->getMessage() : 'no response',
                        ]);
                        $bar->advance();

                        continue;
                    }

                    if ($response->status() === 429) {
                        $retryQueue[] = $recipeId;
                        $bar->advance();

                        continue;
                    }

                    if (! $response->successful()) {
                        Log::warning("SyncRecipes: failed to fetch recipe {$recipeId}", [
                            'status' => $response->status(),
                        ]);
                        $bar->advance();

                        continue;
                    }

                    $recipeData = $response->json();

                    $this->processRecipeResponse(
                        recipeData: $recipeData,
                        recipeId: $recipeId,
                        professionName: $professionName,
                        dryRun: $dryRun,
                        gapStats: $gapStats,
                        totalRecipesSynced: $totalRecipesSynced,
                        bar: $bar,
                    );
                }

                if (! empty($retryQueue)) {
                    $bar->setMessage('Rate limited — pausing 10s...');
                    Log::warning('SyncRecipes: rate limited, pausing 10s', [
                        'queued_for_retry' => count($retryQueue),
                    ]);
                    sleep(10);
                    $retryQueue = [];
                }

                usleep(1_000_000); // 1s pause between batches
            }

            $bar->setMessage('Done!');
            $bar->finish();
            $this->newLine();
        }

        // -------------------------------------------------------------------
        // Report gaps if requested
        // -------------------------------------------------------------------
        if ($reportGaps) {
            $rows = [];
            foreach ($gapStats as $name => $stats) {
                $total = $stats['total'];
                $coverage = $total > 0 ? round((($total - $stats['missing_item']) / $total) * 100, 1) : 0.0;
                $rows[] = [
                    $name,
                    $total,
                    $stats['missing_item'],
                    $stats['missing_qty'],
                    "{$coverage}%",
                ];
            }

            $this->newLine();
            $this->table(
                ['Profession', 'Total', 'Missing Item', 'Missing Qty', 'Coverage %'],
                $rows
            );
        }

        // -------------------------------------------------------------------
        // Summary line
        // -------------------------------------------------------------------
        $totalProfessions = count($gapStats);
        $totalGaps = array_sum(array_column($gapStats, 'missing_item'));
        $this->newLine();
        $this->info(sprintf(
            'Synced %d recipes (%d professions). Gaps: %d recipes missing crafted_item.',
            $totalRecipesSynced,
            $totalProfessions,
            $totalGaps,
        ));

        Log::info('SyncRecipes: finished', [
            'recipes_synced' => $totalRecipesSynced,
            'professions' => $totalProfessions,
            'dry_run' => $dryRun,
        ]);

        return self::SUCCESS;
    }

    /**
     * Process a single recipe API response — upsert Recipe, reagents, and auto-watch.
     */
    private function processRecipeResponse(
        array $recipeData,
        int $recipeId,
        string $professionName,
        bool $dryRun,
        array &$gapStats,
        int &$totalRecipesSynced,
        mixed $bar,
    ): void {
        $name = $this->resolveLocalizedName($recipeData['name'] ?? null) ?? "Recipe {$recipeId}";

        $bar->setMessage($name);

        // Track gap stats
        $gapStats[$professionName]['total']++;

        $hasCraftedItem = isset($recipeData['crafted_item']);
        $hasCraftedQty = isset($recipeData['crafted_quantity']);

        if (! $hasCraftedItem) {
            $gapStats[$professionName]['missing_item']++;
        }
        if (! $hasCraftedQty) {
            $gapStats[$professionName]['missing_qty']++;
        }

        // Resolve crafted item FKs via quality tier lookup
        $craftedItemIdSilver = null;
        $craftedItemIdGold = null;
        $craftedQty = 1;

        if ($hasCraftedItem) {
            $craftedItemName = $this->resolveLocalizedName($recipeData['crafted_item']['name'] ?? null);
            if ($craftedItemName !== null) {
                $matches = CatalogItem::where('name', $craftedItemName)
                    ->orderBy('blizzard_item_id')
                    ->get();

                if ($matches->count() >= 1) {
                    $craftedItemIdSilver = $matches[0]->id;
                }
                if ($matches->count() >= 2) {
                    $craftedItemIdGold = $matches[1]->id;
                }
            }
        }

        if ($hasCraftedQty) {
            $craftedQty = (int) ($recipeData['crafted_quantity']['value']
                ?? $recipeData['crafted_quantity']['minimum']
                ?? 1);
        }

        // Determine is_commodity
        $isCommodity = $this->determineIsCommodity($recipeData, $craftedItemIdSilver);

        if (! $dryRun) {
            // Get profession DB record
            $profession = Profession::where('name', $professionName)->first();

            $recipe = Recipe::updateOrCreate(
                ['blizzard_recipe_id' => $recipeId],
                [
                    'profession_id' => $profession?->id,
                    'name' => $name,
                    'crafted_item_id_silver' => $craftedItemIdSilver,
                    'crafted_item_id_gold' => $craftedItemIdGold,
                    'crafted_quantity' => $craftedQty,
                    'is_commodity' => $isCommodity,
                    'last_synced_at' => now(),
                ]
            );

            // Delete + re-insert reagents for idempotency
            $recipe->reagents()->delete();

            foreach ($recipeData['reagents'] ?? [] as $reagentEntry) {
                $reagentData = $reagentEntry['reagent'] ?? $reagentEntry;
                $reagentItemId = (int) ($reagentData['id'] ?? 0);
                $reagentName = $this->resolveLocalizedName($reagentData['name'] ?? null) ?? "Item {$reagentItemId}";
                $reagentQty = (int) ($reagentEntry['quantity'] ?? 1);

                if ($reagentItemId === 0) {
                    continue;
                }

                // Look up or create minimal CatalogItem for this reagent
                $catalogItem = CatalogItem::where('blizzard_item_id', $reagentItemId)->first();

                if ($catalogItem === null) {
                    Log::warning("SyncRecipes: reagent {$reagentItemId} ({$reagentName}) not in catalog — creating minimal entry.");
                    $catalogItem = CatalogItem::create([
                        'blizzard_item_id' => $reagentItemId,
                        'name' => $reagentName,
                        'category' => 'reagent',
                    ]);
                }

                RecipeReagent::create([
                    'recipe_id' => $recipe->id,
                    'catalog_item_id' => $catalogItem->id,
                    'quantity' => $reagentQty,
                ]);

            }
        }

        $totalRecipesSynced++;
        $bar->advance();
    }

    /**
     * Determine if a crafted item is a commodity (tradeable on commodity AH vs realm AH).
     * Gear-like items (Helm, Chestguard, etc.) are NOT commodities.
     * Items with quality tiers in catalog are commodities.
     */
    private function determineIsCommodity(array $recipeData, ?int $craftedItemIdSilver): bool
    {
        $craftedItemName = $this->resolveLocalizedName(
            $recipeData['crafted_item']['name'] ?? null
        );

        if ($craftedItemName === null) {
            return true; // Default to commodity when unknown
        }

        // Gear keywords indicate realm AH items
        $gearKeywords = ['Helm', 'Chestguard', 'Breastplate', 'Gauntlets', 'Gloves', 'Boots',
            'Greaves', 'Leggings', 'Pauldrons', 'Shoulderguards', 'Belt', 'Girdle',
            'Bracers', 'Cloak', 'Cape', 'Ring', 'Necklace', 'Trinket', 'Shield', 'Sword',
            'Axe', 'Mace', 'Staff', 'Wand', 'Dagger', 'Bow', 'Gun', 'Fist Weapon'];

        foreach ($gearKeywords as $keyword) {
            if (str_contains($craftedItemName, $keyword)) {
                return false;
            }
        }

        // If found in catalog with a quality tier, it's a commodity
        if ($craftedItemIdSilver !== null) {
            $catalogItem = CatalogItem::find($craftedItemIdSilver);
            if ($catalogItem && $catalogItem->quality_tier !== null) {
                return true;
            }
        }

        return true; // Default to commodity
    }

    /**
     * Blizzard API returns name as either a plain string or a localized
     * object like {"en_US": "Alchemy", "es_MX": "Alquimia", ...}.
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
