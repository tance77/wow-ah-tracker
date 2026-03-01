---
phase: 04-blizzard-api-integration
plan: 01
subsystem: api
tags: [laravel, oauth2, blizzard, http, cache, singleton]

requires:
  - phase: 03-item-watchlist-management
    provides: WatchedItem model and watchlist UI — the items that need price data from Blizzard API

provides:
  - BlizzardTokenService class with getToken() returning cached OAuth2 access token
  - Singleton registration in AppServiceProvider for reuse across request lifecycle

affects:
  - 04-02-blizzard-api-integration (commodity price fetch uses this token)
  - phase-05-price-ingestion (scheduled job calls getToken() before every API request)

tech-stack:
  added: []
  patterns:
    - Service class in app/Services/ namespace with declare(strict_types=1)
    - Cache::remember() for long-lived external token caching (82800s = 23h TTL)
    - Http facade (not raw Guzzle) for testability via Http::fake()
    - Singleton binding in AppServiceProvider::register() for shared service instance

key-files:
  created:
    - app/Services/BlizzardTokenService.php
  modified:
    - app/Providers/AppServiceProvider.php

key-decisions:
  - "Cache key 'blizzard_token' with 82800s TTL (23h) chosen to stay within Blizzard's 24h token expiry with 1h safety buffer"
  - "retry(2, 1000) added to Http chain for transient network resilience — discretionary improvement per plan"
  - "Singleton binding uses explicit closure returning new BlizzardTokenService() rather than string binding — explicit is clearer"
  - "No boot-time credential validation — lazy validation approach per CONTEXT.md project decision"

patterns-established:
  - "Service pattern: App\\Services namespace, declare(strict_types=1), single public method returning typed value"
  - "HTTP pattern: Http::withBasicAuth()->asForm()->retry()->timeout()->post() chain for external API calls"
  - "Error pattern: RuntimeException with HTTP status in message for failed external calls"

requirements-completed: [DATA-05]

duration: 3min
completed: 2026-03-01
---

# Phase 4 Plan 1: Blizzard Token Service Summary

**Laravel BlizzardTokenService with OAuth2 client credentials flow, 23-hour Cache::remember() TTL, and AppServiceProvider singleton registration**

## Performance

- **Duration:** 3 min
- **Started:** 2026-03-01T21:47:10Z
- **Completed:** 2026-03-01T21:49:30Z
- **Tasks:** 2
- **Files modified:** 2

## Accomplishments

- BlizzardTokenService created with getToken() method using Cache::remember() at 82800s (23-hour) TTL
- Http::withBasicAuth() POST to https://oauth.battle.net/token with client credentials and asForm() body
- RuntimeException thrown on failed HTTP response or missing access_token in JSON
- AppServiceProvider registers BlizzardTokenService as singleton — same instance reused within request lifecycle

## Task Commits

Each task was committed atomically:

1. **Task 1: Create BlizzardTokenService with OAuth2 client credentials and Cache::remember()** - `49aeddc` (feat)
2. **Task 2: Register BlizzardTokenService as singleton in AppServiceProvider** - `a6ba1b2` (feat)

## Files Created/Modified

- `app/Services/BlizzardTokenService.php` - OAuth2 client credentials token fetch with 23-hour cache
- `app/Providers/AppServiceProvider.php` - Singleton binding for BlizzardTokenService

## Decisions Made

- Cache TTL set to 82800 seconds (23 hours) to stay within Blizzard's 24-hour token expiry with a 1-hour safety buffer
- Added retry(2, 1000) to Http chain for transient failure resilience (discretionary per plan guidance)
- Singleton binding uses explicit closure (not string auto-resolution) for clarity

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None.

## User Setup Required

None - no external service configuration required in this plan. Credentials (BLIZZARD_CLIENT_ID, BLIZZARD_CLIENT_SECRET) are read from config/services.php via .env — no new env vars introduced here.

## Next Phase Readiness

- BlizzardTokenService ready for Phase 4 Plan 2 (commodity price fetch endpoint)
- Token is lazy — no HTTP calls until getToken() is first invoked
- Http::fake() testable — downstream tests can stub the token endpoint without real credentials

---
*Phase: 04-blizzard-api-integration*
*Completed: 2026-03-01*
