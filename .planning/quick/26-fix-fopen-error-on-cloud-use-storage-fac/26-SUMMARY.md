---
phase: quick-26
plan: 01
subsystem: data-ingestion
tags: [storage, cloud, laravel-cloud, fix]
dependency_graph:
  requires: []
  provides: [cloud-compatible-price-pipeline]
  affects: [price-ingestion, commodity-fetching, realm-auction-fetching]
tech_stack:
  patterns: [storage-facade-keys, default-disk-for-cloud-flexibility]
key_files:
  modified:
    - app/Actions/PriceFetchAction.php
    - app/Actions/RealmPriceFetchAction.php
    - app/Actions/ExtractListingsAction.php
    - app/Actions/ExtractRealmListingsAction.php
    - app/Jobs/FetchCommodityDataJob.php
    - app/Jobs/FetchRealmAuctionDataJob.php
    - app/Jobs/DispatchPriceBatchesJob.php
    - app/Jobs/DispatchRealmPriceBatchesJob.php
    - app/Jobs/AggregatePriceBatchJob.php
    - app/Jobs/AggregateRealmPriceBatchJob.php
    - tests/Feature/BlizzardApi/PriceFetchActionTest.php
    - tests/Feature/DataIngestion/FetchCommodityPricesJobTest.php
    - tests/Feature/DataIngestion/ExtractRealmListingsActionTest.php
    - tests/Feature/DataIngestion/DeduplicationTest.php
decisions:
  - Use Storage::disk() (default) instead of Storage::disk('local') so Cloud users can set FILESYSTEM_DISK=s3
  - Keep tempnam+sink pattern for initial HTTP download since md5 hash must be computed before Storage::put
metrics:
  duration: 6 min
  completed: 2026-03-06
---

# Quick Task 26: Fix fopen Error on Cloud -- Use Storage Facade

Storage keys replace absolute file paths throughout the 10-file price ingestion pipeline so queue workers on separate Laravel Cloud containers can access shared files via configurable disk (local or s3).

## What Changed

### Task 1: Production Files (10 files)

**Fetch Actions** (PriceFetchAction, RealmPriceFetchAction):
- Return `storageKey` instead of `tempFilePath` in result array
- Use `Storage::disk()` (default) instead of `Storage::disk('local')` for cloud flexibility
- Log storage key instead of absolute path

**Fetch Jobs** (FetchCommodityDataJob, FetchRealmAuctionDataJob):
- Gate-skip cleanup uses `Storage::delete()` instead of `@unlink()`
- Pass `storageKey` to dispatch jobs

**Dispatch Jobs** (DispatchPriceBatchesJob, DispatchRealmPriceBatchesJob):
- Constructor property renamed `$filePath` to `$storageKey`
- Batch then/catch callbacks use `Storage::delete()` for cleanup
- Pass `storageKey` to aggregate batch jobs

**Aggregate Jobs** (AggregatePriceBatchJob, AggregateRealmPriceBatchJob):
- Constructor property renamed `$filePath` to `$storageKey`
- Pass storage key to extract actions

**Extract Actions** (ExtractListingsAction, ExtractRealmListingsAction):
- Parameter renamed `$filePath` to `$storageKey`
- Use `Storage::readStream()` instead of `fopen()` for streaming

### Task 2: Test Files (4 files)

- PriceFetchActionTest: Assert `storageKey`, use `Storage::exists/delete`
- FetchCommodityPricesJobTest: Use `storageKey` property, `Storage::put` for fixtures
- ExtractRealmListingsActionTest: `writeRealmFixture()` uses `Storage::put`
- DeduplicationTest: Updated all `filePath`/`tempFilePath` references to `storageKey`

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] DeduplicationTest also referenced old property names**
- **Found during:** Task 2 verification
- **Issue:** DeduplicationTest.php had `$batchJob->filePath`, `tempFilePath` assertions, and `@unlink()` calls not listed in plan
- **Fix:** Updated all references in DeduplicationTest.php to use `storageKey` and `Storage` facade
- **Files modified:** tests/Feature/DataIngestion/DeduplicationTest.php
- **Commit:** 5193093

## Commits

| Task | Commit | Description |
|------|--------|-------------|
| 1 | fe78cab | Replace absolute file paths with Storage keys in 10 production files |
| 2 | 5193093 | Update 4 test files to use storageKey and Storage facade assertions |

## Verification

- All 37 targeted tests pass (PriceFetchAction, FetchCommodityPrices, ExtractRealmListings, Deduplication)
- Full suite: 236 passed, 1 pre-existing failure (BlizzardTokenServiceTest -- unrelated)
- Zero remaining references to `tempFilePath` or `->filePath` in modified files
- Only `fopen`/`@unlink` remaining is the tempnam download in fetch actions (local temp before Storage::put)
