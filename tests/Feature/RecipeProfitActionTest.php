<?php

declare(strict_types=1);

use App\Actions\RecipeProfitAction;
use App\Models\CatalogItem;
use App\Models\PriceSnapshot;
use App\Models\Recipe;
use App\Models\RecipeReagent;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class)->group('recipe-profit-action');

// ---------------------------------------------------------------------------
// PROFIT-01: Reagent cost = sum of (quantity × median_price)
// ---------------------------------------------------------------------------

test('PROFIT-01: calculates reagent cost as sum of quantity times median price', function () {
    // Reagent 1: qty 3 × 1000 copper = 3000
    // Reagent 2: qty 2 × 2000 copper = 4000
    // Total reagent cost = 7000
    $reagentItem1 = CatalogItem::factory()->create();
    PriceSnapshot::factory()->create([
        'catalog_item_id' => $reagentItem1->id,
        'median_price'    => 1000,
        'polled_at'       => now(),
    ]);

    $reagentItem2 = CatalogItem::factory()->create();
    PriceSnapshot::factory()->create([
        'catalog_item_id' => $reagentItem2->id,
        'median_price'    => 2000,
        'polled_at'       => now(),
    ]);

    $recipe = Recipe::factory()->create([
        'crafted_item_id_silver' => null,
        'crafted_item_id_gold'   => null,
    ]);

    RecipeReagent::factory()->create([
        'recipe_id'       => $recipe->id,
        'catalog_item_id' => $reagentItem1->id,
        'quantity'        => 3,
    ]);

    RecipeReagent::factory()->create([
        'recipe_id'       => $recipe->id,
        'catalog_item_id' => $reagentItem2->id,
        'quantity'        => 2,
    ]);

    $recipe->load([
        'reagents.catalogItem.priceSnapshots' => fn ($q) => $q->latest('polled_at')->limit(1),
        'craftedItemSilver.priceSnapshots'    => fn ($q) => $q->latest('polled_at')->limit(1),
        'craftedItemGold.priceSnapshots'      => fn ($q) => $q->latest('polled_at')->limit(1),
    ]);

    $result = (new RecipeProfitAction())($recipe);

    expect($result['reagent_cost'])->toBe(7000)
        ->and($result['has_missing_prices'])->toBeFalse();
});

test('PROFIT-01 NULL: reagent cost is null when any reagent has no price snapshot', function () {
    $reagentItem1 = CatalogItem::factory()->create();
    PriceSnapshot::factory()->create([
        'catalog_item_id' => $reagentItem1->id,
        'median_price'    => 1000,
        'polled_at'       => now(),
    ]);

    // Reagent 2 has NO price snapshot
    $reagentItem2 = CatalogItem::factory()->create();

    $recipe = Recipe::factory()->create([
        'crafted_item_id_silver' => null,
        'crafted_item_id_gold'   => null,
    ]);

    RecipeReagent::factory()->create([
        'recipe_id'       => $recipe->id,
        'catalog_item_id' => $reagentItem1->id,
        'quantity'        => 3,
    ]);

    RecipeReagent::factory()->create([
        'recipe_id'       => $recipe->id,
        'catalog_item_id' => $reagentItem2->id,
        'quantity'        => 2,
    ]);

    $recipe->load([
        'reagents.catalogItem.priceSnapshots' => fn ($q) => $q->latest('polled_at')->limit(1),
        'craftedItemSilver.priceSnapshots'    => fn ($q) => $q->latest('polled_at')->limit(1),
        'craftedItemGold.priceSnapshots'      => fn ($q) => $q->latest('polled_at')->limit(1),
    ]);

    $result = (new RecipeProfitAction())($recipe);

    expect($result['reagent_cost'])->toBeNull()
        ->and($result['has_missing_prices'])->toBeTrue();
});

// ---------------------------------------------------------------------------
// PROFIT-02: Sell prices for Silver and Gold tiers
// ---------------------------------------------------------------------------

