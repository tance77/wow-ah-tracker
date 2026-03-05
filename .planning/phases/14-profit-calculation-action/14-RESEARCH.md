# Phase 14: Profit Calculation Action - Research

**Researched:** 2026-03-05
**Domain:** Laravel Action class pattern, Eloquent eager loading, integer arithmetic for in-game currency
**Confidence:** HIGH — all patterns verified from existing codebase source files

---

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| PROFIT-01 | Per-recipe reagent cost calculated from live AH prices (sum of reagent quantities x median price) | `PriceSnapshot.median_price` (BIGINT copper) via `CatalogItem.priceSnapshots()` — same pattern as `Shuffle::profitPerUnit()` |
| PROFIT-02 | Per-recipe crafted item sell price shown for Tier 1 (Silver) and Tier 2 (Gold) | `Recipe.crafted_item_id_silver` / `crafted_item_id_gold` FK to `catalog_items`; latest snapshot via `->latest('polled_at')->first()` |
| PROFIT-03 | Profit = `(sell_price * 0.95) - reagent_cost`; unit test asserts sell=10000, reagent=5000 → profit=4500 | Exact formula present in `Shuffle::profitPerUnit()`: `(int) round($grossOutput * 0.95)` |
| PROFIT-04 | Median profit across both tiers displayed per recipe | Average of T1 profit + T2 profit when both present; median of non-null tier profits otherwise |
</phase_requirements>

---

## Summary

Phase 14 builds a single Action class (`RecipeProfitAction`) that receives a `Recipe` model (with eager-loaded relationships) and returns a structured result containing reagent cost, per-tier sell prices, per-tier profits, and median profit. All values are computed at call time from the latest `PriceSnapshot.median_price` for each `CatalogItem` — nothing is persisted.

The entire calculation domain already exists in the codebase. `Shuffle::profitPerUnit()` on the `Shuffle` model demonstrates exactly the pattern: load latest snapshot via `->latest('polled_at')->limit(1)`, read `median_price`, apply `(int) round($grossOutput * 0.95)` for the 5% AH cut. Phase 14 re-applies those exact mechanics to the recipe/reagent data model introduced in Phase 13.

The key design decision (from STATE.md) is that **profit is calculated live at render time from `PriceSnapshot.median_price` — never persisted**. This means the Action has no DB writes, is purely functional, and is trivially unit-testable without an HTTP layer.

**Primary recommendation:** Build `RecipeProfitAction` as an `__invoke(Recipe $recipe): RecipeProfitResult` invokable class. Use a lightweight value object (readonly class or array-typed DTO) for the return type. Model the test on `ShuffleBatchCalculatorTest.php` — factory setup, price snapshot seeding, assert computed values.

---

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| Laravel Framework | 12.x | Eloquent, Action class pattern | Project foundation |
| PestPHP | 3.8 | Unit and feature tests | All existing tests use Pest — no deviation |
| PHP 8.4 | 8.4 | readonly classes, named arguments, strict_types | Declared in every project file |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| Laravel Eloquent eager loading | 12.x | Load `reagents.catalogItem.priceSnapshots`, `craftedItemSilver.priceSnapshots`, `craftedItemGold.priceSnapshots` in one query set | Required to avoid N+1 on recipe table pages |
| `declare(strict_types=1)` | PHP 8.4 | Compile-time type enforcement | Present in every .php file in project — required |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| `RecipeProfitAction` Action class | Computed attributes on `Recipe` model | Action class is testable in isolation, composable, and matches PriceAggregateAction pattern; model attributes create hidden query overhead |
| Readonly PHP class as DTO | plain array return | Readonly class is self-documenting and type-safe; array return is simpler but loses IDE assistance — either works, action class itself is more important |
| `(int) round($price * 0.95)` | `(int) floor($price * 0.95)` | round() matches `Shuffle::profitPerUnit()` — must stay consistent |

**Installation:** No new packages required.

---

## Architecture Patterns

