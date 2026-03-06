<?php

declare(strict_types=1);

use App\Models\CatalogItem;
use App\Models\Profession;
use App\Models\Recipe;
use App\Models\RecipeReagent;
use App\Models\User;
use App\Models\WatchedItem;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

// -------------------------------------------------------------------
// Shared helpers
// -------------------------------------------------------------------

/**
 * Set up Http::fake() with all four Blizzard API levels plus OAuth token.
 *
 * Professions:
 *   - 171 = Alchemy (crafting)
 *   - 200 = Gathering (non-crafting, should be filtered out)
 *
 * Skill tiers for Alchemy:
 *   - Multiple tiers, highest ID (tier 2100) should be selected.
 *
 * Recipes in skill tier 2100:
 *   - Recipe 101: Elixir of Power (has crafted_item)
 *   - Recipe 102: Mystery Potion (no crafted_item)
 *   - Recipe 103: Enchanted Vial (has crafted_item, gear-like name to test is_commodity)
 *
 * Reagents:
 *   - Reagent item 50001: Silverleaf (quantity 2)
 *   - Reagent item 50002: Peacebloom (quantity 1)
 *   - Crafted item: 60001 = Elixir of Power (name match in catalog)
 */
function fakeBlizzardRecipeEndpoints(): void
{
    // IMPORTANT: Patterns are matched in order — most-specific patterns MUST come first.
    // The pattern *.api.blizzard.com/data/wow/profession/171* would also match
    // the skill-tier and media URLs, so specific patterns are listed before generic ones.
    Http::fake([
        // OAuth token
        'oauth.battle.net/token' => Http::response(
            ['access_token' => 'test-token', 'token_type' => 'bearer', 'expires_in' => 86400],
            200
        ),

        // Profession index (must come before profession/171 to avoid ambiguity)
        '*.api.blizzard.com/data/wow/profession/index*' => Http::response([
            'professions' => [
                ['id' => 171, 'name' => 'Alchemy'],
                ['id' => 200, 'name' => 'Gathering'],
            ],
        ], 200),

        // Profession media for Alchemy (171) — BEFORE profession/171 generic
        '*.api.blizzard.com/data/wow/media/profession/171*' => Http::response([
            'assets' => [
                ['key' => 'icon', 'value' => 'https://example.com/alchemy-icon.jpg'],
            ],
        ], 200),

        // Skill tier recipe list for tier 2100 — BEFORE profession/171 generic
        '*.api.blizzard.com/data/wow/profession/171/skill-tier/2100*' => Http::response([
            'id' => 2100,
            'name' => 'Midnight Alchemy',
            'categories' => [
                [
                    'name' => 'Elixirs',
                    'recipes' => [
                        ['id' => 101, 'name' => 'Elixir of Power'],
                        ['id' => 102, 'name' => 'Mystery Potion'],
                    ],
                ],
                [
                    'name' => 'Vials',
                    'recipes' => [
                        ['id' => 103, 'name' => 'Enchanted Vial'],
                    ],
                ],
            ],
        ], 200),

        // Profession detail for Alchemy (171) — AFTER more-specific patterns
        '*.api.blizzard.com/data/wow/profession/171*' => Http::response([
            'id' => 171,
            'name' => 'Alchemy',
            'skill_tiers' => [
                ['id' => 2050, 'name' => 'Classic Alchemy'],
                ['id' => 2080, 'name' => 'Wrath Alchemy'],
                ['id' => 2100, 'name' => 'Midnight Alchemy'],
            ],
        ], 200),

        // Recipe 101: Elixir of Power (has crafted_item)
        '*.api.blizzard.com/data/wow/recipe/101*' => Http::response([
            'id' => 101,
            'name' => 'Elixir of Power',
            'crafted_item' => ['id' => 60001, 'name' => 'Elixir of Power', 'key' => ['href' => '']],
            'crafted_quantity' => ['value' => 5.0, 'minimum' => 5.0, 'maximum' => 5.0],
            'reagents' => [
                [
                    'reagent' => ['id' => 50001, 'name' => 'Silverleaf', 'key' => ['href' => '']],
                    'quantity' => 2,
                ],
                [
                    'reagent' => ['id' => 50002, 'name' => 'Peacebloom', 'key' => ['href' => '']],
                    'quantity' => 1,
                ],
            ],
        ], 200),

        // Recipe 102: Mystery Potion (no crafted_item)
        '*.api.blizzard.com/data/wow/recipe/102*' => Http::response([
            'id' => 102,
            'name' => 'Mystery Potion',
            'reagents' => [
                [
                    'reagent' => ['id' => 50001, 'name' => 'Silverleaf', 'key' => ['href' => '']],
                    'quantity' => 3,
                ],
            ],
        ], 200),

        // Recipe 103: Enchanted Vial (has crafted_item, gear-like)
        '*.api.blizzard.com/data/wow/recipe/103*' => Http::response([
            'id' => 103,
            'name' => 'Enchanted Vial',
            'crafted_item' => ['id' => 60002, 'name' => 'Enchanted Vial', 'key' => ['href' => '']],
            'crafted_quantity' => ['value' => 1.0, 'minimum' => 1.0, 'maximum' => 1.0],
            'reagents' => [
                [
                    'reagent' => ['id' => 50002, 'name' => 'Peacebloom', 'key' => ['href' => '']],
                    'quantity' => 2,
                ],
            ],
        ], 200),
    ]);
}

