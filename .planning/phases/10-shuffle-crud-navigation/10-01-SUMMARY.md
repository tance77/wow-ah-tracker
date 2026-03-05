---
phase: 10-shuffle-crud-navigation
plan: 01
subsystem: ui
tags: [livewire, volt, blade, tailwind, shuffles, crud]

# Dependency graph
requires:
  - phase: 09-data-foundation
    provides: Shuffle and ShuffleStep models, factories, and database schema
provides:
  - /shuffles and /shuffles/{shuffle} Volt routes with auth middleware
  - Shuffles nav link in desktop and mobile navigation
  - Shuffles list page with create, inline-rename, delete-with-modal, and profitability badge
  - profitPerUnit() method on Shuffle model (first-in/last-out, 5% AH cut)
  - Shuffle detail page shell (placeholder for Phase 11 step editor)
  - ShuffleCrudTest feature tests (8 tests covering SHUF-01, SHUF-03, SHUF-04, SHUF-05)
affects:
  - 10-02 (shuffle detail page, if exists)
  - phase 11 (step editor builds on shuffle-detail shell)
  - phase 12 (batch calculator extends profitPerUnit logic)

# Tech tracking
tech-stack:
  added: []
  patterns:
    - Volt SFC with #[Computed] shuffles() scoped to auth()->user()->shuffles()
    - Alpine x-data inline-edit pattern for rename (click to edit, Enter/blur saves)
    - Profitability badge as inline span with color dot (green/red/gray) + formatGold()
    - Delete confirmation via x-modal component with dispatch('open-modal', name) trigger

key-files:
  created:
    - resources/views/livewire/pages/shuffles.blade.php
    - resources/views/livewire/pages/shuffle-detail.blade.php
    - tests/Feature/ShuffleCrudTest.php
  modified:
    - routes/web.php
    - resources/views/livewire/layout/navigation.blade.php
    - app/Models/Shuffle.php

key-decisions:
  - "profitPerUnit() uses first-step input / last-step output (naive) for Phase 10 badge display; Phase 12 will refine for multi-step chains"
  - "Shuffle detail page is a shell (auth guard + back link + placeholder) — step editor is Phase 11"
  - "User isolation enforced via auth()->user()->shuffles()->findOrFail() — throws ModelNotFoundException on cross-user access"

patterns-established:
  - "Shuffles CRUD: all mutations scoped to auth()->user()->shuffles() relationship query"
  - "Inline rename: Alpine x-data with editing flag, $wire.renameShuffle on Enter/blur"

requirements-completed: [SHUF-01, SHUF-03, SHUF-04, SHUF-05]

# Metrics
duration: 2min
completed: 2026-03-05
---

# Phase 10 Plan 01: Shuffle CRUD and Navigation Summary

**Shuffles list page with inline CRUD, profitability badges, navigation links, and 8 feature tests covering all shuffle management requirements**

## Performance

- **Duration:** 2 min
- **Started:** 2026-03-05T04:17:16Z
- **Completed:** 2026-03-05T04:19:10Z
- **Tasks:** 3
- **Files modified:** 6

## Accomplishments
- Added /shuffles and /shuffles/{shuffle} routes plus Shuffles nav link in desktop and mobile menus
- Created shuffles list Volt SFC with create, inline-rename, delete-with-confirmation-modal, and profitability badges
- Added profitPerUnit(): ?int to Shuffle model (first-in/last-out calculation with 5% AH cut)
- Created shuffle-detail shell page (auth guard, back link, Phase 11 placeholder)
- All 8 ShuffleCrudTest tests pass; full suite (132 tests) green with no regressions

## Task Commits

Each task was committed atomically:

1. **Task 1: Add routes, navigation links, and profitPerUnit() model method** - `b4c0404` (feat)
2. **Task 2: Create shuffles list page (Volt SFC)** - `20144ff` (feat)
3. **Task 3: Create feature tests for shuffle CRUD** - `021ef14` (test)

## Files Created/Modified
- `routes/web.php` - Added /shuffles and /shuffles/{shuffle} Volt routes with auth middleware
- `resources/views/livewire/layout/navigation.blade.php` - Added Shuffles link to desktop and mobile nav
- `app/Models/Shuffle.php` - Added profitPerUnit(): ?int for badge calculation
- `resources/views/livewire/pages/shuffles.blade.php` - Shuffles list with CRUD, badges, empty state
- `resources/views/livewire/pages/shuffle-detail.blade.php` - Shell detail page with auth guard and back link
- `tests/Feature/ShuffleCrudTest.php` - 8 feature tests for auth, CRUD, user isolation

## Decisions Made
- `profitPerUnit()` uses naive first-in/last-out calculation (first step input price → last step output price × min qty × 0.95). Phase 12 batch calculator will refine this for multi-step chains.
- Shuffle detail page is a shell only in this phase — step editor ships in Phase 11.
- User isolation enforced via scoped relationship query (`auth()->user()->shuffles()->findOrFail()`), consistent with watchlist pattern.

## Deviations from Plan

None — plan executed exactly as written.

## Issues Encountered
None.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness
- Shuffles list page and navigation fully functional
- Shuffle detail shell ready for Phase 11 step editor implementation
- profitPerUnit() method ready to power badges on detail page and batch calculator in Phase 12
- No blockers

---
*Phase: 10-shuffle-crud-navigation*
*Completed: 2026-03-05*
