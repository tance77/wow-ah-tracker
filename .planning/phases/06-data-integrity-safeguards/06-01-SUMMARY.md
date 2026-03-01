---
phase: 06-data-integrity-safeguards
plan: 01
subsystem: database
tags: [laravel, eloquent, deduplication, ingestion, metadata, md5]

# Dependency graph
requires:
  - phase: 05-data-ingestion-pipeline
    provides: FetchCommodityPricesJob and PriceFetchAction as base for dedup gate
provides:
  - ingestion_metadata table with last_modified_at, response_hash, last_fetched_at, consecutive_failures
  - IngestionMetadata Eloquent model with singleton() accessor
  - PriceFetchAction returning {listings, lastModified, rawBody} 3-key array
  - Last-Modified primary dedup gate in FetchCommodityPricesJob
  - MD5 hash fallback dedup gate when Last-Modified absent
  - consecutive_failures tracking with increment on error, reset on success
affects: [07-dashboard-and-charts, 08-alerting]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - singleton() accessor on Eloquent model using firstOrCreate(['id' => 1]) for single-row global state
    - Two-tier dedup gate: primary (header comparison) with hash fallback
    - Wrap HTTP action in try/catch with metadata side-effect on failure path

key-files:
  created:
    - database/migrations/2026_03_02_000001_create_ingestion_metadata_table.php
    - app/Models/IngestionMetadata.php
    - tests/Feature/DataIngestion/DeduplicationTest.php
  modified:
    - app/Actions/PriceFetchAction.php
    - app/Jobs/FetchCommodityPricesJob.php
    - tests/Feature/BlizzardApi/PriceFetchActionTest.php

key-decisions:
  - "IngestionMetadata::singleton() uses firstOrCreate(['id' => 1], ['consecutive_failures' => 0]) — explicit default prevents null cast on fresh create"
  - "PriceFetchAction hashes raw body BEFORE filtering — hash represents full API response, not per-item subset"
  - "MD5 used for dedup hash (not SHA256) — sufficient for dedup, not a security use case"
  - "Dedup gate is global (entire API response), not per-item — one gate blocks all writes for the cycle"
  - "On API failure: catch RuntimeException, increment consecutive_failures, skip cycle — no job retry, 15-min scheduler is the natural retry"
  - "last_modified_at stored as raw string (not parsed datetime) — header value compared directly without parsing"

patterns-established:
  - "Two-tier dedup: Last-Modified header primary, MD5 hash fallback — handles both header-present and header-absent API responses"
  - "Metadata updates always happen post-write-loop — never before — so partial failures don't update state prematurely"

requirements-completed:
  - DATA-04

# Metrics
duration: 2min
completed: 2026-03-01
---

# Phase 6 Plan 1: Data Integrity Safeguards — Ingestion Dedup Gate Summary

**Ingestion dedup gate using Last-Modified header (primary) and MD5 body hash (fallback) with per-cycle failure tracking in ingestion_metadata table**

## Performance

- **Duration:** 2 min
- **Started:** 2026-03-01T22:54:37Z
- **Completed:** 2026-03-01T22:56:37Z
- **Tasks:** 2
- **Files modified:** 6 (3 created, 3 modified)

## Accomplishments

- Created `ingestion_metadata` table with dedup state and staleness tracking columns (last_modified_at, response_hash, last_fetched_at, consecutive_failures)
- Updated `PriceFetchAction` return type from bare array of listings to `{listings, lastModified, rawBody}` 3-key array — single HTTP request captures all dedup data
- Implemented two-tier dedup gate in `FetchCommodityPricesJob`: Last-Modified header comparison (primary), MD5 hash fallback (when header absent), with metadata updates after successful writes and failure tracking on exceptions

## Task Commits

Each task was committed atomically:

1. **Task 1: Create ingestion_metadata migration and IngestionMetadata model** - `f8c61cb` (feat)
2. **Task 2 RED: Failing deduplication tests** - `c19a65b` (test)
3. **Task 2 GREEN: Implement PriceFetchAction and FetchCommodityPricesJob changes** - `b73e5cb` (feat)