test('PROFIT-02: returns sell price for silver and gold tiers from latest price snapshots', function () {
    $silverItem = CatalogItem::factory()->create();
    PriceSnapshot::factory()->create([
        'catalog_item_id' => $silverItem->id,
        'median_price'    => 10000,
        'polled_at'       => now(),
    ]);

    $goldItem = CatalogItem::factory()->create();
    PriceSnapshot::factory()->create([
        'catalog_item_id' => $goldItem->id,
        'median_price'    => 15000,
        'polled_at'       => now(),
    ]);

    $recipe = Recipe::factory()->create([
        'crafted_item_id_silver' => $silverItem->id,
        'crafted_item_id_gold'   => $goldItem->id,
    ]);

    $recipe->load([
        'reagents.catalogItem.priceSnapshots' => fn ($q) => $q->latest('polled_at')->limit(1),
        'craftedItemSilver.priceSnapshots'    => fn ($q) => $q->latest('polled_at')->limit(1),
        'craftedItemGold.priceSnapshots'      => fn ($q) => $q->latest('polled_at')->limit(1),
    ]);

    $result = (new RecipeProfitAction())($recipe);

    expect($result['sell_price_silver'])->toBe(10000)
        ->and($result['sell_price_gold'])->toBe(15000);
});

test('PROFIT-02 NULL: both sell prices are null when recipe has no crafted item FKs', function () {
    $recipe = Recipe::factory()->create([
        'crafted_item_id_silver' => null,
        'crafted_item_id_gold'   => null,
    ]);

    $recipe->load([
        'reagents.catalogItem.priceSnapshots' => fn ($q) => $q->latest('polled_at')->limit(1),
        'craftedItemSilver.priceSnapshots'    => fn ($q) => $q->latest('polled_at')->limit(1),
        'craftedItemGold.priceSnapshots'      => fn ($q) => $q->latest('polled_at')->limit(1),
    ]);

    $result = (new RecipeProfitAction())($recipe);

    expect($result['sell_price_silver'])->toBeNull()
        ->and($result['sell_price_gold'])->toBeNull();
});

// ---------------------------------------------------------------------------
// PROFIT-03: Profit formula: (sell_price * 0.95) - reagent_cost
// ---------------------------------------------------------------------------

test('PROFIT-03: sell=10000 reagent=5000 produces profit=4500', function () {
    $silverItem = CatalogItem::factory()->create();
    PriceSnapshot::factory()->create([
        'catalog_item_id' => $silverItem->id,
        'median_price'    => 10000,
        'polled_at'       => now(),
    ]);

    $reagentItem = CatalogItem::factory()->create();
    PriceSnapshot::factory()->create([
        'catalog_item_id' => $reagentItem->id,
        'median_price'    => 5000,
        'polled_at'       => now(),
    ]);

    $recipe = Recipe::factory()->create([
        'crafted_item_id_silver' => $silverItem->id,
        'crafted_item_id_gold'   => null,
    ]);

    RecipeReagent::factory()->create([
        'recipe_id'       => $recipe->id,
        'catalog_item_id' => $reagentItem->id,
        'quantity'        => 1,
    ]);

    $recipe->load([
        'reagents.catalogItem.priceSnapshots' => fn ($q) => $q->latest('polled_at')->limit(1),
        'craftedItemSilver.priceSnapshots'    => fn ($q) => $q->latest('polled_at')->limit(1),
        'craftedItemGold.priceSnapshots'      => fn ($q) => $q->latest('polled_at')->limit(1),
    ]);

    $result = (new RecipeProfitAction())($recipe);

    // (int) round(10000 * 0.95) - 5000 = 9500 - 5000 = 4500
    expect($result['profit_silver'])->toBe(4500);
});