### Recommended Project Structure
```
app/Actions/
├── PriceFetchAction.php          (existing)
├── PriceAggregateAction.php      (existing)
├── ExtractListingsAction.php     (existing)
└── RecipeProfitAction.php        (NEW — Phase 14)

tests/Feature/
└── RecipeProfitActionTest.php    (NEW — Phase 14)
```

No new migrations. No new models. Phase 14 is pure logic on top of Phase 13's schema.

### Pattern 1: Action Class Convention (from existing PriceAggregateAction)
**What:** Single-responsibility `__invoke()` class in `app/Actions/`. Accepts typed input, returns typed output. No side effects.
**When to use:** Any computation that is testable in isolation and reusable across contexts (Artisan commands, Livewire components, API responses).

```php
// Source: app/Actions/PriceAggregateAction.php (existing)
declare(strict_types=1);

namespace App\Actions;

class RecipeProfitAction
{
    /**
     * @return array{
     *   reagent_cost: int|null,
     *   sell_price_silver: int|null,
     *   sell_price_gold: int|null,
     *   profit_silver: int|null,
     *   profit_gold: int|null,
     *   median_profit: int|null,
     *   has_missing_prices: bool,
     * }
     */
    public function __invoke(Recipe $recipe): array
    {
        // ... see Pattern 2 below
    }
}
```

### Pattern 2: Latest Snapshot Lookup (from Shuffle::profitPerUnit)
**What:** Load latest `PriceSnapshot` for a `CatalogItem` via `->latest('polled_at')->first()`.
**When to use:** Any time you need the current market price for an item.

```php
// Source: app/Models/Shuffle.php::profitPerUnit() (existing — HIGH confidence)
// Load relationship: catalogItem.priceSnapshots (latest 1)
$latestSnapshot = $catalogItem->priceSnapshots()->latest('polled_at')->first();
$medianPrice = $latestSnapshot?->median_price; // null if no price data yet
```

**Critical:** The `PriceSnapshot` table uses `catalog_item_id` FK (not `watched_item_id` — that was migrated in Phase 9). The relationship is:
```
Recipe → crafted_item_id_silver → catalog_items.id → price_snapshots.catalog_item_id
Recipe → reagents → recipe_reagents.catalog_item_id → catalog_items.id → price_snapshots.catalog_item_id
```

### Pattern 3: Reagent Cost Calculation
**What:** Sum `quantity * median_price` across all reagents. Return NULL if any reagent has no price data.
**When to use:** PROFIT-01.

```php
// Eager load to avoid N+1:
// $recipe->load(['reagents.catalogItem.priceSnapshots' => fn($q) => $q->latest('polled_at')->limit(1)])

$reagentCost = 0;
$hasMissingPrices = false;

foreach ($recipe->reagents as $reagent) {
    $latestSnapshot = $reagent->catalogItem?->priceSnapshots->first();

    if ($latestSnapshot === null) {
        $hasMissingPrices = true;
        continue; // or return null — see Pitfall 2
    }

    $reagentCost += $reagent->quantity * $latestSnapshot->median_price;
}
```

**Note on NULL handling:** The success criteria says "NULL prices handled gracefully" and PROFIT-03's unit test uses concrete values. The action should return `reagent_cost: null` (not 0) when ANY reagent price is missing — this lets the UI distinguish "no price data" from "zero cost" and show a missing data indicator (TABLE-03, Phase 16).

### Pattern 4: AH Cut and Profit Formula
**What:** `profit = (int) round(sell_price * 0.95) - reagent_cost`
**When to use:** PROFIT-03.

```php
// Source: app/Models/Shuffle.php::profitPerUnit() — exact formula (HIGH confidence)
// sell_price=10000, reagent_cost=5000 → profit = round(10000 * 0.95) - 5000 = 9500 - 5000 = 4500
$netSellPrice = (int) round($sellPrice * 0.95);
$profit = $netSellPrice - $reagentCost;
```

