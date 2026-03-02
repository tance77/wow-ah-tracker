---
phase: 03-item-watchlist-management
plan: "02"
subsystem: ui
tags: [livewire, volt, alpine, blade, tailwind, watchlist, crud]

# Dependency graph
requires:
  - phase: 03-01
    provides: CatalogItem model, WatchedItem model, User.watchedItems() HasMany, unique (user_id, blizzard_item_id) DB constraint

provides:
  - /watchlist route with auth middleware
  - Volt single-file watchlist component with full CRUD (add from catalog, add manual, inline threshold edit, instant remove)
  - Per-user data isolation via auth()->user()->watchedItems() scoping
  - Watchlist nav link in desktop and mobile navigation menus
  - Dashboard item count summary with link to /watchlist

affects:
  - 03-price-snapshots
  - 04-blizzard-api-polling
  - 07-price-charts

# Tech tracking
tech-stack:
  added: []
  patterns:
    - Volt class-based single-file component with #[Computed] properties
    - Alpine x-data with x-watch for inline editing UX pattern
    - All watchlist queries scoped through auth()->user()->watchedItems() (never WatchedItem::query())
    - wire:change on threshold inputs (not wire:model — avoids computed collection mutation)

key-files:
  created:
    - resources/views/livewire/pages/watchlist.blade.php
  modified:
    - routes/web.php
    - resources/views/livewire/layout/navigation.blade.php
    - resources/views/dashboard.blade.php

key-decisions:
  - "All watchlist queries go through auth()->user()->watchedItems() — never WatchedItem::query() or WatchedItem::find() — enforces ITEM-05 user isolation at model layer"
  - "wire:change used on threshold inputs instead of wire:model to avoid issues with updating values inside Computed collections"
  - "Threshold clamped server-side with max(1, min(100, $value)) — client min/max attributes are UX hints only"

patterns-established:
  - "Volt CRUD pattern: Computed properties for read, explicit action methods for write, all auth-scoped"
  - "Alpine inline-edit pattern: x-data={editing,saved}, x-ref + x-init $watch for auto-focus, blur triggers save"

requirements-completed: [ITEM-01, ITEM-02, ITEM-03, ITEM-04, ITEM-05]

# Metrics
duration: 2min
completed: "2026-03-01"
---

# Phase 3 Plan 02: Watchlist UI Summary

**Volt single-file watchlist component with catalog combobox search, manual item ID entry, per-user CRUD, and inline threshold editing wired to navigation and dashboard**

## Performance

- **Duration:** 2 min
- **Started:** 2026-03-01T21:01:36Z
- **Completed:** 2026-03-01T21:03:52Z
- **Tasks:** 2
- **Files modified:** 4

## Accomplishments
- Full /watchlist page with add-from-catalog combobox (debounced, dropdown suggestions), manual Blizzard item ID entry, inline threshold editing with Saved flash, and instant remove
- All data access scoped through auth()->user()->watchedItems() ensuring two users see completely separate watchlists (ITEM-05)
- Navigation bar updated with Watchlist link in both desktop and responsive (mobile) menus
- Dashboard replaced placeholder text with live item count and link to /watchlist

## Task Commits

Each task was committed atomically:

1. **Task 1: Create Watchlist Volt component with full CRUD and route wiring** - `e98214a` (feat)
2. **Task 2: Add Watchlist nav link and dashboard item count summary** - `6e61bd0` (feat)

**Plan metadata:** (docs commit follows)

## Files Created/Modified
- `resources/views/livewire/pages/watchlist.blade.php` - Volt class-based component with search combobox, manual ID form, watched items table with inline threshold editing, empty state
- `routes/web.php` - Added /watchlist route with auth middleware
- `resources/views/livewire/layout/navigation.blade.php` - Added Watchlist nav-link to desktop and responsive menus
- `resources/views/dashboard.blade.php` - Replaced placeholder with item count + watchlist link

## Decisions Made
- All watchlist queries go through `auth()->user()->watchedItems()` — never `WatchedItem::query()` — enforces user isolation at query layer
- `wire:change` used on threshold inputs (not `wire:model`) to avoid conflicts with values inside `#[Computed]` collection items
- Server-side threshold clamping with `max(1, min(100, $value))` — client `min`/`max` HTML attributes are UX only

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- /watchlist page is fully functional with all CRUD operations
- All 5 ITEM requirements (ITEM-01 through ITEM-05) fulfilled
- Phase 3 Plan 03 (price snapshot UI or polling trigger) can proceed
- The watchlist component will need to display price snapshot data once Phase 4 (Blizzard API polling) is complete

---
*Phase: 03-item-watchlist-management*
*Completed: 2026-03-01*
