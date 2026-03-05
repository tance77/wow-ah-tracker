---
phase: 15-profession-overview-page-and-navigation
plan: 01
subsystem: database, ui, testing
tags: [laravel, livewire, volt, pest, migration, slug, routing]

requires:
  - phase: 14-profit-calculation-action
    provides: RecipeProfitAction for profit calculations in tests

provides:
  - Slug column on professions table with auto-generation
  - /crafting and /crafting/{profession} routes with auth middleware
  - Crafting nav link in desktop and mobile navigation
  - Placeholder Volt SFC pages for crafting overview and detail
  - Feature test suite covering NAV-01, OVERVIEW-01, OVERVIEW-02

affects: [15-02-PLAN, 16-per-profession-recipe-table]

tech-stack:
  added: []
  patterns:
    - Slug-based route model binding on Profession model
    - Model boot events for auto-slug generation

key-files:
  created:
    - database/migrations/2026_03_06_300000_add_slug_to_professions_table.php
    - resources/views/livewire/pages/crafting.blade.php
    - resources/views/livewire/pages/crafting-detail.blade.php
    - tests/Feature/CraftingOverviewTest.php
  modified:
    - app/Models/Profession.php
    - database/factories/ProfessionFactory.php
    - routes/web.php
    - resources/views/livewire/layout/navigation.blade.php

key-decisions:
  - "Used model booted() with creating/updating events for slug auto-generation rather than observer"
  - "ProfessionFactory generates slug from name using Str::slug to match model behavior"

patterns-established:
  - "Slug route model binding: override getRouteKeyName() on model, use {model} param in route"

requirements-completed: [NAV-01, OVERVIEW-01, OVERVIEW-02]

duration: 3min
completed: 2026-03-05
---

# Phase 15 Plan 01: Slug Migration, Routes, Nav Link, and Feature Tests Summary

**Slug column on professions with auto-generation, /crafting routes with auth middleware, Crafting nav link, and 8 Pest feature tests for the overview page**

## Performance

- **Duration:** 3 min
- **Started:** 2026-03-05T22:18:44Z
- **Completed:** 2026-03-05T22:21:29Z
- **Tasks:** 2
- **Files modified:** 8

## Accomplishments
- Slug column added to professions table with backfill migration and unique constraint
- Profession model auto-generates slugs on create/update and uses slug for route model binding
- /crafting and /crafting/{profession} routes registered with auth middleware
- Crafting nav link added to both desktop and mobile navigation menus
- 8 feature tests written covering all 3 requirements (3 pass now, 5 pending Plan 02)
- Full test suite (211 tests) passes with no regressions

## Task Commits

Each task was committed atomically:

1. **Task 1: Slug migration, model update, routes, and nav link** - `10e7410` (feat)
2. **Task 2: Feature tests for crafting overview** - `eda930f` (test)

## Files Created/Modified
- `database/migrations/2026_03_06_300000_add_slug_to_professions_table.php` - Adds slug column with backfill
- `app/Models/Profession.php` - Slug auto-generation, route model binding via slug
- `database/factories/ProfessionFactory.php` - Added slug field to factory definition
- `routes/web.php` - Added /crafting and /crafting/{profession} Volt routes
- `resources/views/livewire/layout/navigation.blade.php` - Crafting nav link (desktop + mobile)
- `resources/views/livewire/pages/crafting.blade.php` - Placeholder overview page
- `resources/views/livewire/pages/crafting-detail.blade.php` - Placeholder detail page
- `tests/Feature/CraftingOverviewTest.php` - 8 feature tests with helper function

## Decisions Made
- Used model `booted()` with creating/updating events for slug auto-generation rather than a separate observer class
- ProfessionFactory generates slug from name using `Str::slug()` to match model behavior
- Test helper function `createRecipeWithProfit()` creates complete recipe chains with known sell price and reagent cost for deterministic profit assertions

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness
- Plan 02 can build the full crafting overview page on top of these placeholder pages
- 5 feature tests are ready to validate Plan 02 implementation (currently failing as expected)
- Route model binding via slug is ready for Phase 16 detail page

---
*Phase: 15-profession-overview-page-and-navigation*
*Completed: 2026-03-05*
