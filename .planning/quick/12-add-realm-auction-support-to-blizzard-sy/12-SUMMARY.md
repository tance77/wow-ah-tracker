---
phase: quick-12
plan: 01
subsystem: catalog-sync
tags: [blizzard-api, auction-house, boe-items, cli]
dependency_graph:
  requires: [blizzard-token-service, catalog-items]
  provides: [realm-auction-sync]
  affects: [sync-catalog-command]
tech_stack:
  patterns: [non-blocking-fetch, collection-merge-dedup]
key_files:
  modified:
    - app/Console/Commands/SyncCatalogCommand.php
    - config/services.php
decisions:
  - Connected realm ID 76 (Sargeras) as default, configurable via env
  - Direct HTTP json() for realm auctions (smaller payload vs commodities streaming)
  - Non-blocking realm fetch failure continues with commodities only
metrics:
  duration: "<1 min"
  completed: "2026-03-05"
  tasks_completed: 1
  tasks_total: 1
---

# Quick Task 12: Add Realm Auction Support to Blizzard Sync Catalog Summary

Connected-realm auction fetching via --realm flag on blizzard:sync-catalog, merging BoE item IDs with commodity IDs before the existing dedup/lookup pipeline. Default realm is Sargeras (ID 76).

## What Was Done

### Task 1: Add connected realm config and --realm flag with auction fetching (6e2149d)

- Added `connected_realm_id` config key to `config/services.php` with env override (`BLIZZARD_CONNECTED_REALM_ID`, default 76 for Sargeras)
- Added `--realm` option to command signature with description
- Updated command `$description` to mention realm auctions
- Added Step 1b block after commodity fetch: fetches `/data/wow/connected-realm/{id}/auctions` with `dynamic-{region}` namespace
- Realm item IDs extracted via `pluck('item.id')->unique()`, merged into `$uniqueIds` with dedup
- Outputs count of realm-unique items and total realm items
- Graceful degradation: if realm fetch fails, logs warning and continues with commodities only
- Memory cleanup via `unset($realmAuctions, $realmResponse)` after processing

## Deviations from Plan

None - plan executed exactly as written.

## Verification Results

- `php artisan blizzard:sync-catalog --help` shows --realm flag with description
- `grep connected_realm_id config/services.php` returns config line with default 76
- `grep "connected-realm" SyncCatalogCommand.php` returns API URL pattern
- Realm IDs merge into uniqueIds BEFORE Step 2 dedup filter
