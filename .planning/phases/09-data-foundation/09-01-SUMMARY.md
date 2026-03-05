---
phase: 09-data-foundation
plan: 01
subsystem: database
tags: [laravel, eloquent, migrations, shuffles, cascade-delete, orphan-cleanup]

# Dependency graph
requires: []
provides:
  - shuffles table (id, user_id FK cascadeOnDelete, name, timestamps)
  - shuffle_steps table (id, shuffle_id FK cascadeOnDelete, unsignedBigInteger blizzard item IDs, integer qty min/max, sort_order, composite index)
  - watched_items.created_by_shuffle_id nullable FK (nullOnDelete) for auto-watch provenance
  - Shuffle Eloquent model with user(), steps() (ordered by sort_order), and deleting orphan cleanup event
  - ShuffleStep Eloquent model with shuffle(), inputCatalogItem(), outputCatalogItem() relationships
  - User model updated with shuffles() HasMany
  - WatchedItem model updated with created_by_shuffle_id fillable and createdByShuffle() BelongsTo
affects: [10-shuffle-crud, 11-shuffle-ui, 12-shuffle-dashboard]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Cascade delete for shuffle_steps at DB level via cascadeOnDelete()"
    - "Orphan cleanup for auto-watched items via Shuffle::boot() deleting model event (fires before DB cascade)"
    - "nullOnDelete() on watched_items.created_by_shuffle_id to preserve watched items when shuffle deleted"
    - "unsignedBigInteger for all Blizzard item IDs (project convention)"
    - "unsignedInteger for quantity fields (integer, not float)"
    - "steps() relationship orders by sort_order on the HasMany itself, not in controller"

key-files:
  created:
    - database/migrations/2026_03_05_100000_create_shuffles_table.php
    - database/migrations/2026_03_05_100001_create_shuffle_steps_table.php
    - database/migrations/2026_03_05_100002_add_created_by_shuffle_id_to_watched_items.php
    - app/Models/Shuffle.php
    - app/Models/ShuffleStep.php
  modified:
    - app/Models/User.php
    - app/Models/WatchedItem.php

key-decisions:
  - "Orphan cleanup on shuffle delete uses deleting model event (before delete), not deleted, so steps still exist in DB during check"
  - "nullOnDelete() on watched_items FK preserves watched items after shuffle delete; model event handles actual row deletion for true orphans"
  - "Blizzard item ID columns on shuffle_steps are unsignedBigInteger per project convention for all Blizzard IDs"
  - "Yield columns are integer (unsignedInteger), not float — locked decision from CONTEXT.md"
  - "Composite index (shuffle_id, sort_order) added to shuffle_steps for future-proof ordering queries"

patterns-established:
  - "Pattern: boot() static method with static::deleting() closure for pre-delete model events"
  - "Pattern: HasMany relationship with ->orderBy() applied on relationship definition, not in controller"
  - "Pattern: BelongsTo with custom FK to blizzard_item_id via belongsTo(CatalogItem::class, 'input_blizzard_item_id', 'blizzard_item_id')"

requirements-completed: []

# Metrics
duration: 1min
completed: 2026-03-05
---

# Phase 9 Plan 01: Data Foundation Summary

**Shuffles schema foundation: 3 migrations, 2 new Eloquent models (Shuffle, ShuffleStep), 2 updated models (User, WatchedItem) with cascade-delete and conditional orphan cleanup**

## Performance

- **Duration:** ~1 min
- **Started:** 2026-03-05T03:46:28Z
- **Completed:** 2026-03-05T03:47:36Z
- **Tasks:** 2
- **Files modified:** 7

## Accomplishments
- Three migrations create shuffles table, shuffle_steps table, and watched_items provenance FK — all ran cleanly
- Shuffle model establishes ordered steps() relationship and pre-delete orphan cleanup via boot() deleting event
- ShuffleStep model mirrors WatchedItem::catalogItem() pattern for both input and output item relationships
- User and WatchedItem models extended with shuffle relationships

## Task Commits

Each task was committed atomically:

1. **Task 1: Create migrations** - `c857da2` (feat)
2. **Task 2: Create Shuffle and ShuffleStep models, update User and WatchedItem** - `7dc87af` (feat)

**Plan metadata:** (docs commit pending)

## Files Created/Modified
- `database/migrations/2026_03_05_100000_create_shuffles_table.php` - shuffles table with user_id FK
- `database/migrations/2026_03_05_100001_create_shuffle_steps_table.php` - shuffle_steps with qty columns, composite index
- `database/migrations/2026_03_05_100002_add_created_by_shuffle_id_to_watched_items.php` - nullable provenance FK
- `app/Models/Shuffle.php` - new model with user(), steps() (ordered), and orphan cleanup deleting event
- `app/Models/ShuffleStep.php` - new model with shuffle(), inputCatalogItem(), outputCatalogItem()
- `app/Models/User.php` - added shuffles() HasMany
- `app/Models/WatchedItem.php` - added created_by_shuffle_id to fillable, added createdByShuffle() BelongsTo

## Decisions Made
- Used `deleting` model event (not `deleted`) so shuffle steps still exist in DB when orphan check fires — this is the correct event order for conditional cleanup
- `nullOnDelete()` on the watched_items FK intentionally preserves watched items while model event handles deletion of true orphans
- No factories created in this plan — factories were in the research scope but are not in this plan's task list; deferred to later phase

## Deviations from Plan

None — plan executed exactly as written.

## Issues Encountered
None.

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- Data foundation complete: shuffles and shuffle_steps tables exist, all Eloquent models and relationships wired
- Phase 10 (Shuffle CRUD) can proceed immediately — models are fillable, relationships resolve, cascade delete is DB-enforced
- No blockers

---
*Phase: 09-data-foundation*
*Completed: 2026-03-05*
