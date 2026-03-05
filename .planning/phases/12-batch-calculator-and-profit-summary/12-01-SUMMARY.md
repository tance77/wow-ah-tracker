---
phase: 12-batch-calculator-and-profit-summary
plan: "01"
subsystem: ui
tags: [livewire, volt, alpine, computed-properties, profit-calculation]

# Dependency graph
requires:
  - phase: 11-step-editor-yield-config-and-auto-watch
    provides: ShuffleStep model with input_qty/output_qty_min/output_qty_max and shuffle detail Volt component

provides:
  - profitPerUnit() with proper multi-step cascade logic via floor(qty * output_min / input_qty) per step
  - priceData() computed property on shuffle-detail with staleness detection (>60 min)
  - calculatorSteps() computed property returning Alpine-friendly step array with item names/icons
  - Calculator section placeholder in shuffle-detail blade (data-calculator-section) hidden when no steps

affects:
  - 12-batch-calculator-and-profit-summary/12-02 (Plan 02 consumes priceData() and calculatorSteps() for Alpine UI)

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Cascade yield calculation: floor(qty * step.output_qty_min / max(1, step.input_qty)) through all steps"
    - "priceData() uses single CatalogItem query + single PriceSnapshot query with application-side groupBy for N+1 avoidance"
    - "Staleness: polled_at->diffInMinutes(now()) > 60 (not now()->diffInMinutes() which returns negative for past dates)"
    - "Cache invalidation: unset($this->priceData) and unset($this->calculatorSteps) alongside unset($this->steps) in step mutations"

key-files:
  created:
    - tests/Feature/ShuffleBatchCalculatorTest.php
  modified:
    - app/Models/Shuffle.php
    - resources/views/livewire/pages/shuffle-detail.blade.php

key-decisions:
  - "profitPerUnit() cascade: floor(qty * output_qty_min / input_qty) per step (matches CONTEXT.md cascade spec)"
  - "priceData() query pattern: single CatalogItem query + single PriceSnapshot query with application-side groupBy (avoids N+1, appropriate for 2-10 items per shuffle)"
  - "Staleness direction: polled_at->diffInMinutes(now()) not now()->diffInMinutes(polled_at) — Carbon returns negative when comparing past to now"
  - "Calculator section uses data-calculator-section attribute for test assertSee/assertDontSee (avoids coupling tests to display text)"

patterns-established:
  - "Test helper buildShuffleScenario(): creates user + shuffle + CatalogItems + PriceSnapshots + steps in one call to reduce test boilerplate"
  - "Volt component cache invalidation: unset all derived computed properties when source data changes"

requirements-completed: [INTG-02, INTG-03, CALC-03, CALC-04]

# Metrics
duration: 4min
completed: 2026-03-05
---

# Phase 12 Plan 01: Batch Calculator Backend Summary

**Multi-step cascade profitPerUnit() with priceData()/calculatorSteps() computed properties for Alpine.js batch calculator**

## Performance

- **Duration:** 4 min
- **Started:** 2026-03-05T05:39:43Z
- **Completed:** 2026-03-05T05:43:43Z
- **Tasks:** 2
- **Files modified:** 3

## Accomplishments

- Refactored profitPerUnit() from naive last-step-only to proper multi-step cascade through floor(qty * output_min / input_qty) per step — shuffles list badge and calculator now always agree
- Added priceData() computed property that fetches latest PriceSnapshot per item with staleness flag (>60 min) via two queries (no N+1)
- Added calculatorSteps() computed property mapping steps to Alpine-friendly array with item names, icons, and yield fields
- Added calculator section placeholder in blade hidden when shuffle has no steps — ready for Plan 02 Alpine.js UI
- Added comprehensive 14-test suite covering cascade logic, staleness, null states, and component behavior

## Task Commits

1. **TDD RED: failing tests** - `8271af3` (test)
2. **Task 1: profitPerUnit() cascade refactor** - `dfd0ed0` (feat)
3. **Task 2: priceData() and calculatorSteps() computed properties** - `d9bb0df` (feat)

## Files Created/Modified

- `tests/Feature/ShuffleBatchCalculatorTest.php` - 14 tests covering profitPerUnit cascade, priceData staleness, calculatorSteps shape, and calculator section visibility
- `app/Models/Shuffle.php` - Refactored profitPerUnit() with multi-step cascade loop
- `resources/views/livewire/pages/shuffle-detail.blade.php` - Added PriceSnapshot import, priceData(), calculatorSteps() computed methods, cache unsets in step mutations, calculator section placeholder

## Decisions Made

- priceData() query pattern: single CatalogItem query + single PriceSnapshot query with application-side groupBy. Avoids N+1 with minimal overhead for typical 2-10 items per shuffle.
- Staleness direction fixed: use `$snapshot->polled_at->diffInMinutes(now())` — Carbon's `now()->diffInMinutes($past)` returns negative values for past dates which broke the >60 check.
- Calculator section uses `data-calculator-section` HTML attribute for test visibility assertions — decouples tests from display text which may change in Plan 02.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Fixed Carbon diffInMinutes direction for staleness check**
- **Found during:** Task 2 (priceData staleness test failing)
- **Issue:** `now()->diffInMinutes($snapshot->polled_at)` returns negative minutes for past dates, making stale=false for all snapshots
- **Fix:** Reversed to `$snapshot->polled_at->diffInMinutes(now())` which returns positive minutes elapsed since snapshot
- **Files modified:** resources/views/livewire/pages/shuffle-detail.blade.php
- **Verification:** `priceData sets stale true for snapshots older than 1 hour` test passes
- **Committed in:** d9bb0df (Task 2 commit)

---

**Total deviations:** 1 auto-fixed (Rule 1 - Bug)
**Impact on plan:** Essential correctness fix for staleness detection. No scope creep.

## Issues Encountered

None beyond the Carbon diffInMinutes direction bug (auto-fixed above).

## Next Phase Readiness

- priceData() and calculatorSteps() ready for Plan 02 Alpine.js calculator UI consumption
- Calculator section placeholder exists at correct location in blade template
- profitPerUnit() badge on shuffles list now uses proper cascade logic

## Self-Check: PASSED

- app/Models/Shuffle.php: FOUND
- resources/views/livewire/pages/shuffle-detail.blade.php: FOUND
- tests/Feature/ShuffleBatchCalculatorTest.php: FOUND
- Commit 8271af3 (TDD RED): FOUND
- Commit dfd0ed0 (profitPerUnit cascade): FOUND
- Commit d9bb0df (priceData/calculatorSteps): FOUND

---
*Phase: 12-batch-calculator-and-profit-summary*
*Completed: 2026-03-05*