The unit test in the success criteria (`sell_price=10000, reagent_cost=5000 → profit=4500`) confirms:
- `round(10000 * 0.95)` = `round(9500.0)` = `9500`
- `9500 - 5000` = `4500` ✓

### Pattern 5: Median Profit Across Tiers (PROFIT-04)
**What:** When both T1 and T2 profits are present, median = `(int) round(($profitSilver + $profitGold) / 2)`. When only one tier has data, median = that tier's profit. When neither, `null`.

```php
$profits = array_filter([$profitSilver, $profitGold], fn($p) => $p !== null);

$medianProfit = match(count($profits)) {
    2 => (int) round(array_sum($profits) / 2),
    1 => (int) reset($profits),
    default => null,
};
```

**Note:** The requirement says "median profit across both tiers." With exactly two values, median and average are identical — `(T1 + T2) / 2`. This is not a statistical median over many samples.

### Pattern 6: Eager Loading for Recipe Profit (avoids N+1)
**What:** When computing profit for many recipes (Phase 15/16 will do this), eager load all relationships up front.
**When to use:** Any time RecipeProfitAction is called in a loop over many recipes.

```php
// Load all data needed for profit calculation in 4 queries instead of N+1
$recipes = Recipe::with([
    'reagents.catalogItem.priceSnapshots' => fn ($q) => $q->latest('polled_at')->limit(1),
    'craftedItemSilver.priceSnapshots'    => fn ($q) => $q->latest('polled_at')->limit(1),
    'craftedItemGold.priceSnapshots'      => fn ($q) => $q->latest('polled_at')->limit(1),
])->get();
```

**IMPORTANT:** The `->limit(1)` inside a `with()` closure applies per-group (per catalog_item_id), not globally. This is the standard Laravel eager loading pattern and is confirmed by Shuffle::profitPerUnit() usage.

### Anti-Patterns to Avoid
- **Persisting profit values:** Never store reagent_cost or profit in the database. Prices change every 15 minutes; stale stored profits would silently mislead. The pre-decision in STATE.md is explicit: "Profit calculated live at render time."
- **Using `round()` as float then assigning to int:** Always cast: `(int) round(...)`. PHP's `round()` returns a float; prices in DB are BIGINT copper.
- **Treating 0 as "no price":** A price of 0 copper is technically valid (though rare). Use `null` to represent "no snapshot exists," not `0`. The `PriceSnapshot` record either exists (with a value) or doesn't — `latest()->first()` returns `null` when absent.
- **Mixing floor/ceil with round:** The project uses `(int) round()` for the AH cut. Using `floor()` would produce 9499 instead of 9500 for a 10000g item, breaking the PROFIT-03 unit test.
- **Querying snapshots one at a time in a loop:** Always eager-load before iterating. A recipe table page with 200 recipes would fire 600+ queries without eager loading.

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| 5% AH cut arithmetic | Custom rounding logic | `(int) round($price * 0.95)` | Already established in `Shuffle::profitPerUnit()` — consistency critical for unit test |
| Latest price lookup | Complex subquery | `->priceSnapshots()->latest('polled_at')->first()` | Standard Eloquent, proven in ShuffleBatchCalculator and Shuffle model |
| N+1 query avoidance | Manual JOIN queries | Eloquent eager loading with `with()` closures | Established pattern in item-detail.blade.php and Shuffle::profitPerUnit |
| Gold formatting for display | Custom formatter | `FormatsAuctionData::formatGold(int $copper)` | Trait available in all Livewire components — already handles g/s/c formatting |

**Key insight:** Phase 14 is pattern composition, not pattern invention. The profit formula, snapshot lookup, and eager loading patterns are all present verbatim in existing code.

---

## Common Pitfalls

