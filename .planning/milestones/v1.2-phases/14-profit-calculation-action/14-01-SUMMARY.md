---
phase: 14-profit-calculation-action
plan: "01"
subsystem: profit-calculation
tags: [action, tdd, profit, price-snapshot, recipe]
dependency_graph:
  requires:
    - 13-01: Recipe model with crafted_item_id_silver/gold nullable FKs
    - 13-02: RecipeReagent model with quantity and catalogItem relationship
    - PriceSnapshot: catalog_item_id FK with median_price BIGINT
  provides:
    - RecipeProfitAction: invokable action computing per-recipe profit from live AH prices
  affects:
    - Phase 15: Recipe profit display (will call RecipeProfitAction)
    - Phase 16: Recipe table UI (will call RecipeProfitAction per row)
tech_stack:
  added: []
  patterns:
    - TDD RED-GREEN-REFACTOR with PestPHP
    - Invokable Action class pattern (matches PriceAggregateAction convention)
    - Eager loading with constrained closures (latest polled_at limit 1)
    - (int) round(price * 0.95) AH cut formula (matches Shuffle::profitPerUnit)
key_files:
  created:
    - app/Actions/RecipeProfitAction.php
    - tests/Feature/RecipeProfitActionTest.php
  modified: []
decisions:
  - Return null for reagent_cost when ANY reagent has no price (prevents silent partial sum understatement)
  - Use (int) round() consistently — not floor() — matching Shuffle::profitPerUnit
  - median_profit = (T1 + T2) / 2 when both present (not statistical median over snapshots)
  - Stateless action — no DB writes; profit computed live from latest PriceSnapshot
metrics:
  duration: 2 minutes
  completed_date: 2026-03-05
  tasks_completed: 1
  files_created: 2
  files_modified: 0
  tests_added: 11
  assertions_added: 28
---

# Phase 14 Plan 01: RecipeProfitAction Summary

**One-liner:** Invokable RecipeProfitAction computes per-recipe profit from live PriceSnapshot.median_price using the (int)round(sell*0.95)-reagent_cost AH cut formula with null propagation for missing prices.

## Tasks Completed

| Task | Name | Commit | Files |
|------|------|--------|-------|
| RED | TDD failing tests for RecipeProfitAction | 0b79c6f | tests/Feature/RecipeProfitActionTest.php |
| GREEN | RecipeProfitAction implementation | e31f57f | app/Actions/RecipeProfitAction.php |

## Implementation Details

### RecipeProfitAction (`app/Actions/RecipeProfitAction.php`)

Invokable action class following the established `PriceAggregateAction` pattern:

```php
declare(strict_types=1);
namespace App\Actions;
use App\Models\Recipe;

class RecipeProfitAction
{
    public function __invoke(Recipe $recipe): array
    // Returns: reagent_cost, sell_price_silver, sell_price_gold,
    //          profit_silver, profit_gold, median_profit, has_missing_prices
}
```

**PROFIT-01 (Reagent Cost):** Iterates `$recipe->reagents`, sums `quantity * snapshot->median_price`. Returns `null` (not partial sum) if any reagent has no price snapshot — prevents silent cost understatement.

**PROFIT-02 (Sell Prices):** Reads `$recipe->craftedItemSilver?->priceSnapshots->first()?->median_price` for each tier. Both can be `null` when the recipe's crafted item FKs are null (common — Blizzard API omits this field frequently).

**PROFIT-03 (Profit Formula):** `(int) round($sellPrice * 0.95) - $reagentCost` — exactly matching `Shuffle::profitPerUnit()`. Sell=10000, reagent=5000 → profit=4500 confirmed.

**PROFIT-04 (Median Profit):** `match(count($profits))` — two tiers: `(int) round(sum/2)`; one tier: that tier's profit; neither: `null`.

**has_missing_prices:** Set to `true` when any reagent OR any crafted item (where the FK exists) has no price snapshot. Lets Phase 16 UI show a "missing data" indicator.

### Test Coverage (`tests/Feature/RecipeProfitActionTest.php`)

11 tests, 28 assertions, all passing:

- PROFIT-01: reagent cost calculation with 2 reagents (qty 3×1000 + qty 2×2000 = 7000)
- PROFIT-01 NULL: null reagent_cost + has_missing_prices=true when snapshot absent
- PROFIT-02: silver=10000, gold=15000 from latest snapshots
- PROFIT-02 NULL: both null when crafted_item_id_silver/gold are null
- PROFIT-03: sell=10000, reagent=5000 → profit=4500 (exact spec assertion)
- PROFIT-03: negative profit (-4050) returned as-is
- PROFIT-04: both tiers → median=(4500+14000)/2=9250
- PROFIT-04: only silver → median=profit_silver
- PROFIT-04: neither → median=null
- has_missing_prices=true when crafted item exists but lacks snapshot
- Return array completeness (all 7 keys present)

## Verification

```
php artisan test --filter RecipeProfitActionTest
# Tests: 11 passed (28 assertions)

php artisan test
# Tests: 211 passed (546 assertions)
```

Full suite green — no regressions.

## Deviations from Plan

None - plan executed exactly as written. TDD RED-GREEN cycle completed cleanly. No REFACTOR phase was needed as the implementation directly derived from the research patterns was clean.

## Self-Check

Files verified:
- [x] app/Actions/RecipeProfitAction.php — created
- [x] tests/Feature/RecipeProfitActionTest.php — created
- [x] Commits 0b79c6f and e31f57f — confirmed
