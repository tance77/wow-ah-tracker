---
phase: quick-15
plan: 01
subsystem: data-ingestion
tags: [realm-auctions, price-polling, boe-items, jobs]
dependency_graph:
  requires: [blizzard-token-service, catalog-items, price-snapshots, connected-realm-config]
  provides: [realm-price-pipeline, realm-auction-polling]
  affects: [ingestion-metadata, schedule]
tech_stack:
  added: []
  patterns: [brace-depth-json-parsing, parallel-pipeline-with-separate-gates]
key_files:
  created:
    - app/Actions/RealmPriceFetchAction.php
    - app/Actions/ExtractRealmListingsAction.php
    - app/Jobs/FetchRealmAuctionDataJob.php
    - app/Jobs/DispatchRealmPriceBatchesJob.php
    - app/Jobs/AggregateRealmPriceBatchJob.php
    - database/migrations/2026_03_05_000001_add_realm_columns_to_ingestion_metadata.php
    - tests/Feature/DataIngestion/ExtractRealmListingsActionTest.php
  modified:
    - app/Models/IngestionMetadata.php
    - routes/console.php
decisions:
  - Used brace-depth counting instead of regex for realm auction JSON parsing (nested item objects with bonus_list/modifiers break simple regex)
  - Realm pipeline uses separate gate columns (realm_last_modified_at, realm_response_hash, etc.) to avoid interfering with commodity pipeline
  - Reused existing PriceAggregateAction by mapping buyout to unit_price in ExtractRealmListingsAction output
  - Used hourlyAt(30) instead of hourly()->at('30') which is invalid Laravel syntax
metrics:
  duration: ~4 min
  completed: 2026-03-05
  tasks: 2/2
  files_created: 7
  files_modified: 2
---

# Quick Task 15: Add Hourly Realm Auction Price Polling Summary

Parallel realm auction pipeline for BoE items using brace-depth JSON parsing, reusing PriceAggregateAction with buyout-to-unit_price mapping, scheduled at :30 offset from commodity polling.

## What Was Built

### Realm Auction Pipeline (mirrors commodity pipeline)

1. **RealmPriceFetchAction** - Downloads realm auction JSON from `connected-realm/{id}/auctions` endpoint to temp storage, same streaming sink pattern as PriceFetchAction.

2. **ExtractRealmListingsAction** - Stream-parses realm auction JSON using brace-depth counting (not regex) to handle nested item objects with `bonus_list`, `modifiers`, and `context` fields. Skips bid-only auctions (buyout=0). Output shape matches ExtractListingsAction (`unit_price => buyout, quantity => quantity`).

3. **FetchRealmAuctionDataJob** - Hourly job with `ShouldBeUnique` lock. Uses realm-specific gate columns (`realm_last_modified_at`, `realm_response_hash`) to skip unchanged data.

4. **DispatchRealmPriceBatchesJob** - Chunks catalog items into 50-item batches of `AggregateRealmPriceBatchJob`. Updates realm metadata on completion.

5. **AggregateRealmPriceBatchJob** - Extracts realm listings and writes PriceSnapshot rows using the existing `PriceAggregateAction`.

### Database Changes

Migration adds 4 columns to `ingestion_metadata`: `realm_last_modified_at`, `realm_response_hash`, `realm_last_fetched_at`, `realm_consecutive_failures` — completely independent from commodity gate columns.

### Schedule

- `FetchCommodityDataJob` runs at :00 (unchanged)
- `FetchRealmAuctionDataJob` runs at :30 (new)

## Tests

5 Pest tests for `ExtractRealmListingsAction`:
- Extracts buyout prices for matching item IDs
- Skips bid-only auctions (buyout = 0)
- Skips items not in catalog set
- Handles items with bonus_list and modifiers in item object
- Groups multiple auctions for the same item

All 179 tests pass (existing + new).

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Fixed brace-depth parsing for wrapped JSON**
- **Found during:** Task 1 test verification
- **Issue:** Auction objects are wrapped in `{"auctions":[...]}`, so top-level brace depth 0 captures the entire root object instead of individual auctions
- **Fix:** Changed capture depth to look for objects at brace depth 2 (depth 1 = root object, depth 2 = auction objects inside array)
- **Files modified:** app/Actions/ExtractRealmListingsAction.php
- **Commit:** 8a05a8e

**2. [Rule 1 - Bug] Fixed invalid schedule syntax**
- **Found during:** Task 2 schedule verification
- **Issue:** `->hourly()->at('30')` throws InvalidArgumentException — `at()` expects hour:minute for daily schedules, not minutes offset
- **Fix:** Changed to `->hourlyAt(30)` which correctly sets cron to `30 * * * *`
- **Files modified:** routes/console.php
- **Commit:** 267682f

## Commits

| # | Hash | Message |
|---|------|---------|
| 1 | 8a05a8e | feat(quick-15): add migration, RealmPriceFetchAction, and ExtractRealmListingsAction |
| 2 | 267682f | feat(quick-15): add realm auction jobs and hourly schedule |

## Self-Check: PASSED

All 8 files found. Both commits verified.
