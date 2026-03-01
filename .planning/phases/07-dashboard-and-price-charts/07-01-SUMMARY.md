---
phase: 07-dashboard-and-price-charts
plan: 01
subsystem: ui
tags: [apexcharts, livewire, volt, blade, tailwind, price-formatting]

# Dependency graph
requires:
  - phase: 03-item-watchlist-management
    provides: WatchedItem model with user relationship and priceSnapshots HasMany
  - phase: 05-data-ingestion-pipeline
    provides: PriceSnapshot model with median_price/min_price/polled_at columns
affects: [dashboard, price-charts, 07-02]

# Tech tracking
tech-stack:
  added: [apexcharts@5.7.0]
  patterns:
    - Volt SFC with bare <script> block using window.ApexCharts (no ES module import)
    - $wire.$on('chart-data-updated') for Livewire-to-JS chart updates
    - Eager-load 2 snapshots per item for trend without N+1

key-files:
  created:
    - resources/views/livewire/pages/dashboard.blade.php
  modified:
    - resources/js/app.js
    - routes/web.php
    - package.json
  deleted:
    - resources/views/dashboard.blade.php

key-decisions:
  - "ApexCharts registered as window.ApexCharts in app.js — Volt bare script blocks cannot use ES module import syntax"
  - "Eager-load only 2 snapshots per item (latest('polled_at')->limit(2)) for trend computation — avoids N+1 without fetching full history"
  - "loadChart() dispatches millisecond timestamps (->timestamp * 1000) for ApexCharts datetime x-axis"
  - "Chart state managed in JS (chart === null check) — updateOptions() used on subsequent changes to avoid flicker"
  - "dataFreshness() joins price_snapshots via max(polled_at) across all user's watched items"

patterns-established:
  - "Gold formatter: intdiv(copper, 10000) → g, intdiv(copper % 10000, 100) → s, copper % 100 → c with zero-suppression"
  - "DASH-06 isolation: all queries through auth()->user()->watchedItems() — never WatchedItem::query()"
  - "Volt SFC three-section pattern: PHP class → Blade template → bare <script> block"

requirements-completed: [DASH-01, DASH-02, DASH-03, DASH-06]

# Metrics
duration: 15min
completed: 2026-03-01
---

# Phase 7 Plan 01: Dashboard and Price Charts Summary

**Livewire Volt SFC dashboard with ApexCharts line chart (median + min), summary cards showing gold/silver/copper pricing with trend arrows, timeframe toggle (24h/7d/30d), empty/no-snapshot states, and DASH-06 user isolation**

## Performance

- **Duration:** ~15 min
- **Started:** 2026-03-01T23:15:00Z
- **Completed:** 2026-03-01T23:30:00Z
- **Tasks:** 2
- **Files modified:** 5 (1 deleted, 1 created, 3 modified)

## Accomplishments

- Installed ApexCharts 5.7.0 and registered globally as `window.ApexCharts` for Volt script block compatibility
- Created full Volt SFC dashboard replacing placeholder: summary cards, chart panel, timeframe toggle, gold formatter, trend arrows
- All queries strictly scoped to `auth()->user()->watchedItems()` per DASH-06 user isolation requirement

## Task Commits

Each task was committed atomically:

1. **Task 1: Install ApexCharts and wire global registration** - `971e881` (feat)
2. **Task 2: Build Volt dashboard component with summary cards, chart panel, timeframe toggle, and gold formatter** - `f46b053` (feat)

## Files Created/Modified

- `resources/views/livewire/pages/dashboard.blade.php` - New Volt SFC dashboard component (PHP class + Blade template + bare script block)
- `resources/js/app.js` - Added `window.ApexCharts = ApexCharts` global registration
- `routes/web.php` - Converted /dashboard from static closure to `Volt::route('pages.dashboard')`
- `package.json` / `package-lock.json` - Added apexcharts@5.7.0 to dependencies
- `resources/views/dashboard.blade.php` - Deleted (replaced by Volt component)

## Decisions Made

- Bare `<script>` block (not `@script`/`@endscript`) used in Volt SFC — Volt single-file components use bare tags
- ApexCharts registered on `window` in `app.js` instead of local import in script block — Volt bare script blocks run in non-module context
- `->timestamp * 1000` for millisecond epoch conversion rather than `getPreciseTimestamp(3)` — safer across PHP versions
- `chart === null` guard before `new ApexCharts()` — reuses chart instance via `updateOptions()` to prevent DOM flicker on timeframe toggle
- Pint code style applied to PHP section of Volt SFC

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None - all steps completed without errors. ApexCharts 5.7.0 resolved cleanly. Vite build produced a size warning (548KB bundle) which is expected with ApexCharts and is non-blocking.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- Dashboard is fully functional: cards, chart, timeframe toggle, empty states all in place
- Ready for Phase 7 Plan 02 if it exists (additional dashboard features or price chart enhancements)
- ApexCharts globally available for any future chart components
- Blocker in STATE.md (livewire-charts compatibility concern) is resolved — direct ApexCharts approach confirmed working

---
*Phase: 07-dashboard-and-price-charts*
*Completed: 2026-03-01*
