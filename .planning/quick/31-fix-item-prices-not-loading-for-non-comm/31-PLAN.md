---
phase: quick-31
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - app/Models/CatalogItem.php
  - resources/views/livewire/pages/crafting-detail.blade.php
  - resources/views/livewire/pages/crafting.blade.php
  - app/Models/Shuffle.php
  - resources/views/livewire/pages/dashboard.blade.php
  - resources/views/livewire/pages/item-detail.blade.php
  - tests/Feature/CraftingDetailTest.php
autonomous: true
requirements: [quick-31]
must_haves:
  truths:
    - "Non-commodity recipe items display reagent costs and profit data when price snapshots exist"
    - "Commodity recipe items continue to display prices correctly"
    - "Dashboard watched items show current price for both commodity and non-commodity items"
  artifacts:
    - path: "app/Models/CatalogItem.php"
      provides: "latestPriceSnapshot HasOne relationship using latestOfMany"
      contains: "latestOfMany"
    - path: "resources/views/livewire/pages/crafting-detail.blade.php"
      provides: "Eager loading using latestPriceSnapshot instead of limit(1)"
  key_links:
    - from: "resources/views/livewire/pages/crafting-detail.blade.php"
      to: "app/Models/CatalogItem.php"
      via: "eager loading latestPriceSnapshot relationship"
      pattern: "latestPriceSnapshot"
---

<objective>
Fix item prices not loading for non-commodity items across the application.

Purpose: The eager loading pattern `priceSnapshots => fn($q) => $q->latest('polled_at')->limit(1)` applies LIMIT globally in SQL, not per parent CatalogItem. With many items, most CatalogItems end up with empty priceSnapshots collections. Non-commodity items (BoE gear from realm AH) are disproportionately affected because they have fewer snapshots. The fix is to add a `latestPriceSnapshot` HasOne relationship on CatalogItem using `latestOfMany('polled_at')`, which generates a correct per-parent subquery.

Output: All price lookups use the new `latestPriceSnapshot` relationship, ensuring every CatalogItem with price data displays its most recent price.
</objective>

<execution_context>
@/Users/lancethompson/.claude/get-shit-done/workflows/execute-plan.md
@/Users/lancethompson/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@app/Models/CatalogItem.php
@app/Models/PriceSnapshot.php
@app/Actions/RecipeProfitAction.php
@resources/views/livewire/pages/crafting-detail.blade.php
@resources/views/livewire/pages/crafting.blade.php
@resources/views/livewire/pages/dashboard.blade.php
@resources/views/livewire/pages/item-detail.blade.php
@app/Models/Shuffle.php
@app/Concerns/FormatsAuctionData.php
@tests/Feature/CraftingDetailTest.php
@tests/Feature/RecipeProfitActionTest.php

<interfaces>
From app/Models/CatalogItem.php:
```php
class CatalogItem extends Model {
    public function priceSnapshots(): HasMany
    {
        return $this->hasMany(PriceSnapshot::class);
    }
}
```

From app/Actions/RecipeProfitAction.php:
```php
// Uses: $reagent->catalogItem?->priceSnapshots->first()?->median_price
// Uses: $recipe->craftedItemSilver?->priceSnapshots->first()?->median_price
```

The `limit(1)` eager loading pattern appears in these files:
- resources/views/livewire/pages/crafting-detail.blade.php (lines 24-26)
- resources/views/livewire/pages/crafting.blade.php (lines 22-24) -- same file, overview page
- resources/views/livewire/pages/dashboard.blade.php (line 30) -- limit(2)
- resources/views/livewire/pages/item-detail.blade.php (line 24) -- limit(2)
- app/Models/Shuffle.php (lines 34-36) -- limit(1)
- tests/Feature/RecipeProfitActionTest.php -- many test cases use limit(1)
</interfaces>
</context>

<tasks>

