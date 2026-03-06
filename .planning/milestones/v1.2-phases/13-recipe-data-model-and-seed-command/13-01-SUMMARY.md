---
phase: 13-recipe-data-model-and-seed-command
plan: "01"
subsystem: database
tags: [eloquent, migrations, factories, pest, cascadeOnDelete]

requires: []
provides:
  - professions table (blizzard_profession_id, name, icon_url, last_synced_at)
  - recipes table (profession_id FK, blizzard_recipe_id, dual crafted_item FKs, crafted_quantity, is_commodity, last_synced_at)
  - recipe_reagents table (recipe_id FK, catalog_item_id FK, quantity)
  - Profession Eloquent model with recipes() hasMany
  - Recipe Eloquent model with profession(), reagents(), craftedItemSilver(), craftedItemGold() relationships
  - RecipeReagent Eloquent model with recipe() and catalogItem() relationships
  - ProfessionFactory, RecipeFactory, RecipeReagentFactory
affects:
  - 13-02 (SyncRecipesCommand populates these tables)
  - phase-14 (profit calculation queries recipes and reagents)
  - phase-15 (UI pages render recipe data)
  - phase-16 (sorting queries recipes table)

tech-stack:
  added: []
  patterns:
    - TDD (RED tests first, then GREEN implementation)
    - declare(strict_types=1) on all models and factories
    - HasFactory trait on all Eloquent models
    - foreignId()->constrained()->cascadeOnDelete() for FK cascade pattern
    - foreignId()->nullable()->constrained()->nullOnDelete() for optional FK pattern

key-files:
  created:
    - database/migrations/2026_03_06_200000_create_professions_table.php
    - database/migrations/2026_03_06_200001_create_recipes_table.php
    - database/migrations/2026_03_06_200002_create_recipe_reagents_table.php
    - app/Models/Profession.php
    - app/Models/Recipe.php
    - app/Models/RecipeReagent.php
    - database/factories/ProfessionFactory.php
    - database/factories/RecipeFactory.php
    - database/factories/RecipeReagentFactory.php
    - tests/Feature/RecipeDataModelTest.php
  modified: []

key-decisions:
  - "Dual crafted_item FK columns (crafted_item_id_silver, crafted_item_id_gold) as nullable foreignIds with nullOnDelete — Blizzard API does not reliably return both quality tiers"
  - "Cascade delete from profession propagates to recipes then recipe_reagents via DB-level FK constraints"
  - "crafted_quantity default 1 covers 90%+ of recipes correctly when API omits the value"

patterns-established:
  - "Dual FK nullable pattern: foreignId()->nullable()->constrained('catalog_items')->nullOnDelete() for quality-tier item pairs"
  - "Cascade chain: Profession -> Recipe -> RecipeReagent via cascadeOnDelete on each FK"

requirements-completed:
  - IMPORT-06

duration: 2min
completed: "2026-03-05"
---

# Phase 13 Plan 01: Recipe Data Model and Seed Command Summary

**Three-table Laravel data model (professions, recipes, recipe_reagents) with Eloquent models, dual crafted-item FK columns, cascade-delete chain, and 11 Pest tests — all green**

## Performance

- **Duration:** 2 min
- **Started:** 2026-03-05T19:23:04Z
- **Completed:** 2026-03-05T19:25:09Z
- **Tasks:** 1
- **Files modified:** 10

## Accomplishments
- Three migrations create professions, recipes, and recipe_reagents tables with correct column types and FK constraints
- Recipe model has dual nullable FKs (crafted_item_id_silver, crafted_item_id_gold) pointing to catalog_items with nullOnDelete
- Cascade delete chain: deleting a Profession cascades to its Recipes and their RecipeReagents via DB-level constraints
- Three factories produce valid test data including nested factory relationships (RecipeReagent -> CatalogItem)
- 11 Pest tests (migrations, factories, relationships, cascade, datetime cast) all pass; full suite of 190 tests green

## Task Commits

Each task was committed atomically:

1. **Task 1: Migrations, models, and factories for professions, recipes, and recipe_reagents** - `316638a` (feat)

**Plan metadata:** (see final metadata commit below)

_Note: TDD task had RED (tests written first, all failing) then GREEN (implementation, all passing)_

## Files Created/Modified
- `database/migrations/2026_03_06_200000_create_professions_table.php` - professions schema
- `database/migrations/2026_03_06_200001_create_recipes_table.php` - recipes schema with dual crafted_item FK columns
- `database/migrations/2026_03_06_200002_create_recipe_reagents_table.php` - recipe_reagents schema with composite index
- `app/Models/Profession.php` - Eloquent model with recipes() hasMany
- `app/Models/Recipe.php` - Eloquent model with profession(), reagents(), craftedItemSilver(), craftedItemGold()
- `app/Models/RecipeReagent.php` - Eloquent model with recipe() and catalogItem()
- `database/factories/ProfessionFactory.php` - factory for professions
- `database/factories/RecipeFactory.php` - factory for recipes (defaults: null crafted items, is_commodity=true)
- `database/factories/RecipeReagentFactory.php` - factory for reagents using nested factories
- `tests/Feature/RecipeDataModelTest.php` - 11 Pest tests covering all behaviors

## Decisions Made
- Dual nullable FK columns (crafted_item_id_silver, crafted_item_id_gold) with nullOnDelete — matches Blizzard API's unreliable quality-tier data
- DB-level cascade delete (not Eloquent events) for correctness and performance
- index on profession_id in recipes table and composite index on [recipe_id, catalog_item_id] in recipe_reagents

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered
None

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- All three tables and models ready for Plan 02: SyncRecipesCommand to populate from Blizzard API
- RecipeReagent->catalogItem() relationship ready for Phase 14 profit calculations
- Cascade delete tested and working end-to-end

## Self-Check

Files verified:
- database/migrations/2026_03_06_200000_create_professions_table.php: FOUND
- database/migrations/2026_03_06_200001_create_recipes_table.php: FOUND
- database/migrations/2026_03_06_200002_create_recipe_reagents_table.php: FOUND
- app/Models/Profession.php: FOUND
- app/Models/Recipe.php: FOUND
- app/Models/RecipeReagent.php: FOUND
- database/factories/ProfessionFactory.php: FOUND
- database/factories/RecipeFactory.php: FOUND
- database/factories/RecipeReagentFactory.php: FOUND
- tests/Feature/RecipeDataModelTest.php: FOUND

Commit verified: 316638a

## Self-Check: PASSED

---
*Phase: 13-recipe-data-model-and-seed-command*
*Completed: 2026-03-05*
