---
phase: quick-21
plan: 1
subsystem: ui
tags: [livewire, eloquent, shuffle, clone]

requires:
  - phase: none
    provides: n/a
provides:
  - Clone shuffle action with full step/byproduct duplication and auto-watch
affects: [shuffles]

tech-stack:
  added: []
  patterns: [Eloquent relationship replication with firstOrCreate auto-watch]

key-files:
  created: []
  modified:
    - resources/views/livewire/pages/shuffles.blade.php
    - tests/Feature/ShuffleCrudTest.php

key-decisions:
  - "Auto-watch items use clone's shuffle ID (created_by_shuffle_id) for proper orphan cleanup"

patterns-established:
  - "cloneShuffle pattern: load with byproducts, replicate steps, collect unique item IDs, firstOrCreate watched items"

requirements-completed: [QUICK-21]

duration: 1min
completed: 2026-03-05
---

# Quick 21: Clone Shuffle Summary

**Clone button on shuffles list that duplicates shuffle with all steps, byproducts, and auto-watched items**

## Performance

- **Duration:** 1 min
- **Started:** 2026-03-05T23:39:40Z
- **Completed:** 2026-03-05T23:40:58Z
- **Tasks:** 1 (TDD: RED + GREEN)
- **Files modified:** 2

## Accomplishments
- Clone button in Actions column (before Delete) with gold hover styling
- cloneShuffle() duplicates name with "(Copy)" suffix, all steps, all byproducts
- Auto-watches all input, output, and byproduct item IDs for the cloned shuffle
- Ownership isolation: cannot clone another user's shuffle (ModelNotFoundException)
- Redirects to new shuffle detail page after clone
- 6 new tests covering all clone behaviors

## Task Commits

Each task was committed atomically:

1. **Task 1 RED: Failing tests for clone** - `2fdbc8d` (test)
2. **Task 1 GREEN: Implement cloneShuffle + Clone button** - `0029b08` (feat)

## Files Created/Modified
- `resources/views/livewire/pages/shuffles.blade.php` - Added cloneShuffle() method and Clone button in Actions column
- `tests/Feature/ShuffleCrudTest.php` - Added 6 tests for clone behavior (name, steps, byproducts, auto-watch, ownership, redirect)

## Decisions Made
- Auto-watch items reference the clone's ID (not original's) via created_by_shuffle_id so orphan cleanup works correctly when deleting the clone

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered
None

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- Clone feature complete and tested
- No blockers

---
*Phase: quick-21*
*Completed: 2026-03-05*
