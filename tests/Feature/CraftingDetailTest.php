<?php

declare(strict_types=1);

use App\Models\CatalogItem;
use App\Models\PriceSnapshot;
use App\Models\Profession;
use App\Models\Recipe;
use App\Models\RecipeReagent;
use App\Models\User;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class)->group('crafting-detail');

// ---------------------------------------------------------------------------
// Helper: create a recipe with known profit (duplicated from CraftingOverviewTest)
// ---------------------------------------------------------------------------

function createDetailRecipeWithProfit(
    Profession $profession,
    string $name,
    int $sellPrice,
    int $reagentCost,
    ?Carbon\Carbon $polledAt = null,
): Recipe {
    $polledAt ??= now();

    $craftedItem = CatalogItem::factory()->create();
    PriceSnapshot::factory()->create([
        'catalog_item_id' => $craftedItem->id,
        'median_price' => $sellPrice,
        'polled_at' => $polledAt,
    ]);

    $reagentItem = CatalogItem::factory()->create();
    PriceSnapshot::factory()->create([
        'catalog_item_id' => $reagentItem->id,
        'median_price' => $reagentCost,
        'polled_at' => $polledAt,
    ]);

    $recipe = Recipe::factory()->create([
        'profession_id' => $profession->id,
        'name' => $name,
        'crafted_item_id_silver' => $craftedItem->id,
        'crafted_item_id_gold' => null,
    ]);

    RecipeReagent::factory()->create([
        'recipe_id' => $recipe->id,
        'catalog_item_id' => $reagentItem->id,
        'quantity' => 1,
    ]);

    return $recipe;
}

// ---------------------------------------------------------------------------
// TABLE-01: Per-profession page shows all recipes
// ---------------------------------------------------------------------------

it('shows all recipes for a profession', function () {
    $user = User::factory()->create();
    $profession = Profession::factory()->create(['name' => 'Alchemy']);

    createDetailRecipeWithProfit($profession, 'Healing Potion', 10000, 2000);
    createDetailRecipeWithProfit($profession, 'Mana Potion', 15000, 3000);
    createDetailRecipeWithProfit($profession, 'Flask of Power', 20000, 4000);

    $response = $this->actingAs($user)->get('/crafting/'.$profession->slug);

    $response->assertOk();
    $response->assertSee('Healing Potion');
    $response->assertSee('Mana Potion');
    $response->assertSee('Flask of Power');
});

// ---------------------------------------------------------------------------
// TABLE-02: Profit columns for each recipe
// ---------------------------------------------------------------------------

it('displays profit columns for each recipe', function () {
    $user = User::factory()->create();
    $profession = Profession::factory()->create(['name' => 'Enchanting']);

    // sell 10000 copper, reagent cost 2000 copper
    // profit_silver = round(10000 * 0.95) - 2000 = 9500 - 2000 = 7500
    createDetailRecipeWithProfit($profession, 'Enchant Weapon', 10000, 2000);

    $response = $this->actingAs($user)->get('/crafting/'.$profession->slug);

    $response->assertOk();

    // Check that key profit data fields are present in the JSON embedded via @js()
    $response->assertSee('reagent_cost');
    $response->assertSee('profit_silver');
    $response->assertSee('median_profit');
    // The actual reagent cost value (2000) should be in the JSON
    $response->assertSee('2000');
    // The profit value (7500) should be in the JSON
    $response->assertSee('7500');
});

// ---------------------------------------------------------------------------
// TABLE-03: Missing prices flagged
// ---------------------------------------------------------------------------

it('flags recipes with missing prices', function () {
    $user = User::factory()->create();
    $profession = Profession::factory()->create(['name' => 'Tailoring']);

    // Recipe with a crafted item FK but no price snapshot for it
    $craftedItem = CatalogItem::factory()->create();
    // Deliberately NOT creating a PriceSnapshot for craftedItem

    $recipe = Recipe::factory()->create([
        'profession_id' => $profession->id,
        'name' => 'Mysterious Cloth',
        'crafted_item_id_silver' => $craftedItem->id,
        'crafted_item_id_gold' => null,
    ]);

    $response = $this->actingAs($user)->get('/crafting/'.$profession->slug);

    $response->assertOk();
    // @js() uses \u0022 for quotes -- check for has_missing_prices:true in the JSON
    $response->assertSee('\u0022has_missing_prices\u0022:true', false);
});