## Files Created/Modified

- `database/migrations/2026_03_02_000001_create_ingestion_metadata_table.php` - Creates ingestion_metadata table with all dedup/staleness columns
- `app/Models/IngestionMetadata.php` - Eloquent model with singleton() accessor using firstOrCreate id=1
- `app/Actions/PriceFetchAction.php` - Updated to return {listings, lastModified, rawBody} 3-key array; captures Last-Modified header and raw body before filtering
- `app/Jobs/FetchCommodityPricesJob.php` - Added try/catch RuntimeException block, Last-Modified gate, hash fallback gate, metadata update after write loop
- `tests/Feature/DataIngestion/DeduplicationTest.php` - 8 new tests covering all dedup gate paths and failure tracking
- `tests/Feature/BlizzardApi/PriceFetchActionTest.php` - Updated 3 existing tests to use result['listings'] after return type change

## Decisions Made

- `singleton()` includes `['consecutive_failures' => 0]` as default array in `firstOrCreate` — avoids null on fresh model create since DB defaults aren't reflected in in-memory model until persisted and re-fetched
- Raw body hashed before filtering so hash represents the full API response (not the per-item-filtered subset)
- last_modified_at stored as raw string — Blizzard's RFC 7231 date is compared directly without parsing

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Updated PriceFetchActionTest to use new return shape**
- **Found during:** Task 2 GREEN phase (full suite run)
- **Issue:** Three existing tests in PriceFetchActionTest.php compared result directly as a bare array (e.g., `count($result)`, `$result->toBe([])`) — these failed after return type changed from bare array to 3-key array
- **Fix:** Updated all three tests to use `$result['listings']` instead of `$result` for array access and count operations
- **Files modified:** `tests/Feature/BlizzardApi/PriceFetchActionTest.php`
- **Verification:** Full suite 87 tests passing after fix
- **Committed in:** `b73e5cb` (Task 2 GREEN commit)

**2. [Rule 1 - Bug] Added consecutive_failures default to singleton() create args**
- **Found during:** Task 2 GREEN phase (DeduplicationTest failure)
- **Issue:** `firstOrCreate(['id' => 1])` without defaults created model with `consecutive_failures = null` (DB default not reflected in PHP model object until re-read); test asserting `toBe(0)` failed
- **Fix:** Added `['consecutive_failures' => 0]` as second argument to `firstOrCreate` in `IngestionMetadata::singleton()`
- **Files modified:** `app/Models/IngestionMetadata.php`
- **Verification:** All 8 DeduplicationTest tests pass, 87 total suite passing
- **Committed in:** `b73e5cb` (Task 2 GREEN commit)

---

**Total deviations:** 2 auto-fixed (both Rule 1 - Bug)
**Impact on plan:** Both fixes required for correctness. The return type update was an expected side-effect of the PriceFetchAction change. No scope creep.

## Issues Encountered

None — the plan accurately described required changes. Both deviations were minor corrections discovered during test execution.

## Next Phase Readiness

- `ingestion_metadata` table populated after first successful job run — Phase 7 dashboard can read `consecutive_failures` and `last_fetched_at` for staleness display
- `response_hash` and `last_modified_at` are nullable — dashboard must handle null values (first run before any fetch completes)
- Dedup gate operational: subsequent job runs with unchanged Blizzard data will skip writes and log at info level

---
*Phase: 06-data-integrity-safeguards*
*Completed: 2026-03-01*

## Self-Check: PASSED

- FOUND: database/migrations/2026_03_02_000001_create_ingestion_metadata_table.php
- FOUND: app/Models/IngestionMetadata.php
- FOUND: app/Actions/PriceFetchAction.php
- FOUND: app/Jobs/FetchCommodityPricesJob.php
- FOUND: tests/Feature/DataIngestion/DeduplicationTest.php
- FOUND: .planning/phases/06-data-integrity-safeguards/06-01-SUMMARY.md
- FOUND: commit f8c61cb (Task 1 - migration and model)
- FOUND: commit c19a65b (Task 2 RED - failing tests)
- FOUND: commit b73e5cb (Task 2 GREEN - implementation)
