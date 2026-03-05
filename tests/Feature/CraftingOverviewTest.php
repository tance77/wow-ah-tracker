<?php

declare(strict_types=1);

use App\Models\CatalogItem;
use App\Models\PriceSnapshot;
use App\Models\Profession;
use App\Models\Recipe;
use App\Models\RecipeReagent;
use App\Models\User;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class)->group('crafting-overview');

// ---------------------------------------------------------------------------
// NAV-01: Navigation and route access
// ---------------------------------------------------------------------------

it('shows crafting nav link for authenticated users', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/crafting');

    $response->assertOk();
    $response->assertSee('Crafting');
});

it('redirects unauthenticated users to login', function () {
    $response = $this->get('/crafting');

    $response->assertRedirect('/login');
});

// ---------------------------------------------------------------------------
// OVERVIEW-01: Profession cards
// ---------------------------------------------------------------------------

it('displays profession cards with names', function () {
    $user = User::factory()->create();
    $profession1 = Profession::factory()->create(['name' => 'Alchemy']);
    $profession2 = Profession::factory()->create(['name' => 'Blacksmithing']);

    // Each profession needs at least one recipe to appear
    Recipe::factory()->create(['profession_id' => $profession1->id]);
    Recipe::factory()->create(['profession_id' => $profession2->id]);

    $response = $this->actingAs($user)->get('/crafting');

    $response->assertOk();
    $response->assertSee('Alchemy');
    $response->assertSee('Blacksmithing');
});

it('shows no profitable recipes message for professions with no profitable recipes', function () {
    $user = User::factory()->create();
    $profession = Profession::factory()->create(['name' => 'Jewelcrafting']);

    // Recipe with no crafted item (median_profit = null)
    Recipe::factory()->create([
        'profession_id' => $profession->id,
        'crafted_item_id_silver' => null,
        'crafted_item_id_gold' => null,
    ]);

    $response = $this->actingAs($user)->get('/crafting');

    $response->assertOk();
    $response->assertSee('No profitable recipes');
});

// ---------------------------------------------------------------------------
// OVERVIEW-02: Top recipes per profession
// ---------------------------------------------------------------------------

it('shows top recipes sorted by median profit descending', function () {
    $user = User::factory()->create();
    $profession = Profession::factory()->create(['name' => 'Enchanting']);

    // Recipe 1: low profit (sell 5000, reagent cost 2000 -> profit ~2750)
    $recipe1 = createRecipeWithProfit($profession, 'Minor Enchant', 5000, 2000);

    // Recipe 2: high profit (sell 20000, reagent cost 2000 -> profit ~17000)
    $recipe2 = createRecipeWithProfit($profession, 'Major Enchant', 20000, 2000);

    // Recipe 3: mid profit (sell 10000, reagent cost 2000 -> profit ~7500)
    $recipe3 = createRecipeWithProfit($profession, 'Medium Enchant', 10000, 2000);

    $response = $this->actingAs($user)->get('/crafting');

    $response->assertOk();
    $response->assertSeeInOrder(['Major Enchant', 'Medium Enchant', 'Minor Enchant']);
});

it('excludes recipes with missing price data from top list', function () {
    $user = User::factory()->create();
    $profession = Profession::factory()->create(['name' => 'Tailoring']);

    // Recipe with prices
    createRecipeWithProfit($profession, 'Priced Recipe', 10000, 2000);

    // Recipe without prices (no crafted item, no reagent prices)
    Recipe::factory()->create([
        'profession_id' => $profession->id,
        'name' => 'Unpriced Recipe',
        'crafted_item_id_silver' => null,
        'crafted_item_id_gold' => null,
    ]);

    $response = $this->actingAs($user)->get('/crafting');

    $response->assertOk();
    $response->assertSee('Priced Recipe');
    $response->assertDontSee('Unpriced Recipe');
});

it('shows recipe stats with profitable count', function () {
    $user = User::factory()->create();
    $profession = Profession::factory()->create(['name' => 'Leatherworking']);

    // 2 profitable recipes
    createRecipeWithProfit($profession, 'Profitable Item A', 10000, 2000);
    createRecipeWithProfit($profession, 'Profitable Item B', 15000, 3000);

    // 1 unprofitable recipe (sell < reagent cost after AH cut)
    createRecipeWithProfit($profession, 'Unprofitable Item', 1000, 5000);

    $response = $this->actingAs($user)->get('/crafting');

    $response->assertOk();
    $response->assertSee('2 of 3 profitable');
});

// ---------------------------------------------------------------------------
// Summary stats in header
// ---------------------------------------------------------------------------

it('shows summary stats in header', function () {
    $user = User::factory()->create();
    $profession1 = Profession::factory()->create(['name' => 'Inscription']);
    $profession2 = Profession::factory()->create(['name' => 'Engineering']);

    createRecipeWithProfit($profession1, 'Glyph A', 10000, 2000);
    createRecipeWithProfit($profession1, 'Glyph B', 15000, 3000);
    createRecipeWithProfit($profession1, 'Glyph C', 20000, 4000);
    createRecipeWithProfit($profession2, 'Gadget A', 12000, 3000);
    createRecipeWithProfit($profession2, 'Gadget B', 18000, 5000);

    $response = $this->actingAs($user)->get('/crafting');

    $response->assertOk();
    // Should show profession count and recipe count
    $response->assertSee('2');  // 2 professions
    $response->assertSee('5');  // 5 recipes
});

// ---------------------------------------------------------------------------
// Helper: create a recipe with known profit
// ---------------------------------------------------------------------------

function createRecipeWithProfit(Profession $profession, string $name, int $sellPrice, int $reagentCost): Recipe
{
    $craftedItem = CatalogItem::factory()->create();
    PriceSnapshot::factory()->create([
        'catalog_item_id' => $craftedItem->id,
        'median_price' => $sellPrice,
        'polled_at' => now(),
    ]);

    $reagentItem = CatalogItem::factory()->create();
    PriceSnapshot::factory()->create([
        'catalog_item_id' => $reagentItem->id,
        'median_price' => $reagentCost,
        'polled_at' => now(),
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
