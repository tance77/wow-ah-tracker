---
phase: 16-per-profession-recipe-table
plan: 01
subsystem: ui
tags: [livewire, volt, alpine, recipe-profit, computed-property, eager-loading]

# Dependency graph
requires:
  - phase: 14-profit-calculation-action
    provides: RecipeProfitAction for per-recipe profit computation
  - phase: 15-profession-overview-page-and-navigation
    provides: crafting-detail.blade.php shell with Profession model binding and route
provides:
  - "#[Computed] recipeData property with full recipe dataset (profit, reagents, staleness)"
  - "Feature tests covering TABLE-01 through TABLE-06 requirements"
  - "Data pipeline ready for Alpine.js table UI in Plan 02"
affects: [16-per-profession-recipe-table plan 02]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Server-side #[Computed] property builds full dataset passed to Alpine via @js()"
    - "Staleness computed from oldest polled_at across all eagerly-loaded price snapshots"

key-files:
  created:
    - tests/Feature/CraftingDetailTest.php
  modified:
    - resources/views/livewire/pages/crafting-detail.blade.php

key-decisions:
  - "Duplicated createRecipeWithProfit helper in CraftingDetailTest rather than extracting to shared helper (simpler, self-contained tests)"
  - "Staleness threshold set at 60 minutes matching user decision from CONTEXT.md"
  - "Reagent display_name uses CatalogItem accessor (name + quality tier suffix)"

patterns-established:
  - "@js() encodes JSON with \\u0022 escaped quotes -- tests must match this format"
  - "Reagent breakdown included in initial JSON payload (no lazy loading)"

requirements-completed: [TABLE-01, TABLE-02, TABLE-03, TABLE-04, TABLE-05, TABLE-06]

# Metrics
duration: 3min
completed: 2026-03-05
---

# Phase 16 Plan 01: Per-Profession Recipe Table Data Pipeline Summary

**Server-side #[Computed] recipeData property computing profit, reagent breakdowns, and staleness for all profession recipes via RecipeProfitAction**

## Performance

- **Duration:** 3 min
- **Started:** 2026-03-05T23:51:15Z
- **Completed:** 2026-03-05T23:54:33Z
- **Tasks:** 2
- **Files modified:** 2

## Accomplishments
- 7 feature tests covering all TABLE requirements (TABLE-01 through TABLE-06) plus auth guard
- #[Computed] recipeData property eager-loads recipes with price snapshots and computes profit via RecipeProfitAction
- Reagent breakdown data (name, quantity, unit_price, subtotal) built per recipe from eager-loaded relationships
- Staleness flag computed from oldest polled_at across all reagent and crafted item price snapshots (>60 min threshold)
- Data embedded in Blade template via @js() ready for Alpine.js consumption in Plan 02

## Task Commits

Each task was committed atomically:

1. **Task 1: Create CraftingDetailTest feature tests** - `eb6da77` (test)
2. **Task 2: Implement #[Computed] recipeData property in Volt SFC** - `7696632` (feat)

## Files Created/Modified
- `tests/Feature/CraftingDetailTest.php` - 7 Pest feature tests covering recipe data contract
- `resources/views/livewire/pages/crafting-detail.blade.php` - Volt SFC with recipeData computed property and minimal Alpine template

## Decisions Made
- Duplicated `createRecipeWithProfit` helper in test file (renamed to `createDetailRecipeWithProfit`) rather than extracting to a shared helper -- keeps tests self-contained
- Tests assert against `\u0022`-escaped JSON keys since Laravel's `@js()` directive uses `JSON.parse()` with unicode-escaped quotes
- Missing-price test uses a recipe with a crafted item FK but no PriceSnapshot, triggering `has_missing_prices: true` via RecipeProfitAction's sell-price null check

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Fixed test assertions for @js() JSON encoding**
- **Found during:** Task 1 (CraftingDetailTest creation)
- **Issue:** Plan suggested `assertSee('"stale":true')` but @js() encodes quotes as `\u0022`, so double-quote patterns don't match HTML output
- **Fix:** Changed assertions to match `\u0022`-escaped format (e.g., `\u0022stale\u0022:true`)
- **Files modified:** tests/Feature/CraftingDetailTest.php
- **Verification:** All 7 tests pass
- **Committed in:** eb6da77 (Task 1 commit)

**2. [Rule 1 - Bug] Fixed missing-price test setup**
- **Found during:** Task 1 (CraftingDetailTest creation)
- **Issue:** Plan suggested recipe with `crafted_item_id_silver => null` would trigger `has_missing_prices: true`, but RecipeProfitAction only sets that flag when FK is non-null but price snapshot is missing
- **Fix:** Created recipe with valid crafted_item_id_silver FK pointing to CatalogItem with no PriceSnapshot
- **Files modified:** tests/Feature/CraftingDetailTest.php
- **Verification:** Test correctly asserts `has_missing_prices: true`
- **Committed in:** eb6da77 (Task 1 commit)

**3. [Rule 1 - Bug] Fixed reagent name factory field**
- **Found during:** Task 1 (CraftingDetailTest creation)
- **Issue:** Plan used `display_name` as factory attribute, but `display_name` is a computed accessor on CatalogItem (not a DB column)
- **Fix:** Used `name` column instead -- `display_name` accessor appends quality tier suffix automatically
- **Files modified:** tests/Feature/CraftingDetailTest.php
- **Verification:** Reagent breakdown test passes with correct names
- **Committed in:** eb6da77 (Task 1 commit)

---

**Total deviations:** 3 auto-fixed (3 bugs in plan's test specifications)
**Impact on plan:** All auto-fixes corrected incorrect test assertions from the plan. No scope change.

## Issues Encountered
- Pre-existing failure in `BlizzardTokenServiceTest` (unrelated to this plan's changes) -- 1 test fails in full suite, all other 235 tests pass

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- recipeData computed property provides complete dataset for Alpine.js table rendering
- Plan 02 can build the full sortable/filterable/expandable table UI consuming this data
- All TABLE requirements validated by feature tests

---
*Phase: 16-per-profession-recipe-table*
*Completed: 2026-03-05*
