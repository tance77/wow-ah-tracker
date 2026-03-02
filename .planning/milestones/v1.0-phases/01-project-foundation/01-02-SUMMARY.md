---
phase: 01-project-foundation
plan: "02"
subsystem: database
tags: [laravel, eloquent, migrations, sqlite, factories, seeders]

# Dependency graph
requires:
  - phase: 01-01
    provides: Laravel 12 skeleton with PHP 8.4, Pint strict-types enforcement, Pest test suite
provides:
  - watched_items table: id, nullable user_id FK, blizzard_item_id, name, buy_threshold, sell_threshold
  - price_snapshots table: id, watched_item_id FK (cascade delete), min/avg/median_price + total_volume as BIGINT UNSIGNED, polled_at timestamp
  - Composite index on (watched_item_id, polled_at) for efficient time-range queries
  - WatchedItem Eloquent model with hasMany(PriceSnapshot) and belongsTo(User) relationships
  - PriceSnapshot Eloquent model with belongsTo(WatchedItem) relationship
  - WatchedItemFactory and PriceSnapshotFactory with realistic copper-denominated prices
  - DatabaseSeeder populating 5 watched items with 20 snapshots each (100 total rows)
affects:
  - 01-03 (Blizzard token service test — uses PriceSnapshot/WatchedItem factories)
  - 02-auth (user_id FK already nullable in watched_items, no migration change needed)
  - 04-api-integration (ingestion job writes to price_snapshots)
  - 05-price-display (reads from watched_items + price_snapshots via relationships)

# Tech tracking
tech-stack:
  added: []
  patterns:
    - BIGINT UNSIGNED for all copper price columns (min_price, avg_price, median_price, total_volume)
    - timestamp() for polled_at — never store Unix integers for time
    - Composite index on (watched_item_id, polled_at) for time-series query performance
    - Nullable user_id FK on watched_items for zero-migration Phase 2 auth addition
    - Hard delete only — no SoftDeletes on either model

key-files:
  created:
    - database/migrations/2026_03_01_192521_create_watched_items_table.php
    - database/migrations/2026_03_01_192522_create_price_snapshots_table.php
    - app/Models/WatchedItem.php
    - app/Models/PriceSnapshot.php
    - database/factories/WatchedItemFactory.php
    - database/factories/PriceSnapshotFactory.php
  modified:
    - database/seeders/DatabaseSeeder.php

key-decisions:
  - "All price columns are BIGINT UNSIGNED (copper denomination) — float/decimal irrecoverable after data is written"
  - "Composite index on (watched_item_id, polled_at) in migration — cannot be added efficiently after data accumulates"
  - "nullable user_id FK on watched_items from day one — avoids Phase 2 schema migration"
  - "Hard delete only — no SoftDeletes on WatchedItem or PriceSnapshot (per prior user decision)"
  - "Migration timestamps set sequentially (192521/192522) to guarantee watched_items runs before price_snapshots"

patterns-established:
  - "Pattern: Copper denomination — all WoW prices stored as BIGINT UNSIGNED copper values, never float/decimal"
  - "Pattern: Timestamp column for polled_at — polled_at uses timestamp() column type, never unsignedInteger Unix"
  - "Pattern: Composite index in create callback — performance-critical indexes added at table creation, not post-hoc"

requirements-completed: ["DATA-02", "DATA-03"]

# Metrics
duration: 2min
completed: 2026-03-01
---

# Phase 1 Plan 02: Database Schema Summary

**watched_items and price_snapshots migrations with BIGINT UNSIGNED copper prices, composite index on (watched_item_id, polled_at), Eloquent models with relationships, and factories seeding 100 realistic rows**

## Performance

- **Duration:** 2 min
- **Started:** 2026-03-01T19:25:17Z
- **Completed:** 2026-03-01T19:27:05Z
- **Tasks:** 2 completed
- **Files modified:** 7

## Accomplishments
- Two migrations create watched_items and price_snapshots with correct column types — BIGINT UNSIGNED for all price columns, timestamp for polled_at, cascade delete on FK
- Composite index on (watched_item_id, polled_at) embedded in create migration for efficient time-range price queries from day one
- WatchedItem and PriceSnapshot Eloquent models with full relationship chain: User -> WatchedItem -> PriceSnapshot
- Factories generate realistic copper-denominated prices (10k-500k copper avg, min at 85%, median at 95%) for test data
- DatabaseSeeder populates 5 watched items x 20 snapshots = 100 rows; `migrate:fresh --seed` runs without errors

## Task Commits

Each task was committed atomically:

1. **Task 1: Create watched_items and price_snapshots migrations** - `3833301` (feat)
2. **Task 2: Create Eloquent models, factories, and DatabaseSeeder** - `55803d8` (feat)

**Plan metadata:** (docs commit — see below)

## Files Created/Modified
- `database/migrations/2026_03_01_192521_create_watched_items_table.php` - watched_items schema: nullable user_id FK, blizzard_item_id, name, buy/sell thresholds
- `database/migrations/2026_03_01_192522_create_price_snapshots_table.php` - price_snapshots schema: BIGINT UNSIGNED prices, timestamp polled_at, composite index
- `app/Models/WatchedItem.php` - Eloquent model with fillable, integer casts, hasMany(PriceSnapshot), belongsTo(User)
- `app/Models/PriceSnapshot.php` - Eloquent model with fillable, integer/datetime casts, belongsTo(WatchedItem)
- `database/factories/WatchedItemFactory.php` - Generates items with realistic blizzard_item_id (100-200000) and 5-25% thresholds
- `database/factories/PriceSnapshotFactory.php` - Generates copper prices (10k-500k avg) with realistic min/median relationships
- `database/seeders/DatabaseSeeder.php` - Creates 5 watched items with 20 snapshots each via factory relationships

## Decisions Made
- **Migration ordering**: Both migrations generated at same second (192521); price_snapshots renamed to 192522 so watched_items always runs first — required for FK constraint on price_snapshots.watched_item_id
- **Hard delete only**: No SoftDeletes on either model per prior user decision — keeps queries simple and avoids globally-scoped deleted_at filter overhead
- **Nullable user_id from day one**: FK wired now so Phase 2 auth needs only to populate the column, no ALTER TABLE migration required

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] Renamed price_snapshots migration to ensure correct run order**
- **Found during:** Task 1 (migration verification)
- **Issue:** Both migrations generated with identical timestamp (192521); alphabetical sort caused price_snapshots to run before watched_items, violating FK constraint
- **Fix:** Renamed `2026_03_01_192521_create_price_snapshots_table.php` to `2026_03_01_192522_create_price_snapshots_table.php` so watched_items runs first
- **Files modified:** database/migrations/ (file rename)
- **Verification:** `php artisan migrate:fresh` shows watched_items before price_snapshots in output; no FK errors
- **Committed in:** 3833301 (Task 1 commit)

---

**Total deviations:** 1 auto-fixed (1 blocking)
**Impact on plan:** Necessary fix for correct FK constraint ordering. No scope creep.

## Issues Encountered
- SQLite defers FK constraint checking by default, so the initial wrong-order migration succeeded locally. Fixed proactively before this could silently fail on MySQL/PostgreSQL in production.

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- Schema in place: both tables ready for reads/writes
- Models and relationships operational: WatchedItem::first()->priceSnapshots()->count() returns 20
- Factories ready for Pest feature tests in Phase 1 Plan 03 and beyond
- nullable user_id FK on watched_items ready for Phase 2 auth — no schema change needed
- Ready to proceed to Phase 1 Plan 03 (Blizzard token service)

---
*Phase: 01-project-foundation*
*Completed: 2026-03-01*