test('PROFIT-03: negative profit is returned as-is when reagent cost exceeds sell price', function () {
    $silverItem = CatalogItem::factory()->create();
    PriceSnapshot::factory()->create([
        'catalog_item_id' => $silverItem->id,
        'median_price'    => 1000,
        'polled_at'       => now(),
    ]);

    $reagentItem = CatalogItem::factory()->create();
    PriceSnapshot::factory()->create([
        'catalog_item_id' => $reagentItem->id,
        'median_price'    => 5000,
        'polled_at'       => now(),
    ]);

    $recipe = Recipe::factory()->create([
        'crafted_item_id_silver' => $silverItem->id,
        'crafted_item_id_gold'   => null,
    ]);

    RecipeReagent::factory()->create([
        'recipe_id'       => $recipe->id,
        'catalog_item_id' => $reagentItem->id,
        'quantity'        => 1,
    ]);

    $recipe->load([
        'reagents.catalogItem.priceSnapshots' => fn ($q) => $q->latest('polled_at')->limit(1),
        'craftedItemSilver.priceSnapshots'    => fn ($q) => $q->latest('polled_at')->limit(1),
        'craftedItemGold.priceSnapshots'      => fn ($q) => $q->latest('polled_at')->limit(1),
    ]);

    $result = (new RecipeProfitAction())($recipe);

    // (int) round(1000 * 0.95) - 5000 = 950 - 5000 = -4050
    expect($result['profit_silver'])->toBe(-4050);
});

// ---------------------------------------------------------------------------
// PROFIT-04: Median profit across quality tiers
// ---------------------------------------------------------------------------

test('PROFIT-04: median profit is average of silver and gold profits when both present', function () {
    $silverItem = CatalogItem::factory()->create();
    PriceSnapshot::factory()->create([
        'catalog_item_id' => $silverItem->id,
        'median_price'    => 10000,
        'polled_at'       => now(),
    ]);

    $goldItem = CatalogItem::factory()->create();
    PriceSnapshot::factory()->create([
        'catalog_item_id' => $goldItem->id,
        'median_price'    => 20000,
        'polled_at'       => now(),
    ]);

    $reagentItem = CatalogItem::factory()->create();
    PriceSnapshot::factory()->create([
        'catalog_item_id' => $reagentItem->id,
        'median_price'    => 5000,
        'polled_at'       => now(),
    ]);

    $recipe = Recipe::factory()->create([
        'crafted_item_id_silver' => $silverItem->id,
        'crafted_item_id_gold'   => $goldItem->id,
    ]);

    RecipeReagent::factory()->create([
        'recipe_id'       => $recipe->id,
        'catalog_item_id' => $reagentItem->id,
        'quantity'        => 1,
    ]);

    $recipe->load([
        'reagents.catalogItem.priceSnapshots' => fn ($q) => $q->latest('polled_at')->limit(1),
        'craftedItemSilver.priceSnapshots'    => fn ($q) => $q->latest('polled_at')->limit(1),
        'craftedItemGold.priceSnapshots'      => fn ($q) => $q->latest('polled_at')->limit(1),
    ]);

    $result = (new RecipeProfitAction())($recipe);

    // profit_silver = round(10000 * 0.95) - 5000 = 9500 - 5000 = 4500
    // profit_gold   = round(20000 * 0.95) - 5000 = 19000 - 5000 = 14000
    // median = round((4500 + 14000) / 2) = round(9250) = 9250
    expect($result['profit_silver'])->toBe(4500)
        ->and($result['profit_gold'])->toBe(14000)
        ->and($result['median_profit'])->toBe(9250);
});