<task type="auto">
  <name>Task 1: Add latestPriceSnapshot HasOne relationship to CatalogItem and update all eager loading</name>
  <files>
    app/Models/CatalogItem.php,
    resources/views/livewire/pages/crafting-detail.blade.php,
    resources/views/livewire/pages/crafting.blade.php,
    app/Models/Shuffle.php,
    resources/views/livewire/pages/dashboard.blade.php,
    resources/views/livewire/pages/item-detail.blade.php,
    app/Actions/RecipeProfitAction.php,
    app/Concerns/FormatsAuctionData.php
  </files>
  <action>
    1. In `app/Models/CatalogItem.php`, add a `latestPriceSnapshot` HasOne relationship:
       ```php
       use Illuminate\Database\Eloquent\Relations\HasOne;

       public function latestPriceSnapshot(): HasOne
       {
           return $this->hasOne(PriceSnapshot::class)->latestOfMany('polled_at');
       }
       ```
       Keep the existing `priceSnapshots()` HasMany relationship (used elsewhere for historical data).

    2. In `resources/views/livewire/pages/crafting-detail.blade.php`, replace the eager loading:
       Change:
       ```php
       'recipes.reagents.catalogItem.priceSnapshots' => fn ($q) => $q->latest('polled_at')->limit(1),
       'recipes.craftedItemSilver.priceSnapshots' => fn ($q) => $q->latest('polled_at')->limit(1),
       'recipes.craftedItemGold.priceSnapshots' => fn ($q) => $q->latest('polled_at')->limit(1),
       ```
       To:
       ```php
       'recipes.reagents.catalogItem.latestPriceSnapshot',
       'recipes.craftedItemSilver.latestPriceSnapshot',
       'recipes.craftedItemGold.latestPriceSnapshot',
       ```

       Then update ALL references from `->priceSnapshots->first()` to `->latestPriceSnapshot`:
       - Line 37-38: `$reagent->catalogItem?->priceSnapshots->first()?->polled_at` -> `$reagent->catalogItem?->latestPriceSnapshot?->polled_at`
       - Line 44: `$recipe->$rel?->priceSnapshots->first()?->polled_at` -> `$recipe->$rel?->latestPriceSnapshot?->polled_at`
       - Lines 54-57: reagent breakdown `$r->catalogItem?->priceSnapshots->first()?->median_price` -> `$r->catalogItem?->latestPriceSnapshot?->median_price`
       - Line 55-57: subtotal calculation uses same pattern

    3. In `resources/views/livewire/pages/crafting.blade.php` (the overview page -- same pattern), apply identical changes to the eager loading and any `priceSnapshots->first()` references.

    4. In `app/Models/Shuffle.php`, replace:
       ```php
       'inputCatalogItem.priceSnapshots' => fn ($q) => $q->latest('polled_at')->limit(1),
       'outputCatalogItem.priceSnapshots' => fn ($q) => $q->latest('polled_at')->limit(1),
       'byproducts.catalogItem.priceSnapshots' => fn ($q) => $q->latest('polled_at')->limit(1),
       ```
       With:
       ```php
       'inputCatalogItem.latestPriceSnapshot',
       'outputCatalogItem.latestPriceSnapshot',
       'byproducts.catalogItem.latestPriceSnapshot',
       ```
       And update any `->priceSnapshots->first()` references in the same file to `->latestPriceSnapshot`.

    5. In `app/Actions/RecipeProfitAction.php`, update the PHPDoc comment to reference `latestPriceSnapshot` instead of `priceSnapshots (latest 1)`, and update all `->priceSnapshots->first()` to `->latestPriceSnapshot`:
       - Line 38: `$reagent->catalogItem?->priceSnapshots->first()` -> `$reagent->catalogItem?->latestPriceSnapshot`
       - Line 52: `$recipe->craftedItemSilver?->priceSnapshots->first()?->median_price` -> `$recipe->craftedItemSilver?->latestPriceSnapshot?->median_price`
       - Line 53: same for craftedItemGold

    6. For `resources/views/livewire/pages/dashboard.blade.php` (line 30), this uses `limit(2)` for trend comparison. Keep the `priceSnapshots` eager load with `limit(2)` here BUT also add `latestPriceSnapshot` for the current price:
       Change:
       ```php
       'catalogItem' => fn ($q) => $q->with(['priceSnapshots' => fn ($q2) => $q2->latest('polled_at')->limit(2)]),
       ```
       To:
       ```php
       'catalogItem' => fn ($q) => $q->with(['latestPriceSnapshot', 'priceSnapshots' => fn ($q2) => $q2->latest('polled_at')->limit(2)]),
       ```
       Note: The dashboard `limit(2)` has the same global-limit issue but since it's for trend direction (latest 2 snapshots), it needs a different fix. For now, add `latestPriceSnapshot` alongside. The `FormatsAuctionData` trait already handles the fallback (`$item->catalogItem?->priceSnapshots ?? $item->priceSnapshots`) so the 2-snapshot trend data still works for items that DO get their snapshots loaded.

    7. For `resources/views/livewire/pages/item-detail.blade.php` (line 24), same approach -- add `latestPriceSnapshot` alongside the existing `priceSnapshots` load (the `limit(2)` is used for trend on that page).

    8. In `app/Concerns/FormatsAuctionData.php`, update `rollingSignal` line 56 to also consider `latestPriceSnapshot`:
       Change: `$snapshots = $item->catalogItem?->priceSnapshots ?? $item->priceSnapshots;`
       The current price should prefer: `$currentPrice = $item->catalogItem?->latestPriceSnapshot?->median_price ?? $snapshots->first()?->median_price ?? 0;`
       Similarly update `trendDirection` and `trendPercent` to use `latestPriceSnapshot` for current price.
  </action>
  <verify>
    <automated>cd /Users/lancethompson/Github/wow-ah-tracker && php artisan test --filter=CraftingDetail 2>&1 | tail -5</automated>
  </verify>
  <done>All eager loading uses latestPriceSnapshot HasOne relationship. Non-commodity and commodity items both load their most recent price snapshot correctly via per-parent subquery instead of global LIMIT.</done>
