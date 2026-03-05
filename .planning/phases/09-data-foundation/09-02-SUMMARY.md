---
phase: 09-data-foundation
plan: 02
subsystem: database
tags: [laravel, pest, factories, tdd, shuffles, cascade-delete, orphan-cleanup]

# Dependency graph
requires:
  - shuffles table (09-01)
  - shuffle_steps table (09-01)
  - Shuffle model with deleting event (09-01)
  - ShuffleStep model (09-01)
provides:
  - ShuffleFactory for test seeding
  - ShuffleStepFactory for test seeding
  - Comprehensive Pest test suite covering all Phase 9 success criteria
affects: [10-shuffle-crud, 11-shuffle-ui]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "TDD: write tests first (factories absent = red), create factories (green)"
    - "Factory pattern mirrors WatchedItemFactory: model class, faker for names, related factory for FK"
    - "ShuffleStepFactory uses Shuffle::factory() to auto-create parent when not provided"
    - "Tests group via uses()->group('shuffle-data-foundation') for targeted runs"

key-files:
  created:
    - database/factories/ShuffleFactory.php
    - database/factories/ShuffleStepFactory.php
    - tests/Feature/ShuffleDataFoundationTest.php
  modified:
    - app/Models/Shuffle.php

key-decisions:
  - "Orphan cleanup subquery must use 'wi2.id' not bare 'id' — SQLite rejects ambiguous column when multiple joined tables have id column"
  - "ShuffleStepFactory uses random blizzard IDs (100000-300000) for default definition; tests that need specific IDs override explicitly"
  - "Tests use fixed blizzard_item_id values (155001, 166001, 177001) to avoid collision across test scenarios"

patterns-established:
  - "Pattern: Factory with parent factory FK — ShuffleStepFactory auto-creates Shuffle via Shuffle::factory()"
  - "Pattern: Override specific factory fields in test via create(['field' => value])"
  - "Pattern: Orphan cleanup tests create full scenario (user, shuffle, steps, watched items) then assert post-delete state"

requirements-completed: []

# Metrics
duration: 2min
completed: 2026-03-05
---

# Phase 9 Plan 02: Data Foundation Summary

**Shuffle test factories and 10-test Pest suite: ShuffleFactory, ShuffleStepFactory, full cascade/orphan/relationship coverage; fixed ambiguous column bug in orphan cleanup query**

## Performance

- **Duration:** ~2 min
- **Started:** 2026-03-05T03:49:44Z
- **Completed:** 2026-03-05T03:51:45Z
- **Tasks:** 1
- **Files modified:** 4

## Accomplishments
- ShuffleFactory and ShuffleStepFactory created following WatchedItemFactory pattern
- 10-test Pest suite covers all 4 Phase 9 success criteria: migrations (implicit via RefreshDatabase), relationships, factory seeding, cascade/orphan logic
- TDD RED→GREEN flow confirmed: 10 tests failed before factories, 10 passed after
- Full suite ran clean (124 tests, 303 assertions, no regressions)

## Task Commits

Each task was committed atomically (TDD approach: RED commit first, then GREEN commit):

1. **RED — Failing tests** - `88b0cc7` (test) — 10 tests written, all failing (factories missing)
2. **GREEN — Factories + bug fix** - `342c6cc` (feat) — factories created, ambiguous column fixed, all 10 tests pass

## Files Created/Modified
- `database/factories/ShuffleFactory.php` — factory for Shuffle model with User::factory() for user_id
- `database/factories/ShuffleStepFactory.php` — factory for ShuffleStep model with Shuffle::factory() for shuffle_id
- `tests/Feature/ShuffleDataFoundationTest.php` — 10 Pest tests covering all Phase 9 success criteria
- `app/Models/Shuffle.php` — fixed ambiguous `id` column in orphan cleanup subquery (auto-fix Rule 1)

## Decisions Made
- ShuffleStepFactory uses explicit `shuffle_id => Shuffle::factory()` so calling `ShuffleStep::factory()->create()` works standalone without providing a parent shuffle
- Fixed `select 'id'` to `select 'wi2.id'` in Shuffle orphan cleanup subquery — SQLite strict about column ambiguity when joining watched_items (alias wi2), shuffle_steps, and shuffles (all have id columns)

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Fixed ambiguous column in Shuffle orphan cleanup subquery**
- **Found during:** Task 1 GREEN phase (tests failed with QueryException)
- **Issue:** `select 'id'` in the NOT EXISTS subquery is ambiguous when joined tables (watched_items wi2, shuffle_steps ss, shuffles) all have an `id` column. SQLite raises `General error: 1 ambiguous column name: id`.
- **Fix:** Changed `$query->select('id')` to `$query->select('wi2.id')` in `app/Models/Shuffle.php` boot() deleting closure
- **Files modified:** `app/Models/Shuffle.php`
- **Commit:** `342c6cc`

## Issues Encountered
- Ambiguous column name SQLite error in orphan cleanup subquery — auto-fixed inline per Rule 1

## User Setup Required
None.

## Next Phase Readiness
- Phase 9 data foundation fully proven: all 10 tests green, full suite clean
- Phase 10 (Shuffle CRUD) can proceed immediately — factories enable clean test seeding for CRUD endpoints
- No blockers

---
*Phase: 09-data-foundation*
*Completed: 2026-03-05*

## Self-Check: PASSED

- FOUND: database/factories/ShuffleFactory.php
- FOUND: database/factories/ShuffleStepFactory.php
- FOUND: tests/Feature/ShuffleDataFoundationTest.php
- FOUND: .planning/phases/09-data-foundation/09-02-SUMMARY.md
- FOUND: commit 88b0cc7 (test RED)
- FOUND: commit 342c6cc (feat GREEN + bug fix)
