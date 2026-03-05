---
phase: 12-batch-calculator-and-profit-summary
plan: "02"
subsystem: ui
tags: [alpine.js, livewire, blade, wow-ah, batch-calculator, profit-summary]

# Dependency graph
requires:
  - phase: 12-01
    provides: priceData() and calculatorSteps() computed properties, profitPerUnit() cascade logic

provides:
  - Alpine.js batch calculator UI island in shuffle-detail.blade.php
  - Cascading yield table showing per-step input/output quantities
  - Profit summary with Total Cost, Gross Value, AH Cut, Net Profit, Break-even price
  - Staleness warning banner for prices older than 1 hour
  - Missing price handling (shows "--" and suppresses profit calculation)
  - Calculator hidden guard when shuffle has no steps

affects:
  - any future phase touching shuffle-detail.blade.php
  - any UI or profitability display work

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Alpine.js inline component defined as function in Blade template (not separate JS file), consistent with step editor Alpine pattern"
    - "wire:ignore on Alpine calculator container prevents Livewire DOM morphing from resetting reactive batchQty state"
    - "@js() Blade directive passes PHP computed properties (priceData, calculatorSteps) into Alpine x-data as init data"
    - "Alpine computed getters (get cascade(), get canCalculate(), etc.) provide reactivity without server round-trips"

key-files:
  created:
    - tests/Feature/ShuffleBatchCalculatorTest.php
  modified:
    - resources/views/livewire/pages/shuffle-detail.blade.php

key-decisions:
  - "Alpine component defined inline in Blade (not external JS file) for consistency with step editor pattern"
  - "wire:ignore wraps calculator container to prevent Livewire morph from resetting batchQty input state"
  - "Cost = first step input only; Value = final step output x 0.95 AH cut (intermediate items not bought/sold)"
  - "Net Profit row is text-green-400 when >= 0, text-red-400 when < 0 per visual design decision"
  - "Break-even price shown in wow-gold color, Min column only (Max shows ---)"

patterns-established:
  - "Alpine island pattern: @js() passes PHP data, inline function defines component, wire:ignore prevents morph conflicts"
  - "Cascade calculation: floor(qty * output_qty_min / input_qty) per step applied to batchQty to get all downstream quantities"
  - "formatGold JS mirror: g=floor(c/10000), s=floor((c%10000)/100), c=c%100 with zero-suppression and negative support"

requirements-completed: [INTG-02, INTG-03, CALC-01, CALC-02]

# Metrics
duration: ~10min
completed: 2026-03-04
---

# Phase 12 Plan 02: Batch Calculator UI Summary

**Alpine.js batch calculator island with cascading yield table, five-row profit summary (Total Cost, Gross Value, AH Cut, Net Profit, Break-even), staleness warnings, and gold/silver/copper formatting — human-verified and approved**

## Performance

- **Duration:** ~10 min
- **Started:** 2026-03-04
- **Completed:** 2026-03-04
- **Tasks:** 2 (1 auto + 1 human-verify checkpoint)
- **Files modified:** 2

## Accomplishments
- Built complete Alpine.js batch calculator UI below the step editor in shuffle-detail.blade.php
- Implemented cascading yield calculation through all shuffle steps with reactive batchQty input
- Profit summary displays Total Cost, Gross Value, AH Cut (5%), Net Profit (green/red), and Break-even price
- Staleness warning banner appears when any item's price is older than 1 hour
- Missing prices show "--" and trigger "Cannot calculate" message in profit summary
- Calculator section hidden entirely when shuffle has no steps
- Human verification confirmed correct reactivity, WoW addon aesthetic, and data accuracy

## Task Commits

Each task was committed atomically:

1. **Task 1: Build Alpine.js batch calculator UI** - `bfb6a6a` (feat)
2. **Task 2: Verify batch calculator UI and reactivity** - Checkpoint approved (no code commit)

**Plan metadata:** (docs commit — this summary)

## Files Created/Modified
- `resources/views/livewire/pages/shuffle-detail.blade.php` - Added Alpine.js batch calculator island below step editor with staleness banner, input quantity field, step breakdown table, and profit summary
- `tests/Feature/ShuffleBatchCalculatorTest.php` - Feature tests for calculator rendering, hiding when no steps, Alpine binding, and priceData JSON embedding

## Decisions Made
- Alpine component defined inline in Blade (not external JS file) for consistency with step editor pattern
- wire:ignore wraps calculator container to prevent Livewire morph from resetting batchQty input state
- Cost = first step input only; Value = final step output x 0.95 AH cut (intermediate items not bought/sold)
- Net Profit row is text-green-400 when >= 0, text-red-400 when < 0
- Break-even price in wow-gold color, Min column only (Max shows ---)

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness
- Phase 12 is now complete — all batch calculator and profit summary features shipped
- Shuffles v1.1 milestone capstone feature is fully functional and human-verified
- No blockers for any future phases

---
*Phase: 12-batch-calculator-and-profit-summary*
*Completed: 2026-03-04*