### Pitfall 1: Using `watched_items` as the Join Table for Prices
**What goes wrong:** Developer looks at `WatchedItem` and sees `priceSnapshots()` relationship, assumes profit calculation should go through `WatchedItem`.
**Why it happens:** The `WatchedItem` → `PriceSnapshot` path exists (`hasManyThrough` via `CatalogItem`), but it's designed for the watchlist UI, not for recipe profit. Recipes reference `CatalogItem` directly via `RecipeReagent.catalog_item_id`.
**How to avoid:** Always resolve price via `CatalogItem.priceSnapshots()`. The path from recipe to price is: `RecipeReagent → catalog_item_id → CatalogItem → price_snapshots`.
**Warning signs:** SQL queries hitting `watched_items` when computing recipe profit.

### Pitfall 2: Partial Reagent Cost When Some Prices Are NULL
**What goes wrong:** Action returns a partial reagent cost when only some reagents have prices — cost looks artificially low, profit looks artificially high.
**Why it happens:** Loop continues summing when it should track `$hasMissingPrices` and signal incomplete data.
**How to avoid:** Track `$hasMissingPrices = true` when any reagent has no snapshot. Expose this flag in the return value so the UI can show the "missing price" indicator (TABLE-03). Whether to return `null` for `reagent_cost` or a partial cost is a design choice — returning `null` is cleaner and prevents silent miscalculation.
**Warning signs:** Recipe shows suspiciously low reagent cost; one ingredient is a new Midnight item not yet seeded.

### Pitfall 3: Eager Load Syntax for Constrained Nested Relationships
**What goes wrong:** `->with('reagents.catalogItem.priceSnapshots')` loads ALL snapshots (potentially thousands per item), not just the latest.
**Why it happens:** Nested dot-notation `with()` without a closure loads the full relationship.
**How to avoid:** Use closure syntax to constrain the innermost relationship:
```php
// WRONG — loads all snapshots:
Recipe::with('reagents.catalogItem.priceSnapshots')->get();

// RIGHT — loads only latest snapshot per item:
Recipe::with(['reagents.catalogItem.priceSnapshots' => fn ($q) => $q->latest('polled_at')->limit(1)])->get();
```
**Warning signs:** Memory spikes when loading a recipe list; each item has hundreds of snapshot rows in memory.

### Pitfall 4: crafted_item_id_silver / _gold May Both Be NULL
**What goes wrong:** Action throws null pointer exception or returns nonsensical profit when the recipe has no crafted item resolved (common case — see Phase 13 Pitfall 1).
**Why it happens:** The `crafted_item` field is absent from many Blizzard API recipe responses. `crafted_item_id_silver` and `crafted_item_id_gold` are both nullable columns.
**How to avoid:** Always null-check: `$sellPriceSilver = $recipe->craftedItemSilver?->priceSnapshots->first()?->median_price`. When both are null, `profit_silver = null`, `profit_gold = null`, `median_profit = null`. This is a valid state — the UI will show a "not tracked" indicator.
**Warning signs:** PHP notice about accessing property on null; profit shows as 0 instead of null for recipes with missing crafted item.

### Pitfall 5: Integer Overflow on Large Copper Values
**What goes wrong:** Reagent cost overflows PHP integer on high-value recipes with many reagents.
**Why it happens:** PHP on 64-bit systems has `PHP_INT_MAX = 9,223,372,036,854,775,807`. WoW prices are copper (1 gold = 10,000 copper). Even a 1,000,000g item is only 10,000,000,000 copper — well within 64-bit range. This is a non-issue on 64-bit PHP.
**How to avoid:** Confirm server is 64-bit (it is — macOS + standard PHP setup). No special handling needed.
**Warning signs:** None expected, but if prices start displaying as negative — check PHP build architecture.

### Pitfall 6: Confusion Between `median_profit` (scalar) and Frequency-Distribution Median
**What goes wrong:** Developer implements a statistical median across many snapshots instead of the simple average of T1/T2 profit.
**Why it happens:** `PriceAggregateAction` uses a sophisticated frequency-distribution median for commodity listings. The term "median" in PROFIT-04 means something different — the midpoint between two tier values.
**How to avoid:** PROFIT-04 "median profit" = `(profit_silver + profit_gold) / 2` when both present. Not a per-snapshot statistical median. The requirement language is about displaying a single representative number per recipe, not aggregating over time.