// ---------------------------------------------------------------------------
// TABLE-04: Staleness warning when prices are old
// ---------------------------------------------------------------------------

it('shows staleness warning when prices are old', function () {
    $user = User::factory()->create();
    $profession = Profession::factory()->create(['name' => 'Blacksmithing']);

    // Recipe with price snapshots from 2 hours ago
    createDetailRecipeWithProfit($profession, 'Iron Sword', 10000, 2000, now()->subHours(2));

    $response = $this->actingAs($user)->get('/crafting/'.$profession->slug);

    $response->assertOk();
    // Staleness data should be embedded in the JSON (@js() uses \u0022 for quotes)
    $response->assertSee('\u0022stale\u0022:true', false);
});

// ---------------------------------------------------------------------------
// TABLE-05: Reagent breakdown data
// ---------------------------------------------------------------------------

it('includes reagent breakdown data', function () {
    $user = User::factory()->create();
    $profession = Profession::factory()->create(['name' => 'Leatherworking']);

    $craftedItem = CatalogItem::factory()->create();
    PriceSnapshot::factory()->create([
        'catalog_item_id' => $craftedItem->id,
        'median_price' => 15000,
        'polled_at' => now(),
    ]);

    $reagentItem1 = CatalogItem::factory()->create(['name' => 'Rugged Leather']);
    PriceSnapshot::factory()->create([
        'catalog_item_id' => $reagentItem1->id,
        'median_price' => 500,
        'polled_at' => now(),
    ]);

    $reagentItem2 = CatalogItem::factory()->create(['name' => 'Thick Thread']);
    PriceSnapshot::factory()->create([
        'catalog_item_id' => $reagentItem2->id,
        'median_price' => 100,
        'polled_at' => now(),
    ]);

    $recipe = Recipe::factory()->create([
        'profession_id' => $profession->id,
        'name' => 'Leather Armor',
        'crafted_item_id_silver' => $craftedItem->id,
    ]);

    RecipeReagent::factory()->create([
        'recipe_id' => $recipe->id,
        'catalog_item_id' => $reagentItem1->id,
        'quantity' => 3,
    ]);

    RecipeReagent::factory()->create([
        'recipe_id' => $recipe->id,
        'catalog_item_id' => $reagentItem2->id,
        'quantity' => 2,
    ]);

    $response = $this->actingAs($user)->get('/crafting/'.$profession->slug);

    $response->assertOk();
    // Reagent names should appear in the JSON data
    $response->assertSee('Rugged Leather');
    $response->assertSee('Thick Thread');
    // Quantities should be present (@js() uses \u0022 for quotes)
    $response->assertSee('\u0022quantity\u0022:3', false);
    $response->assertSee('\u0022quantity\u0022:2', false);
    // Unit prices should be present
    $response->assertSee('\u0022unit_price\u0022:500', false);
    $response->assertSee('\u0022unit_price\u0022:100', false);
});

// ---------------------------------------------------------------------------
// TABLE-06: Non-commodity recipes marked
// ---------------------------------------------------------------------------

it('marks non-commodity recipes', function () {
    $user = User::factory()->create();
    $profession = Profession::factory()->create(['name' => 'Jewelcrafting']);

    Recipe::factory()->create([
        'profession_id' => $profession->id,
        'name' => 'Epic Ring',
        'is_commodity' => false,
    ]);

    $response = $this->actingAs($user)->get('/crafting/'.$profession->slug);

    $response->assertOk();
    // @js() uses \u0022 for quotes in JSON output
    $response->assertSee('\u0022is_commodity\u0022:false', false);
});

// ---------------------------------------------------------------------------
// Auth guard
// ---------------------------------------------------------------------------

it('redirects unauthenticated users to login', function () {
    $profession = Profession::factory()->create(['name' => 'Inscription']);

    $response = $this->get('/crafting/'.$profession->slug);

    $response->assertRedirect('/login');
});