/**
 * Seed the catalog items that the recipes will reference.
 */
function seedRecipeCatalogItems(): void
{
    // Reagent items
    CatalogItem::factory()->create(['blizzard_item_id' => 50001, 'name' => 'Silverleaf']);
    CatalogItem::factory()->create(['blizzard_item_id' => 50002, 'name' => 'Peacebloom']);

    // Crafted items - Elixir of Power exists in catalog (T1/T2 pair)
    CatalogItem::factory()->create([
        'blizzard_item_id' => 60001,
        'name' => 'Elixir of Power',
        'quality_tier' => 1,
    ]);
    CatalogItem::factory()->create([
        'blizzard_item_id' => 60002,
        'name' => 'Enchanted Vial',
        'quality_tier' => null,
    ]);
}

beforeEach(function (): void {
    Cache::forget('blizzard_token');
    // Create user #1 that auto-watch targets
    User::factory()->create(['id' => 1]);
});

// -------------------------------------------------------------------
// Test 1 (IMPORT-01): Seeds professions, recipes, and recipe_reagents
// -------------------------------------------------------------------
it('IMPORT-01: seeds professions, recipes, and recipe_reagents from faked API', function (): void {
    seedRecipeCatalogItems();
    fakeBlizzardRecipeEndpoints();

    $this->artisan('blizzard:sync-recipes')->assertSuccessful();

    // One crafting profession seeded (Alchemy only; Gathering filtered)
    expect(Profession::count())->toBe(1);
    $profession = Profession::first();
    expect($profession->blizzard_profession_id)->toBe(171);
    expect($profession->name)->toBe('Alchemy');
    expect($profession->icon_url)->toBe('https://example.com/alchemy-icon.jpg');

    // Three recipes seeded
    expect(Recipe::count())->toBe(3);

    // Recipe 101 field values
    $recipe101 = Recipe::where('blizzard_recipe_id', 101)->first();
    expect($recipe101)->not->toBeNull();
    expect($recipe101->name)->toBe('Elixir of Power');
    expect($recipe101->crafted_quantity)->toBe(5);
    expect($recipe101->profession_id)->toBe($profession->id);

    // Recipe 102 (no crafted_item) — null FKs
    $recipe102 = Recipe::where('blizzard_recipe_id', 102)->first();
    expect($recipe102->crafted_item_id_silver)->toBeNull();
    expect($recipe102->crafted_item_id_gold)->toBeNull();

    // Reagents: recipe 101 has 2, recipe 102 has 1, recipe 103 has 1
    expect(RecipeReagent::count())->toBe(4);

    $r101Reagents = $recipe101->reagents;
    expect($r101Reagents)->toHaveCount(2);
});

// -------------------------------------------------------------------
// Test 2 (IMPORT-02): sync does not auto-watch items (watchlist is user-managed only)
// -------------------------------------------------------------------
it('IMPORT-02: sync does not auto-watch any items', function (): void {
    seedRecipeCatalogItems();
    fakeBlizzardRecipeEndpoints();

    $this->artisan('blizzard:sync-recipes')->assertSuccessful();

    expect(WatchedItem::count())->toBe(0);
});

// -------------------------------------------------------------------
// Test 3 (IMPORT-03): --dry-run produces zero DB rows
// -------------------------------------------------------------------
it('IMPORT-03: --dry-run traverses API but writes zero rows', function (): void {
    seedRecipeCatalogItems();
    fakeBlizzardRecipeEndpoints();

    $this->artisan('blizzard:sync-recipes --dry-run')->assertSuccessful();

    expect(Profession::count())->toBe(0);
    expect(Recipe::count())->toBe(0);
    expect(RecipeReagent::count())->toBe(0);
});

// -------------------------------------------------------------------
// Test 5 (IMPORT-04): --report-gaps outputs per-profession table
// -------------------------------------------------------------------
it('IMPORT-04: --report-gaps outputs a per-profession table with coverage stats', function (): void {
    seedRecipeCatalogItems();
    fakeBlizzardRecipeEndpoints();

    $this->artisan('blizzard:sync-recipes --report-gaps')
        ->assertSuccessful()
        ->expectsOutputToContain('Alchemy');
});