---

## Code Examples

Verified patterns from existing project source:

### Complete RecipeProfitAction (recommended implementation)
```php
// Source: Pattern derived from app/Models/Shuffle.php::profitPerUnit() + app/Actions/PriceAggregateAction.php
declare(strict_types=1);

namespace App\Actions;

use App\Models\Recipe;

class RecipeProfitAction
{
    /**
     * Compute profit for a single recipe from live AH prices.
     *
     * IMPORTANT: Recipe must be loaded with eager relationships:
     *   reagents.catalogItem.priceSnapshots (latest 1)
     *   craftedItemSilver.priceSnapshots (latest 1)
     *   craftedItemGold.priceSnapshots (latest 1)
     *
     * All prices in copper (BIGINT). Never persisted — computed at call time.
     *
     * @return array{
     *   reagent_cost: int|null,
     *   sell_price_silver: int|null,
     *   sell_price_gold: int|null,
     *   profit_silver: int|null,
     *   profit_gold: int|null,
     *   median_profit: int|null,
     *   has_missing_prices: bool,
     * }
     */
    public function __invoke(Recipe $recipe): array
    {
        // --- Reagent cost (PROFIT-01) ---
        $reagentCost = 0;
        $hasMissingPrices = false;

        foreach ($recipe->reagents as $reagent) {
            $snapshot = $reagent->catalogItem?->priceSnapshots->first();

            if ($snapshot === null) {
                $hasMissingPrices = true;
                continue;
            }

            $reagentCost += $reagent->quantity * $snapshot->median_price;
        }

        // If any reagent has no price, reagent_cost is incomplete — signal as null
        $reagentCostFinal = $hasMissingPrices ? null : $reagentCost;

        // --- Sell prices (PROFIT-02) ---
        $sellPriceSilver = $recipe->craftedItemSilver?->priceSnapshots->first()?->median_price;
        $sellPriceGold   = $recipe->craftedItemGold?->priceSnapshots->first()?->median_price;

        if ($sellPriceSilver === null && $recipe->craftedItemSilver !== null) {
            $hasMissingPrices = true;
        }
        if ($sellPriceGold === null && $recipe->craftedItemGold !== null) {
            $hasMissingPrices = true;
        }

        // --- Per-tier profit (PROFIT-03): (sell_price * 0.95) - reagent_cost ---
        $profitSilver = null;
        $profitGold   = null;

        if ($sellPriceSilver !== null && $reagentCostFinal !== null) {
            $profitSilver = (int) round($sellPriceSilver * 0.95) - $reagentCostFinal;
        }

        if ($sellPriceGold !== null && $reagentCostFinal !== null) {
            $profitGold = (int) round($sellPriceGold * 0.95) - $reagentCostFinal;
        }

        // --- Median profit across tiers (PROFIT-04) ---
        $profits = array_filter([$profitSilver, $profitGold], fn ($p) => $p !== null);
        $medianProfit = match (count($profits)) {
            2 => (int) round(array_sum($profits) / 2),
            1 => (int) reset($profits),
            default => null,
        };

        return [
            'reagent_cost'      => $reagentCostFinal,
            'sell_price_silver' => $sellPriceSilver,
            'sell_price_gold'   => $sellPriceGold,
            'profit_silver'     => $profitSilver,
            'profit_gold'       => $profitGold,
            'median_profit'     => $medianProfit,
            'has_missing_prices' => $hasMissingPrices,
        ];
    }
}
```

### Existing AH Cut Pattern (exact source, HIGH confidence)
```php
// Source: app/Models/Shuffle.php line 61 — profitPerUnit()
$netOutput = (int) round($grossOutput * 0.95); // 5% AH cut
return $netOutput - $firstInputPrice;
```

