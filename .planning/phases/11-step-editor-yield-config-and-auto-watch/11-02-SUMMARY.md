---
phase: 11-step-editor-yield-config-and-auto-watch
plan: "02"
subsystem: ui
tags: [livewire, volt, alpine, blade, shuffle, steps, watchlist, middleware]

# Dependency graph
requires:
  - phase: 11-step-editor-yield-config-and-auto-watch-01
    provides: ShuffleStep model, migrations, factories, test suite (RED)
  - phase: 10-shuffle-crud-navigation
    provides: Shuffle model, shuffle-detail.blade.php shell, Livewire Volt component pattern
provides:
  - Full step editor UI in shuffle-detail.blade.php replacing placeholder
  - addStep, saveStep, deleteStep, moveStepUp, moveStepDown Livewire actions
  - autoWatch() silently creating WatchedItem entries on step save
  - Item search comboboxes (inputSuggestions, outputSuggestions computed) using CatalogItem
  - EnsureShuffleOwner route middleware for HTTP-level authorization
affects: [phase-12-batch-profit-calculator]

# Tech tracking
tech-stack:
  added: [App\Http\Middleware\EnsureShuffleOwner]
  patterns:
    - Livewire computed properties with unset() to refresh after mutations
    - autoWatch via firstOrCreate with null thresholds and shuffle provenance
    - Route middleware for HTTP 403, action-level abort for Livewire action 403
    - Alpine x-data per step card for local yield editing state
    - Item search combobox with wire:model.live.debounce and x-show dropdown

key-files:
  created:
    - app/Http/Middleware/EnsureShuffleOwner.php
  modified:
    - resources/views/livewire/pages/shuffle-detail.blade.php
    - bootstrap/app.php
    - routes/web.php

key-decisions:
  - "Auth split: EnsureShuffleOwner middleware handles HTTP-level 403 (mount must succeed for Livewire test snapshot); addStep enforces 403 at action level for Livewire test assertForbidden()"
  - "EnsureShuffleOwner manually resolves shuffle from string ID when SubstituteBindings hasn't run yet due to middleware priority ordering in Volt routes"
  - "Middleware priority list extended to include EnsureShuffleOwner after SubstituteBindings"
  - "Step editor UI and PHP actions implemented together in one file rather than in separate commits (single-file Livewire Volt component)"

patterns-established:
  - "Livewire action authorization: HTTP protection via named route middleware, Livewire action protection via abort_unless in action method"
  - "Computed property cache invalidation: unset($this->steps) after any mutation that changes the steps collection"
  - "Alpine yield editing: x-data per card with local state and saveYield() JS method calling $wire.saveStep()"

requirements-completed: [SHUF-02, YILD-01, YILD-02, YILD-03, INTG-01]

# Metrics
duration: 9min
completed: 2026-03-05
---

# Phase 11 Plan 02: Step Editor Blade/Alpine UI and Livewire Actions Summary

**Full shuffle step editor with item search comboboxes, inline yield editing with range toggle, chain flow visualization, and silent auto-watch via firstOrCreate on step save**

## Performance

- **Duration:** 9 min
- **Started:** 2026-03-05T05:03:51Z
- **Completed:** 2026-03-05T05:13:05Z
- **Tasks:** 2 (implemented together as single Livewire Volt component)
- **Files modified:** 4

## Accomplishments

- Replaced "Step editor coming soon" placeholder with full functional step editor
- All 19 ShuffleStepEditorTest tests now pass (GREEN) — addStep, saveStep, deleteStep, moveStepUp, moveStepDown, autoWatch, yield validation, orphan cleanup
- Auto-watch silently creates WatchedItems with null thresholds and shuffle provenance on step save
- Step cards show item icons (32x32), names, chain flow arrows, inline editable yield fields with range toggle
- "Add Step" form with search comboboxes matching watchlist.blade.php pattern
- Empty state with "Add First Step" button; step count badge in header
- Full test suite (156 tests) remains green — no regressions

## Task Commits

