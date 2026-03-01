---
phase: 04-blizzard-api-integration
plan: 03
subsystem: testing
tags: [pest, http-fake, blizzard-api, token-service, price-fetch]

# Dependency graph
requires:
  - phase: 04-blizzard-api-integration/04-01
    provides: BlizzardTokenService with OAuth2 client credentials and cache
  - phase: 04-blizzard-api-integration/04-02
    provides: PriceFetchAction with Bearer token header and item filtering

provides:
  - Pest feature tests for BlizzardTokenService (4 tests)
  - Pest feature tests for PriceFetchAction (6 tests)
  - JSON fixture file with 6 realistic auction entries for both test suites
affects:
  - Phase 5 (price ingestion job) — test patterns established here apply to job tests

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Http::fake() with per-test helper function to avoid stubCallback accumulation"
    - "Cache::forget('blizzard_token') in beforeEach for deterministic test isolation"
    - "Http::assertSent() with closure for request header and URL verification"
    - "Http::assertSentCount(1) to verify cache hit prevents duplicate HTTP requests"

key-files:
  created:
    - tests/Fixtures/blizzard_commodities.json
    - tests/Feature/BlizzardApi/BlizzardTokenServiceTest.php
    - tests/Feature/BlizzardApi/PriceFetchActionTest.php
  modified:
    - app/Services/BlizzardTokenService.php
    - app/Actions/PriceFetchAction.php

key-decisions:
  - "retry(2, 1000, throw: false) required in both service and action — Laravel's HTTP client throws RequestException before service-level error handling runs without throw: false"
  - "Http::fake() merges stubCallbacks (does not replace) — per-test helper function fakeBothEndpoints() used instead of beforeEach Http::fake() to avoid stub accumulation breaking override tests"
  - "Trailing * on commodities URL pattern (*.api.blizzard.com/...commodities*) required — Str::is() matches full URL including query string, so pattern without trailing * fails to match ?namespace=dynamic-us"

patterns-established:
  - "Fixture files in tests/Fixtures/ loaded via file_get_contents(base_path('tests/Fixtures/...json'))"
  - "Http::fake() called per-test (not in beforeEach) when tests need different responses for same URL"

requirements-completed:
  - DATA-05

# Metrics
duration: 4min
completed: 2026-03-01
---

# Phase 4 Plan 3: Blizzard API Feature Tests Summary

**Pest feature test suite for BlizzardTokenService (4 tests) and PriceFetchAction (6 tests) using Http::fake() with JSON fixture — verifying Basic Auth, Bearer header, cache deduplication, namespace param, item filtering, and error handling without any live Blizzard API calls.**

## Performance

- **Duration:** 4 min
- **Started:** 2026-03-01T21:53:30Z
- **Completed:** 2026-03-01T21:57:30Z
- **Tasks:** 2
- **Files modified:** 5 (3 created, 2 auto-fixed)

## Accomplishments

- Created `tests/Fixtures/blizzard_commodities.json` with 6 realistic auction entries including an unwatched item (999999) for filter verification
- `BlizzardTokenServiceTest` covers Basic Auth header format, cache hit reducing HTTP calls to 1, 401/500 error handling — all 4 tests pass
- `PriceFetchActionTest` covers Bearer token header, namespace=dynamic-us query param, item ID filtering (excludes 999999 and unwatched 210930), empty result, 500 error, and re-indexed array keys — all 6 tests pass

## Task Commits

Each task was committed atomically:

1. **Task 1: Create blizzard_commodities.json fixture and BlizzardTokenServiceTest** - `d3df2c6` (test)
2. **Task 2: Create PriceFetchActionTest with fetch, filtering, and error tests** - `c8f8113` (test)

**Plan metadata:** _(docs commit follows — see final_commit)_

## Files Created/Modified

- `tests/Fixtures/blizzard_commodities.json` - 6 auction entries: 224025 x2, 210781 x2, 210930 x1, 999999 x1 (unwatched)
- `tests/Feature/BlizzardApi/BlizzardTokenServiceTest.php` - 4 tests for token service
- `tests/Feature/BlizzardApi/PriceFetchActionTest.php` - 6 tests for price fetch action
- `app/Services/BlizzardTokenService.php` - Auto-fix: added `throw: false` to retry()
- `app/Actions/PriceFetchAction.php` - Auto-fix: added `throw: false` to retry()

## Decisions Made

- `retry(2, 1000, throw: false)` required — without it, Laravel throws `RequestException` before the service's `!$response->successful()` check runs, so `RuntimeException` is never raised
- Per-test `fakeBothEndpoints()` helper instead of `Http::fake()` in `beforeEach` — Laravel's `fake()` accumulates callbacks via `merge()`, so a second call doesn't replace the first; a 500 override in the test body would be ignored because the 200 from beforeEach was registered first
- Trailing `*` appended to the commodities URL pattern — `Str::is()` matches against the full URL including query string, and `?namespace=dynamic-us` breaks exact pattern matching

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Fixed retry(2, 1000) throwing RequestException before service error handling**

- **Found during:** Task 1 (BlizzardTokenServiceTest — 401/500 error tests failing)
- **Issue:** Laravel's `retry()` method throws `Illuminate\Http\Client\RequestException` (not `RuntimeException`) after all retries fail, meaning the service's `if (!$response->successful())` block was never reached
- **Fix:** Added `throw: false` to `retry(2, 1000, throw: false)` in both `BlizzardTokenService` and `PriceFetchAction` so the response object is returned and service-level error handling executes
- **Files modified:** `app/Services/BlizzardTokenService.php`, `app/Actions/PriceFetchAction.php`
- **Verification:** All 4 BlizzardTokenServiceTest error tests pass after fix
- **Committed in:** `d3df2c6` (Task 1 commit)

---

**Total deviations:** 1 auto-fixed (Rule 1 - Bug)
**Impact on plan:** Fix was necessary for correct error propagation. The service contract (`throws RuntimeException`) was broken without it. No scope creep.

## Issues Encountered

Laravel's `Http::fake()` `stubCallbacks` accumulation behavior (merge, not replace) required restructuring the PriceFetchActionTest away from `beforeEach` Http::fake() setup to a per-test `fakeBothEndpoints()` helper function. This ensures the 500 error test is not shadowed by a previously-registered 200 stub for the same URL.

The commodities URL pattern required a trailing `*` (`*.api.blizzard.com/data/wow/auctions/commodities*`) because `Str::is()` matches against the full URL including query string appended by the HTTP client.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- Full DATA-05 test coverage in place — BlizzardTokenService and PriceFetchAction are verified correct
- 56 total tests all pass — no regressions across auth, watchlist, or API layers
- Phase 5 (price ingestion job) can proceed: the `retry(throw: false)` fix is already applied to both classes

---
*Phase: 04-blizzard-api-integration*
*Completed: 2026-03-01*