### Latest Snapshot Eager Load (exact source, HIGH confidence)
```php
// Source: app/Models/Shuffle.php lines 33-35 — profitPerUnit()
$steps = $this->steps()->with([
    'inputCatalogItem.priceSnapshots' => fn ($q) => $q->latest('polled_at')->limit(1),
    'outputCatalogItem.priceSnapshots' => fn ($q) => $q->latest('polled_at')->limit(1),
])->get();
```

### Gold Formatter (for future display, available via trait)
```php
// Source: app/Concerns/FormatsAuctionData.php — formatGold(int $copper): string
// Returns "125g 50s 0c" format
// Available in all Livewire components via: use FormatsAuctionData;
$this->formatGold($profitSilver); // e.g. "125g 50s"
```

### Test Setup Pattern (from ShuffleBatchCalculatorTest)
```php
// Source: tests/Feature/ShuffleBatchCalculatorTest.php — factory + snapshot seeding
$recipe = Recipe::factory()->create([
    'crafted_item_id_silver' => $silverItem->id,
    'crafted_item_id_gold'   => $goldItem->id,
]);

// Seed a price snapshot for each catalog item
PriceSnapshot::factory()->create([
    'catalog_item_id' => $reagentCatalogItem->id,
    'median_price'    => 5000,
    'polled_at'       => now(),
]);
```

---

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| PriceSnapshot keyed by `watched_item_id` | PriceSnapshot keyed by `catalog_item_id` | Phase 9 migration | Recipe profit can query prices directly via CatalogItem — no WatchedItem required |
| Profit stored in DB column | Profit computed live from latest snapshot | Pre-decision (STATE.md) | Action is stateless; prices auto-reflect latest 15-min poll without re-sync |
| Single quality tier per recipe | Dual nullable tier columns (`_silver`, `_gold`) | Phase 13 schema | Phase 14 must handle two tier prices independently, both potentially null |

**Deprecated/outdated:**
- Querying profit through `WatchedItem`: The swap migration (Phase 9) made `PriceSnapshot.catalog_item_id` the authoritative join key. `WatchedItem.priceSnapshots()` is a convenience relationship for the watchlist UI, not the canonical price source.

---

## Open Questions

1. **Partial reagent cost behavior**
   - What we know: Success criteria says "NULL prices handled gracefully." The return type should distinguish "no price data" from "zero cost."
   - What's unclear: Should `reagent_cost` be `null` when ANY reagent price is missing (strict), or should it return the partial sum plus set `has_missing_prices: true` (partial)?
   - Recommendation: Return `null` for `reagent_cost` when any reagent price is absent. Partial sums silently understate costs and would produce misleadingly positive profit numbers. The UI will show a "missing price data" indicator (TABLE-03, Phase 16) — `null` is the correct signal for that.

2. **crafted_quantity multiplier for alchemy/cooking**
   - What we know: `Recipe.crafted_quantity` defaults to 1; alchemy may yield 5 potions per craft. ADVN-01 (yield quantity handling) is deferred.
   - What's unclear: Does Phase 14 need to divide reagent cost by `crafted_quantity` to get per-unit cost? If a recipe yields 5 potions using 10 herbs, reagent cost per-unit = (herb cost) * 10 / 5.
   - Recommendation: For Phase 14, treat `crafted_quantity` as-is — calculate total batch cost vs total batch sell value. This matches the success criteria (which computes profit per-craft, not per-unit). Document this as a known simplification. ADVN-01 can add per-unit mode later.

3. **Negative profit values**
   - What we know: Profit can be negative (reagents cost more than the crafted item sells for). This is valid.
   - What's unclear: Should the return type flag negative profit differently?
   - Recommendation: Return the raw signed integer. Negative profit is meaningful information ("this recipe loses money"). The UI will render it in red (Phase 15/16).

---

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | PestPHP 3.8 with pest-plugin-laravel |
| Config file | `phpunit.xml` |
| Quick run command | `php artisan test --filter RecipeProfitAction` |
| Full suite command | `php artisan test` |

