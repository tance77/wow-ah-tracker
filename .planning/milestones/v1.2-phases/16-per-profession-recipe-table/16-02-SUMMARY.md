---
phase: 16-per-profession-recipe-table
plan: 02
subsystem: ui
tags: [alpine, blade, sorting, filtering, accordion, recipe-table, staleness-banner]

# Dependency graph
requires:
  - phase: 16-per-profession-recipe-table plan 01
    provides: "#[Computed] recipeData property with full recipe dataset (profit, reagents, staleness)"
provides:
  - "Interactive per-profession recipe table with Alpine.js sorting, filtering, and accordion expansion"
  - "Missing-price amber badges and non-commodity row handling"
  - "Staleness banner for stale price data (>60 min)"
affects: []

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Alpine.js x-data with @js() data hydration for client-side sorting/filtering (no Livewire round-trips)"
    - "Partition-sort pattern: normal recipes sorted independently from bottom-tier (missing-price + non-commodity)"
    - "formatGold() JavaScript port of PHP FormatsAuctionData trait for client-side gold display"

key-files:
  created: []
  modified:
    - resources/views/livewire/pages/crafting-detail.blade.php

key-decisions:
  - "Alpine-only sorting/filtering with no Livewire wire:click round-trips for instant UI response"
  - "Partition-sort: missing-price and non-commodity recipes always sort to bottom regardless of sort column"
  - "Single accordion pattern: only one recipe row expanded at a time via expandedRow ID tracking"

patterns-established:
  - "formatGold(copper) JS function mirrors PHP trait for consistent gold/silver/copper display"
  - "x-if conditional colspan for non-commodity rows avoids DOM mismatch with regular profit cells"

requirements-completed: [TABLE-01, TABLE-02, TABLE-03, TABLE-04, TABLE-05, TABLE-06]

# Metrics
duration: 2min
completed: 2026-03-05
---

# Phase 16 Plan 02: Per-Profession Recipe Table UI Summary

**Alpine.js interactive recipe table with client-side sorting, text search filtering, accordion reagent breakdowns, missing-price badges, non-commodity dimming, and staleness banner**

## Performance

- **Duration:** 2 min
- **Started:** 2026-03-06T00:00:00Z
- **Completed:** 2026-03-06T00:02:00Z
- **Tasks:** 2
- **Files modified:** 1

## Accomplishments
- Full interactive recipe table with 5 sortable columns (name, reagent cost, T1 profit, T2 profit, median profit)
- Text search filtering with reactive recipe count indicator
- Accordion row expansion showing per-reagent cost breakdown (quantity, unit price, subtotal)
- Missing-price recipes display amber badge and em dashes in profit columns, sorted to bottom
- Non-commodity recipes show "Realm AH -- not tracked" with dimmed styling, sorted to bottom
- Staleness banner appears when any price snapshot is older than 60 minutes
- Mobile-responsive with horizontal scroll wrapper

## Task Commits

Each task was committed atomically:

1. **Task 1: Build the full Alpine.js recipe table UI** - `b8e0412` (feat)
2. **Task 2: Visual verification of recipe table** - user-approved checkpoint (no code commit)

## Files Created/Modified
- `resources/views/livewire/pages/crafting-detail.blade.php` - Complete Blade template with Alpine.js sort, filter, accordion, staleness banner, and conditional non-commodity/missing-price handling

## Decisions Made
- Alpine-only client-side sorting/filtering avoids Livewire round-trips for instant interaction
- Partition-sort ensures missing-price and non-commodity recipes always appear at the bottom regardless of which column is sorted
- Single-accordion UX: clicking a new row closes the previously expanded row
- formatGold() JavaScript function ported from PHP FormatsAuctionData trait for consistent display

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered
None.

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- Phase 16 (Per-Profession Recipe Table) is fully complete -- all plans delivered
- v1.2 Crafting Profitability milestone is now feature-complete
- All TABLE requirements (TABLE-01 through TABLE-06) verified by feature tests (Plan 01) and visual approval (Plan 02)

## Self-Check: PASSED

- FOUND: resources/views/livewire/pages/crafting-detail.blade.php
- FOUND: commit b8e0412
- FOUND: 16-02-SUMMARY.md

---
*Phase: 16-per-profession-recipe-table*
*Completed: 2026-03-05*
