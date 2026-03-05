---
phase: 11-step-editor-yield-config-and-auto-watch
plan: 01
subsystem: database
tags: [laravel, eloquent, migrations, livewire, pest, shuffle, watched-items]

requires:
  - phase: 09-data-foundation
    provides: ShuffleStep model, WatchedItem model with created_by_shuffle_id, Shuffle orphan cleanup pattern
  - phase: 10-shuffle-crud-navigation
    provides: ShuffleStep factory, shuffle-detail Volt component shell

provides:
  - input_qty column on shuffle_steps (unsigned integer, default 1)
  - nullable buy_threshold and sell_threshold on watched_items for auto-watch
  - ShuffleStep::boot() deleted event for per-step orphan cleanup
  - ShuffleStepEditorTest.php with 19 tests (16 RED awaiting Plan 02, 3 GREEN)

affects:
  - 11-02 (Plan 02 must implement addStep/saveStep/moveStepUp/moveStepDown/deleteStep to make tests pass)

tech-stack:
  added: []
  patterns:
    - "ShuffleStep::boot() deleted event — per-step orphan cleanup after delete using whereNotNull(created_by_shuffle_id)"
    - "TDD RED phase — write Livewire action tests before implementing component methods"

key-files:
  created:
    - database/migrations/2026_03_06_000000_add_input_qty_to_shuffle_steps.php
    - database/migrations/2026_03_06_000001_make_watched_item_thresholds_nullable.php
    - tests/Feature/ShuffleStepEditorTest.php
  modified:
    - app/Models/ShuffleStep.php

key-decisions:
  - "Used deleted event (after-delete) not deleting event (before-delete) in ShuffleStep::boot() so the step is already gone from DB when orphan check runs — ShuffleStep::where(...)->exists() returns false for truly orphaned items"
  - "Orphan cleanup in ShuffleStep filters whereNotNull(created_by_shuffle_id) to preserve manually-added watchlist items"
  - "TDD RED tests call Livewire methods that don't exist yet — 16 tests will fail until Plan 02 implements addStep/saveStep/moveStep/deleteStep"

patterns-established:
  - "Per-step orphan cleanup mirrors Shuffle-level cleanup but uses simpler exists() check since step is already deleted from DB when deleted event fires"

requirements-completed: [SHUF-02, YILD-01, YILD-02, YILD-03, INTG-01]

duration: 3min
completed: 2026-03-05
---

# Phase 11 Plan 01: Schema Foundation and Step Editor Tests Summary

**input_qty migration, nullable thresholds migration, ShuffleStep orphan cleanup boot event, and 19-test RED/GREEN feature test suite for step editor behaviors**

## Performance

- **Duration:** 3 min
- **Started:** 2026-03-05T04:57:12Z
- **Completed:** 2026-03-05T05:00:12Z
- **Tasks:** 2
- **Files modified:** 4

## Accomplishments

- Created migration adding `input_qty` unsigned integer (default 1) to `shuffle_steps` — enables "5 ore -> 1 gem" ratio expressions
- Created migration making `buy_threshold` and `sell_threshold` nullable on `watched_items` — required for auto-watched items that get null thresholds (price tracking only)
- Added `input_qty` to ShuffleStep `$fillable` and `$casts`, plus `boot()` `deleted` event for per-step orphan cleanup
- Created `ShuffleStepEditorTest.php` with 19 tests: 16 failing (RED — awaiting Plan 02 Livewire methods) and 3 passing (GREEN — model-level orphan cleanup works now)

## Task Commits

Each task was committed atomically:

1. **Task 1: Create migrations and update ShuffleStep model** - `c14ed0a` (feat)
2. **Task 2: Create feature tests (TDD RED)** - `3c86d67` (test)

_Note: TDD RED phase — tests intentionally fail until Plan 02 implements component methods_

## Files Created/Modified

- `database/migrations/2026_03_06_000000_add_input_qty_to_shuffle_steps.php` - Adds input_qty column to shuffle_steps with default 1
- `database/migrations/2026_03_06_000001_make_watched_item_thresholds_nullable.php` - Makes buy_threshold/sell_threshold nullable on watched_items
- `app/Models/ShuffleStep.php` - Added input_qty to fillable/casts; added boot() deleted event for orphan cleanup
- `tests/Feature/ShuffleStepEditorTest.php` - 19 tests covering SHUF-02, YILD-01/02/03, INTG-01

## Decisions Made

- Used `deleted` (post-delete) event rather than `deleting` (pre-delete) in `ShuffleStep::boot()` — because the step row is already removed from DB when the `deleted` event fires, so `ShuffleStep::where(...)->exists()` correctly returns false for truly orphaned items
- Orphan cleanup always filters `whereNotNull('created_by_shuffle_id')` — preserves manually-added watchlist items even if they overlap with shuffle item IDs
- TDD approach: tests call Livewire methods that don't exist yet in the shuffle-detail component — 16 tests are deliberately RED so Plan 02 has clear pass/fail targets

## Deviations from Plan

None — plan executed exactly as written.

## Issues Encountered

None.

## User Setup Required

None — no external service configuration required.

## Next Phase Readiness

- Schema foundation is complete: `input_qty` column exists, thresholds nullable, migrations ran
- ShuffleStep model has orphan cleanup wired via `deleted` boot event
- 16 RED tests define the exact API surface Plan 02 must implement: `addStep(int, int, int, int, int)`, `saveStep(int, int, int, int)`, `moveStepUp(int)`, `moveStepDown(int)`, `deleteStep(int)`
- 3 GREEN orphan cleanup tests confirm model-level behavior works independently of UI

---
*Phase: 11-step-editor-yield-config-and-auto-watch*
*Completed: 2026-03-05*