### Phase Requirements → Test Map
| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| PROFIT-01 | Reagent cost = sum of (quantity × median_price) across all reagents | Unit/Feature | `php artisan test --filter RecipeProfitActionTest` | Wave 0 |
| PROFIT-01 | Reagent cost is `null` when any reagent has no price snapshot | Unit/Feature | `php artisan test --filter RecipeProfitActionTest` | Wave 0 |
| PROFIT-02 | `sell_price_silver` and `sell_price_gold` reflect latest snapshot for each crafted item | Unit/Feature | `php artisan test --filter RecipeProfitActionTest` | Wave 0 |
| PROFIT-02 | Both sell prices are `null` when recipe has no crafted item IDs | Unit/Feature | `php artisan test --filter RecipeProfitActionTest` | Wave 0 |
| PROFIT-03 | `profit = (int) round(sell_price * 0.95) - reagent_cost`; sell=10000, reagent=5000 → profit=4500 | Unit | `php artisan test --filter RecipeProfitActionTest` | Wave 0 |
| PROFIT-04 | `median_profit = (profit_silver + profit_gold) / 2` when both present | Unit | `php artisan test --filter RecipeProfitActionTest` | Wave 0 |
| PROFIT-04 | `median_profit = profit_silver` when only T1 present | Unit | `php artisan test --filter RecipeProfitActionTest` | Wave 0 |

### Sampling Rate
- **Per task commit:** `php artisan test --filter RecipeProfitActionTest`
- **Per wave merge:** `php artisan test`
- **Phase gate:** Full suite green before `/gsd:verify-work`

### Wave 0 Gaps
- [ ] `tests/Feature/RecipeProfitActionTest.php` — covers all PROFIT-* requirements

*(No framework gaps — existing PestPHP + RefreshDatabase infrastructure covers all needs. RecipeFactory, PriceSnapshotFactory, CatalogItemFactory all exist.)*

---

## Sources

### Primary (HIGH confidence)
- `app/Models/Shuffle.php::profitPerUnit()` — exact AH cut formula `(int) round($grossOutput * 0.95)`, latest snapshot eager load pattern
- `app/Actions/PriceAggregateAction.php` — Action class convention, invokable signature, `__invoke()` pattern
- `app/Models/Recipe.php` — confirmed nullable `crafted_item_id_silver`/`_gold`, `reagents()` HasMany, `craftedItemSilver()`/`craftedItemGold()` BelongsTo
- `app/Models/RecipeReagent.php` — confirmed `quantity` integer column, `catalogItem()` BelongsTo
- `app/Models/PriceSnapshot.php` — confirmed `median_price` integer (copper), `catalog_item_id` FK
- `app/Models/CatalogItem.php` — confirmed `priceSnapshots()` HasMany
- `database/migrations/2026_03_02_000000_swap_watched_item_id_*` — confirmed `price_snapshots.catalog_item_id` is the current join key
- `database/factories/RecipeFactory.php` + `PriceSnapshotFactory.php` — confirmed factory availability for tests
- `.planning/STATE.md` Decisions section — "Profit calculated live at render time from PriceSnapshot.median_price — never persisted"

### Secondary (MEDIUM confidence)
- `tests/Feature/ShuffleBatchCalculatorTest.php` — pattern for factory setup + price snapshot seeding in tests
- `app/Concerns/FormatsAuctionData.php` — `formatGold()` helper available for display (Phase 15/16, not Phase 14)

### Tertiary (LOW confidence)
- None — all critical claims verified from source files.

---

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — all libraries already in project, no new dependencies
- Architecture: HIGH — Action class convention, eager load pattern, formula all sourced from existing code
- Pitfalls: HIGH — directly derived from reading actual migrations, models, and existing tests
- Formula correctness: HIGH — PROFIT-03 unit test assertion (`sell=10000, reagent=5000 → 4500`) cross-checks the formula against `(int) round(10000 * 0.95) - 5000 = 9500 - 5000 = 4500`

**Research date:** 2026-03-05
**Valid until:** 2026-04-05 (stable patterns; no external API involvement)
