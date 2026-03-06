---
phase: quick-31
plan: 01
subsystem: pricing
tags: [eager-loading, hasone, non-commodity, bug-fix]
dependency_graph:
  requires: []
  provides: [latestPriceSnapshot-relationship]
  affects: [crafting-detail, crafting-overview, dashboard, item-detail, shuffle, recipe-profit]
tech_stack:
  added: []
  patterns: [latestOfMany-HasOne-for-per-parent-latest-record]
key_files:
  created: []
  modified:
    - app/Models/CatalogItem.php
    - app/Actions/RecipeProfitAction.php
    - app/Models/Shuffle.php
    - app/Concerns/FormatsAuctionData.php
    - resources/views/livewire/pages/crafting-detail.blade.php
    - resources/views/livewire/pages/crafting.blade.php
    - resources/views/livewire/pages/dashboard.blade.php
    - resources/views/livewire/pages/item-detail.blade.php
    - tests/Feature/RecipeProfitActionTest.php
    - tests/Feature/CraftingDetailTest.php
decisions:
  - "Use latestOfMany('polled_at') HasOne instead of eager load with limit(1) -- generates correct per-parent subquery"
  - "Keep priceSnapshots HasMany with limit(2) on dashboard/item-detail for trend comparison alongside new latestPriceSnapshot"
metrics:
  duration: 3 min
  completed: 2026-03-06
---

# Quick Task 31: Fix Item Prices Not Loading for Non-Commodity Items

Added `latestPriceSnapshot` HasOne relationship using `latestOfMany('polled_at')` to CatalogItem, replacing all `priceSnapshots` eager loads with `limit(1)` that applied LIMIT globally in SQL instead of per-parent row.

## What Changed

### Task 1: Add latestPriceSnapshot HasOne and update all eager loading
**Commit:** c8735ee

The root cause was that `->with(['priceSnapshots' => fn($q) => $q->latest('polled_at')->limit(1)])` generates a single SQL query with `LIMIT 1` applied globally, not per CatalogItem. When loading many items, only one CatalogItem gets its snapshot -- all others have empty collections. Non-commodity items (BoE gear from realm AH) were disproportionately affected because they have fewer snapshots.

**Fix:** Added a `latestPriceSnapshot()` HasOne relationship using Laravel's `latestOfMany('polled_at')`, which generates a correlated subquery that correctly selects one row per parent.

Files updated:
- `app/Models/CatalogItem.php` -- new `latestPriceSnapshot` HasOne relationship
- `app/Actions/RecipeProfitAction.php` -- `priceSnapshots->first()` to `latestPriceSnapshot`
- `app/Models/Shuffle.php` -- eager loading and all price lookups
- `resources/views/livewire/pages/crafting-detail.blade.php` -- eager loading + all references
- `resources/views/livewire/pages/crafting.blade.php` -- eager loading
- `resources/views/livewire/pages/dashboard.blade.php` -- added `latestPriceSnapshot` alongside `priceSnapshots` limit(2) for trends
- `resources/views/livewire/pages/item-detail.blade.php` -- same approach as dashboard
- `app/Concerns/FormatsAuctionData.php` -- prefer `latestPriceSnapshot` for current price in rollingSignal

### Task 2: Update tests for latestPriceSnapshot
**Commit:** 1249d72

- Updated all 10 test cases in `RecipeProfitActionTest.php` to use new eager loading pattern
- Added regression test in `CraftingDetailTest.php` verifying both commodity and non-commodity items load reagent costs correctly when multiple recipes exist

## Deviations from Plan

None -- plan executed exactly as written.

## Verification

- `php artisan test --filter="CraftingDetail|RecipeProfit"` -- 19 tests, 56 assertions, all passing
- `php artisan test` -- 236 passed (1 pre-existing failure in BlizzardTokenServiceTest unrelated to this change)
- `php artisan view:cache` -- all Blade templates cached successfully

## Self-Check: PASSED

- [x] app/Models/CatalogItem.php contains latestPriceSnapshot HasOne with latestOfMany
- [x] All eager loading sites converted
- [x] All tests pass
- [x] Both commits exist in git log