1. **Task 1 + 2: Step editor PHP actions and Blade/Alpine UI** - `027c6a3` (feat)

**Plan metadata:** (this commit)

## Files Created/Modified

- `resources/views/livewire/pages/shuffle-detail.blade.php` - Complete rewrite with PHP actions + Blade/Alpine UI
- `app/Http/Middleware/EnsureShuffleOwner.php` - HTTP-level ownership check for shuffle route
- `bootstrap/app.php` - Register shuffle.owner middleware alias and priority
- `routes/web.php` - Apply shuffle.owner middleware to shuffles.show route

## Decisions Made

- **Auth split between middleware and action:** Livewire v4 aborts in mount cause invalid snapshot state in `->test()->call()` chains. Moving HTTP auth to route middleware (EnsureShuffleOwner) and keeping `abort_unless` inside `addStep` allows both the HTTP 403 test (ShuffleCrudTest) and the Livewire action 403 test (ShuffleStepEditorTest) to pass correctly.
- **Manual model resolution in middleware:** EnsureShuffleOwner resolves the Shuffle manually from string ID when SubstituteBindings hasn't run yet, handling Volt route middleware priority edge cases.
- **Single commit for both tasks:** Task 1 (PHP) and Task 2 (Blade/Alpine) are a single-file Livewire Volt component — they were implemented together and committed as one atomic change.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Livewire v4 abort-in-mount causes invalid snapshot for action tests**
- **Found during:** Task 1 (addStep authorization)
- **Issue:** `abort_unless($shuffle->user_id === auth()->id(), 403)` in `mount()` causes Livewire to create invalid snapshot when test chains `->test()->call()->assertForbidden()`. The `InvalidArgumentException` is thrown server-side before 403 response is returned.
- **Fix:** Moved HTTP-level auth to `EnsureShuffleOwner` route middleware. Auth in `addStep` provides action-level 403 for the Livewire test assertion.
- **Files modified:** app/Http/Middleware/EnsureShuffleOwner.php, bootstrap/app.php, routes/web.php
- **Verification:** Both ShuffleCrudTest (HTTP 403) and ShuffleStepEditorTest (Livewire 403) pass
- **Committed in:** 027c6a3

**2. [Rule 3 - Blocking] Middleware runs before SubstituteBindings in Volt routes**
- **Found during:** Task 1 (EnsureShuffleOwner middleware)
- **Issue:** Custom middleware receives raw string shuffle ID instead of resolved Shuffle model because middleware priority doesn't place it after SubstituteBindings automatically
- **Fix:** Manual `Shuffle::find($shuffle)` fallback in middleware + added EnsureShuffleOwner to middleware priority list after SubstituteBindings
- **Files modified:** app/Http/Middleware/EnsureShuffleOwner.php, bootstrap/app.php
- **Verification:** ShuffleCrudTest "returns 403" test passes with correct model data
- **Committed in:** 027c6a3

---

**Total deviations:** 2 auto-fixed (1 bug, 1 blocking)
**Impact on plan:** Both auto-fixes required for correct authorization behavior. No scope creep.

## Issues Encountered

- Livewire v4 test mode + mount abort = invalid snapshot structure. Required architectural shift to route middleware + action-level auth pattern. Resolved automatically per deviation rules.

## Next Phase Readiness

- Phase 11 complete: step editor ships with full CRUD, item search, yield ranges, auto-watch
- Phase 12 (batch profit calculator) can now iterate over ShuffleStep records via shuffle->steps() to compute real profit chains
- ShuffleStep model and all Livewire actions provide the foundation for Phase 12's price-based calculations

## Self-Check: PASSED

- FOUND: resources/views/livewire/pages/shuffle-detail.blade.php
- FOUND: app/Http/Middleware/EnsureShuffleOwner.php
- FOUND: .planning/phases/11-step-editor-yield-config-and-auto-watch/11-02-SUMMARY.md
- FOUND: commit 027c6a3

---
*Phase: 11-step-editor-yield-config-and-auto-watch*
*Completed: 2026-03-05*
