---
phase: 03-item-watchlist-management
plan: 01
subsystem: database
tags: [eloquent, laravel, migrations, seeders, catalog]

requires:
  - phase: 01-project-foundation
    provides: WatchedItem and PriceSnapshot models with schema
  - phase: 02-authentication
    provides: User model (Authenticatable base)

provides:
  - CatalogItem Eloquent model for static TWW item catalog
  - catalog_items migration with unique blizzard_item_id constraint
  - ItemCatalogSeeder with 19 TWW-era crafting materials across 6 categories
  - User.watchedItems() HasMany relationship to WatchedItem
  - Unique composite index on (user_id, blizzard_item_id) in watched_items

affects:
  - 03-item-watchlist-management (Plan 02 — watchlist UI needs catalog search and User relationship)
  - 04-blizzard-api-integration (verify item IDs against live API)

tech-stack:
  added: []
  patterns:
    - "CatalogItem::updateOrCreate() on blizzard_item_id for idempotent seeding"
    - "Unique composite index enforced at DB level to prevent duplicate watched items"

key-files:
  created:
    - app/Models/CatalogItem.php
    - database/migrations/2026_03_01_200001_create_catalog_items_table.php
    - database/migrations/2026_03_01_200002_add_unique_user_blizzard_item_to_watched_items.php
    - database/seeders/ItemCatalogSeeder.php
  modified:
    - app/Models/User.php
    - database/seeders/DatabaseSeeder.php

key-decisions:
  - "19 TWW-era item IDs are placeholders — must verify against live Blizzard API in Phase 4"
  - "updateOrCreate() on blizzard_item_id keeps seeder idempotent across re-seeds"
  - "Unique constraint on (user_id, blizzard_item_id) enforced at DB level, not just application level"

patterns-established:
  - "All seeders use updateOrCreate() for idempotency"
  - "declare(strict_types=1) on all new PHP files"
  - "Database-level unique constraints for critical business rules (no duplicates)"

requirements-completed: [ITEM-01, ITEM-05]

duration: 4min
completed: 2026-03-01
---

# Phase 3 Plan 1: Data Layer — CatalogItem Model, Seeder, and User Relationship Summary

**Eloquent CatalogItem model with 19 TWW crafting materials seeded via ItemCatalogSeeder, User.watchedItems() HasMany relationship, and unique (user_id, blizzard_item_id) DB constraint**

## Performance

- **Duration:** ~4 min
- **Started:** 2026-03-01T20:17:57Z
- **Completed:** 2026-03-01T20:21:30Z
- **Tasks:** 2
- **Files modified:** 6

## Accomplishments

- CatalogItem model and migration for static TWW crafting material catalog with unique blizzard_item_id
- ItemCatalogSeeder populates 19 TWW-era items across 6 categories (herb, ore, cloth, leather, enchanting, gem)
- User model gains watchedItems() HasMany relationship completing the inverse of WatchedItem.user()
- Unique composite index on (user_id, blizzard_item_id) prevents duplicate watched items at the database level

## Task Commits

1. **Task 1: Create CatalogItem model, migration, and seeder with TWW crafting materials** - `3b66c67` (feat)
2. **Task 2: Add watchedItems relationship to User model and unique constraint on watched_items** - `2f1ce12` (feat)

## Files Created/Modified

- `app/Models/CatalogItem.php` - Eloquent model with blizzard_item_id, name, category; HasFactory; fillable + casts
- `database/migrations/2026_03_01_200001_create_catalog_items_table.php` - catalog_items schema with unique blizzard_item_id
- `database/migrations/2026_03_01_200002_add_unique_user_blizzard_item_to_watched_items.php` - Unique composite index on (user_id, blizzard_item_id)
- `database/seeders/ItemCatalogSeeder.php` - 19 TWW-era crafting materials using updateOrCreate() for idempotency
- `app/Models/User.php` - Added watchedItems() HasMany relationship and HasMany import
- `database/seeders/DatabaseSeeder.php` - Added ItemCatalogSeeder::class call before WatchedItem factory

## Decisions Made

- 19 items seeded (plan said ~20): all items from RESEARCH.md were included; exact count is 19
- Item IDs are TWW-era placeholders that must be verified against live Blizzard API in Phase 4
- updateOrCreate() on blizzard_item_id ensures re-running seeder is safe

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- CatalogItem is searchable via `CatalogItem::where('name', 'like', '%query%')` — ready for watchlist UI search
- User::watchedItems() scopes queries to the authenticated user — ready for dashboard listing
- Unique constraint on watched_items prevents duplicates — watchlist controller can handle the violation gracefully
- Plan 02 (watchlist UI) can proceed: all data-layer prerequisites are in place

## Self-Check: PASSED

All created files verified on disk. Both task commits (3b66c67, 2f1ce12) confirmed in git log.

---
*Phase: 03-item-watchlist-management*
*Completed: 2026-03-01*