</task>

<task type="auto">
  <name>Task 2: Update tests to use latestPriceSnapshot eager loading pattern</name>
  <files>
    tests/Feature/RecipeProfitActionTest.php,
    tests/Feature/CraftingDetailTest.php
  </files>
  <action>
    1. In `tests/Feature/RecipeProfitActionTest.php`, replace ALL occurrences of:
       ```php
       'reagents.catalogItem.priceSnapshots' => fn ($q) => $q->latest('polled_at')->limit(1),
       'craftedItemSilver.priceSnapshots'    => fn ($q) => $q->latest('polled_at')->limit(1),
       'craftedItemGold.priceSnapshots'      => fn ($q) => $q->latest('polled_at')->limit(1),
       ```
       With:
       ```php
       'reagents.catalogItem.latestPriceSnapshot',
       'craftedItemSilver.latestPriceSnapshot',
       'craftedItemGold.latestPriceSnapshot',
       ```
       There are ~10 test cases that each have this pattern. Update them all.

    2. In `tests/Feature/CraftingDetailTest.php`, add a new test case that verifies prices load correctly when multiple recipes with different CatalogItems exist (regression test for the global limit bug):

       ```php
       it('loads prices for all recipes including non-commodity items', function () {
           $user = User::factory()->create();
           $profession = Profession::factory()->create(['name' => 'Blacksmithing']);

           // Commodity recipe with prices
           createDetailRecipeWithProfit($profession, 'Bronze Bar', 5000, 1000);

           // Non-commodity recipe (gear) with prices
           $gearItem = CatalogItem::factory()->create(['name' => 'Iron Helm']);
           PriceSnapshot::factory()->create([
               'catalog_item_id' => $gearItem->id,
               'median_price' => 50000,
               'polled_at' => now(),
           ]);

           $reagentItem = CatalogItem::factory()->create(['name' => 'Iron Ingot']);
           PriceSnapshot::factory()->create([
               'catalog_item_id' => $reagentItem->id,
               'median_price' => 2000,
               'polled_at' => now(),
           ]);

           $gearRecipe = Recipe::factory()->create([
               'profession_id' => $profession->id,
               'name' => 'Craft Iron Helm',
               'crafted_item_id_silver' => $gearItem->id,
               'is_commodity' => false,
           ]);

           RecipeReagent::factory()->create([
               'recipe_id' => $gearRecipe->id,
               'catalog_item_id' => $reagentItem->id,
               'quantity' => 5,
           ]);

           $response = $this->actingAs($user)->get('/crafting/'.$profession->slug);
           $response->assertOk();

           // Both recipes should have reagent cost data (not null)
           // The non-commodity recipe has reagent cost 5 * 2000 = 10000
           $response->assertSee('\u0022reagent_cost\u0022:10000', false);
           // The commodity recipe has reagent cost 1000
           $response->assertSee('\u0022reagent_cost\u0022:1000', false);
       });
       ```

    3. Run the full test suite to confirm no regressions.
  </action>
  <verify>
    <automated>cd /Users/lancethompson/Github/wow-ah-tracker && php artisan test --filter="CraftingDetail|RecipeProfit" 2>&1 | tail -10</automated>
  </verify>
  <done>All existing tests pass with updated eager loading. New regression test confirms both commodity and non-commodity items load prices correctly when multiple recipes exist.</done>
</task>

</tasks>

<verification>
- `php artisan test` -- full test suite passes
- `php artisan view:cache` -- all Blade views compile without errors
- Non-commodity recipes on crafting detail page show reagent costs and sell prices when price snapshot data exists
</verification>

<success_criteria>
- CatalogItem has `latestPriceSnapshot` HasOne relationship using `latestOfMany('polled_at')`
- All eager loading sites converted from `priceSnapshots` with `limit(1)` to `latestPriceSnapshot`
- RecipeProfitAction uses `->latestPriceSnapshot` instead of `->priceSnapshots->first()`
- All tests pass including new regression test for multi-item price loading
</success_criteria>

<output>
After completion, create `.planning/quick/31-fix-item-prices-not-loading-for-non-comm/31-SUMMARY.md`
</output>
