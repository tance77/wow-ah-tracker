---
phase: 15-profession-overview-page-and-navigation
plan: 02
subsystem: ui
tags: [laravel, livewire, volt, crafting, profession-cards]

requires:
  - phase: 15-profession-overview-page-and-navigation
    plan: 01
    provides: Slug migration, routes, nav link, feature tests

provides:
  - Crafting overview page with profession cards and top 5 profitable recipes
  - Responsive grid layout (3/2/1 columns) with gold-formatted profit display
  - Summary stats header (profession count, recipe count, profitable count)

affects: [16-per-profession-recipe-table]

tech-stack:
  added: []
  patterns:
    - Livewire Volt SFC with Computed properties for eager-loaded profession data
    - RecipeProfitAction invoked per recipe with dynamic properties on Profession model
    - onerror fallback for broken img tags

key-files:
  created: []
  modified:
    - resources/views/livewire/pages/crafting.blade.php

key-decisions:
  - "Used dynamic underscore-prefixed properties (_top_recipes, _total_recipes, etc.) on Profession model for computed display data"
  - "Added onerror handler on img tags to gracefully handle broken/unreachable icon URLs"

requirements-completed: [OVERVIEW-01, OVERVIEW-02]

duration: 6min
completed: 2026-03-05
---

# Phase 15 Plan 02: Crafting Overview Page with Profession Cards Summary

**Full Livewire Volt SFC with profession cards showing top 5 profitable recipes, gold-formatted profit, responsive grid, and summary stats header**

## Performance

- **Duration:** 6 min
- **Started:** 2026-03-05T23:03:52Z
- **Completed:** 2026-03-05T23:10:09Z
- **Tasks:** 2 (1 auto + 1 human-verify checkpoint)
- **Files modified:** 1

## Accomplishments
- Replaced placeholder crafting overview page with full Livewire Volt SFC
- Profession cards display icon, name, top 5 recipes sorted by median profit descending
- Recipes with missing price data excluded from top list
- Professions sorted by most profitable first (sortByDesc _best_profit)
- Summary stats header shows profession count, recipe count, profitable count with bull separators
- Green text for profitable recipes (+prefix), red text for losses
- Responsive grid: 3 columns desktop, 2 tablet, 1 mobile
- Cards link to /crafting/{slug} with wire:navigate
- All 8 CraftingOverviewTest tests pass

## Task Commits

Each task was committed atomically:

1. **Task 1: Crafting overview Volt SFC page** - `17d5e5c` (feat)
2. **Icon fix: Handle broken icon URLs** - `fe30557` (fix)

## Files Modified
- `resources/views/livewire/pages/crafting.blade.php` - Full Volt SFC with PHP computed properties and Blade template

## Decisions Made
- Used dynamic underscore-prefixed properties (_top_recipes, _total_recipes, _profitable_count, _best_profit) on Profession model instances for computed display data rather than separate DTOs
- Added onerror handler on img tags to gracefully hide broken icon URLs and show SVG fallback placeholder

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Fixed broken profession icon display**
- **Found during:** Task 2 (human-verify checkpoint)
- **Issue:** Profession icon_url values were either null or pointed to unreachable URLs (e.g., example.com placeholder), causing broken image icons in the browser
- **Fix:** Added onerror JavaScript handler on img tags that hides the broken image and reveals a hidden SVG fallback placeholder
- **Files modified:** resources/views/livewire/pages/crafting.blade.php
- **Commit:** fe30557

## Issues Encountered

None beyond the icon display fix above.

## Next Phase Readiness
- Phase 16 (per-profession recipe table) can build on the /crafting/{slug} detail page
- All overview page functionality is complete and tested

---
*Phase: 15-profession-overview-page-and-navigation*
*Completed: 2026-03-05*