// -------------------------------------------------------------------
// Test 6 (IMPORT-05): Idempotent — running twice produces identical state
// -------------------------------------------------------------------
it('IMPORT-05: running twice produces identical DB state (idempotent)', function (): void {
    seedRecipeCatalogItems();
    fakeBlizzardRecipeEndpoints();

    $this->artisan('blizzard:sync-recipes')->assertSuccessful();

    $professionCount1 = Profession::count();
    $recipeCount1 = Recipe::count();
    $reagentCount1 = RecipeReagent::count();

    // Re-run with same API responses
    fakeBlizzardRecipeEndpoints();
    $this->artisan('blizzard:sync-recipes')->assertSuccessful();

    expect(Profession::count())->toBe($professionCount1);
    expect(Recipe::count())->toBe($recipeCount1);
    expect(RecipeReagent::count())->toBe($reagentCount1);
});

// -------------------------------------------------------------------
// Test 7 (IMPORT-06): last_synced_at set on every recipe after sync
// -------------------------------------------------------------------
it('IMPORT-06: recipe.last_synced_at is set to current time after sync', function (): void {
    seedRecipeCatalogItems();
    fakeBlizzardRecipeEndpoints();

    $before = now()->subSecond();
    $this->artisan('blizzard:sync-recipes')->assertSuccessful();
    $after = now()->addSecond();

    $recipes = Recipe::all();
    expect($recipes)->not->toBeEmpty();

    foreach ($recipes as $recipe) {
        expect($recipe->last_synced_at)->not->toBeNull();
        expect($recipe->last_synced_at->timestamp)->toBeGreaterThanOrEqual($before->timestamp);
        expect($recipe->last_synced_at->timestamp)->toBeLessThanOrEqual($after->timestamp);
    }
});

// -------------------------------------------------------------------
// Test 8: Missing crafted_item stores NULL FKs
// -------------------------------------------------------------------
it('stores NULL crafted_item_id_silver and crafted_item_id_gold for recipes missing crafted_item', function (): void {
    seedRecipeCatalogItems();
    fakeBlizzardRecipeEndpoints();

    $this->artisan('blizzard:sync-recipes')->assertSuccessful();

    $recipe102 = Recipe::where('blizzard_recipe_id', 102)->first();
    expect($recipe102)->not->toBeNull();
    expect($recipe102->crafted_item_id_silver)->toBeNull();
    expect($recipe102->crafted_item_id_gold)->toBeNull();
});

// -------------------------------------------------------------------
// Test 9: Reagent with unknown blizzard_item_id gets minimal CatalogItem created
// -------------------------------------------------------------------
it('creates a minimal CatalogItem for reagent whose blizzard_item_id is not in catalog_items', function (): void {
    // Only seed the crafted items, not the reagents
    CatalogItem::factory()->create([
        'blizzard_item_id' => 60001,
        'name' => 'Elixir of Power',
        'quality_tier' => 1,
    ]);
    CatalogItem::factory()->create([
        'blizzard_item_id' => 60002,
        'name' => 'Enchanted Vial',
        'quality_tier' => null,
    ]);

    fakeBlizzardRecipeEndpoints();

    $this->artisan('blizzard:sync-recipes')->assertSuccessful();

    // Reagent items 50001 and 50002 should be auto-created as minimal CatalogItems
    expect(CatalogItem::where('blizzard_item_id', 50001)->exists())->toBeTrue();
    expect(CatalogItem::where('blizzard_item_id', 50002)->exists())->toBeTrue();

    // RecipeReagent rows should exist (FK satisfied)
    expect(RecipeReagent::count())->toBeGreaterThan(0);
});

// -------------------------------------------------------------------
// Test 10: Highest-ID skill tier is selected from multiple tiers
// -------------------------------------------------------------------
it('selects the highest-ID skill tier from multiple tiers for a profession', function (): void {
    seedRecipeCatalogItems();
    fakeBlizzardRecipeEndpoints();

    $this->artisan('blizzard:sync-recipes')->assertSuccessful();

    // Tier 2100 (Midnight Alchemy) is highest — should have recipes from that tier
    // All 3 recipes in our fake are in tier 2100
    expect(Recipe::count())->toBe(3);
    expect(Recipe::where('blizzard_recipe_id', 101)->exists())->toBeTrue();
    expect(Recipe::where('blizzard_recipe_id', 102)->exists())->toBeTrue();
    expect(Recipe::where('blizzard_recipe_id', 103)->exists())->toBeTrue();
});
