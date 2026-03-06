---
phase: 13-recipe-data-model-and-seed-command
plan: "02"
subsystem: commands
tags: [artisan, blizzard-api, http-pool, tdd, pest, seeding, auto-watch]

requires:
  - professions table (from 13-01)
  - recipes table (from 13-01)
  - recipe_reagents table (from 13-01)
  - CatalogItem model
  - WatchedItem model
  - BlizzardTokenService
provides:
  - artisan blizzard:sync-recipes command
  - Three-level Blizzard API traversal (profession index -> skill tier -> recipe detail)
  - --dry-run flag for zero-write API traversal
  - --report-gaps flag for per-profession coverage table
  - Auto-watch for user #1 with profession tagging
  - Idempotent upsert for professions, recipes, and reagents
affects:
  - phase-14 (profit calculation queries recipes and reagents populated by this command)
  - phase-15 (UI profession pages render recipe data seeded here)
  - phase-16 (sorting queries recipes table seeded here)

tech-stack:
  added: []
  patterns:
    - TDD (RED tests first, then GREEN implementation)
    - Http::pool() with 20-item batches and 1s pause between batches
    - Http::fake() patterns must be ordered most-specific first to avoid wildcard conflicts
    - declare(strict_types=1) on all command files
    - updateOrCreate upsert pattern for idempotent syncing
    - delete + re-insert reagents for idempotent re-runs
    - firstOrCreate for auto-watch deduplication

key-files:
  created:
    - app/Console/Commands/SyncRecipesCommand.php
    - tests/Feature/BlizzardApi/SyncRecipesCommandTest.php
  modified: []

key-decisions:
  - "Http::fake() pattern ordering is critical — more-specific wildcard patterns (skill-tier, media) must precede profession/ID generic patterns or the wrong stub is returned"
  - "reagents delete + re-insert (not updateOrCreate) ensures idempotent re-run without duplicate reagent rows"
  - "Unknown reagents (not in catalog_items) auto-create minimal CatalogItem entries so FK constraints are satisfied — logged as warning"
  - "Http::pool() batch size of 20 with usleep(1_000_000) between batches mirrors SyncCatalogCommand to stay under Blizzard 100 req/s limit"
  - "Auto-watch uses firstOrCreate so re-running never creates duplicate WatchedItem rows"

duration: 6min
completed: "2026-03-05"
---

# Phase 13 Plan 02: SyncRecipesCommand Summary

**`artisan blizzard:sync-recipes` command with three-level Blizzard API traversal, Http::pool() batching, --dry-run/--report-gaps flags, auto-watch for user #1, and 10 Pest feature tests covering IMPORT-01 through IMPORT-06**

## Performance

- **Duration:** ~6 min
- **Started:** 2026-03-05T19:27:17Z
- **Completed:** 2026-03-05
- **Tasks:** 1
- **Files modified:** 2

## Accomplishments

- `SyncRecipesCommand` implements full three-level traversal: profession index → highest-ID skill tier → recipe detail batches
- `Http::pool()` sends 20 recipe detail requests concurrently with a 1-second pause between batches — mirrors SyncCatalogCommand pattern
- `--dry-run` traverses all four API levels but performs zero DB writes (professions, recipes, reagents, watched items all skip)
- `--report-gaps` outputs a `$this->table()` with Profession, Total, Missing Item, Missing Qty, Coverage % columns
- Idempotent: `Profession::updateOrCreate`, `Recipe::updateOrCreate`, reagents delete+re-insert, `WatchedItem::firstOrCreate`
- `last_synced_at` set on every Recipe row after sync
- Missing `crafted_item` in API response → NULL `crafted_item_id_silver` / `crafted_item_id_gold` (no error)
- Unknown reagent items auto-create minimal `CatalogItem` entries with `category='reagent'` to satisfy FK constraints
- Auto-watch creates `WatchedItem` rows for user_id=1 with `profession` field set to profession name
- 429 rate-limit handling: adds to retry queue, pauses 10s, retries
- 10 Pest feature tests all green; full suite of 200 tests green

## Task Commits

Each task was committed atomically:

1. **Task 1: SyncRecipesCommand with tests** - `430a2fe` (feat)

_Note: TDD task — RED (10 failing tests written first) then GREEN (command implementation, all 10 passing)_

## Files Created/Modified

- `app/Console/Commands/SyncRecipesCommand.php` - Artisan command (250 lines) with three-level API traversal, Http::pool() batching, --dry-run, --report-gaps, auto-watch, gap stats, rate limit handling
- `tests/Feature/BlizzardApi/SyncRecipesCommandTest.php` - 10 Pest feature tests covering IMPORT-01 through IMPORT-06 plus edge cases

## Decisions Made

- Http::fake() wildcard patterns must be ordered from most-specific to least-specific; `profession/171/skill-tier/2100` and `media/profession/171` patterns placed before the generic `profession/171` pattern
- Reagents use delete+re-insert rather than updateOrCreate — simpler and ensures exact match on re-run without stale reagent rows
- Unknown reagents auto-create minimal CatalogItem (rather than skip/error) so the RecipeReagent FK is always satisfied and the item can be watched
- Auto-watch targets user_id=1 explicitly, matching the plan spec for v1.2 single-user assumption

## Deviations from Plan

None — plan executed exactly as written.

## Issues Encountered

One non-obvious bug found and fixed during GREEN phase:

**Http::fake() wildcard pattern conflict:** The pattern `*.api.blizzard.com/data/wow/profession/171*` matched the skill-tier URL (`/profession/171/skill-tier/2100`) because it was listed before the more-specific pattern. Fixed by reordering fake patterns in the test helper: skill-tier and media patterns listed before the generic profession-detail pattern.

This was a test setup issue, not a command bug.

## User Setup Required

None during testing. For production use, `config/services.php` must have `services.blizzard.region` set (defaults to `us`).

## Next Phase Readiness

- All three tables (professions, recipes, recipe_reagents) are populated by this command
- WatchedItem rows created for all recipe inputs/outputs, enabling Phase 14 profit calculation
- Phase 14 can query `Recipe::with(['reagents.catalogItem', 'craftedItemSilver'])` to compute costs

## Self-Check

Files verified:
- app/Console/Commands/SyncRecipesCommand.php: FOUND
- tests/Feature/BlizzardApi/SyncRecipesCommandTest.php: FOUND

Commit verified: 430a2fe

## Self-Check: PASSED

---
*Phase: 13-recipe-data-model-and-seed-command*
*Completed: 2026-03-05*