test('PROFIT-04: median profit equals silver profit when only silver tier present', function () {
    $silverItem = CatalogItem::factory()->create();
    PriceSnapshot::factory()->create([
        'catalog_item_id' => $silverItem->id,
        'median_price'    => 10000,
        'polled_at'       => now(),
    ]);

    $reagentItem = CatalogItem::factory()->create();
    PriceSnapshot::factory()->create([
        'catalog_item_id' => $reagentItem->id,
        'median_price'    => 5000,
        'polled_at'       => now(),
    ]);

    $recipe = Recipe::factory()->create([
        'crafted_item_id_silver' => $silverItem->id,
        'crafted_item_id_gold'   => null,
    ]);

    RecipeReagent::factory()->create([
        'recipe_id'       => $recipe->id,
        'catalog_item_id' => $reagentItem->id,
        'quantity'        => 1,
    ]);

    $recipe->load([
        'reagents.catalogItem.priceSnapshots' => fn ($q) => $q->latest('polled_at')->limit(1),
        'craftedItemSilver.priceSnapshots'    => fn ($q) => $q->latest('polled_at')->limit(1),
        'craftedItemGold.priceSnapshots'      => fn ($q) => $q->latest('polled_at')->limit(1),
    ]);

    $result = (new RecipeProfitAction())($recipe);

    // profit_silver = 4500, no gold tier
    // median = profit_silver = 4500
    expect($result['profit_silver'])->toBe(4500)
        ->and($result['profit_gold'])->toBeNull()
        ->and($result['median_profit'])->toBe(4500);
});

test('PROFIT-04: median profit is null when neither tier present', function () {
    $recipe = Recipe::factory()->create([
        'crafted_item_id_silver' => null,
        'crafted_item_id_gold'   => null,
    ]);

    $recipe->load([
        'reagents.catalogItem.priceSnapshots' => fn ($q) => $q->latest('polled_at')->limit(1),
        'craftedItemSilver.priceSnapshots'    => fn ($q) => $q->latest('polled_at')->limit(1),
        'craftedItemGold.priceSnapshots'      => fn ($q) => $q->latest('polled_at')->limit(1),
    ]);

    $result = (new RecipeProfitAction())($recipe);

    expect($result['profit_silver'])->toBeNull()
        ->and($result['profit_gold'])->toBeNull()
        ->and($result['median_profit'])->toBeNull();
});

// ---------------------------------------------------------------------------
// has_missing_prices: crafted item exists but has no snapshot
// ---------------------------------------------------------------------------

test('has_missing_prices is true when crafted item exists but has no price snapshot', function () {
    // Silver item has no snapshot
    $silverItem = CatalogItem::factory()->create();

    $recipe = Recipe::factory()->create([
        'crafted_item_id_silver' => $silverItem->id,
        'crafted_item_id_gold'   => null,
    ]);

    $recipe->load([
        'reagents.catalogItem.priceSnapshots' => fn ($q) => $q->latest('polled_at')->limit(1),
        'craftedItemSilver.priceSnapshots'    => fn ($q) => $q->latest('polled_at')->limit(1),
        'craftedItemGold.priceSnapshots'      => fn ($q) => $q->latest('polled_at')->limit(1),
    ]);

    $result = (new RecipeProfitAction())($recipe);

    expect($result['sell_price_silver'])->toBeNull()
        ->and($result['has_missing_prices'])->toBeTrue();
});

// ---------------------------------------------------------------------------
// Return array completeness: all 7 keys must be present
// ---------------------------------------------------------------------------

test('return array contains all 7 expected keys', function () {
    $recipe = Recipe::factory()->create([
        'crafted_item_id_silver' => null,
        'crafted_item_id_gold'   => null,
    ]);

    $recipe->load([
        'reagents.catalogItem.priceSnapshots' => fn ($q) => $q->latest('polled_at')->limit(1),
        'craftedItemSilver.priceSnapshots'    => fn ($q) => $q->latest('polled_at')->limit(1),
        'craftedItemGold.priceSnapshots'      => fn ($q) => $q->latest('polled_at')->limit(1),
    ]);

    $result = (new RecipeProfitAction())($recipe);

    expect($result)->toHaveKeys([
        'reagent_cost',
        'sell_price_silver',
        'sell_price_gold',
        'profit_silver',
        'profit_gold',
        'median_profit',
        'has_missing_prices',
    ]);
});
