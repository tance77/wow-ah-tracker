# Phase 4: Blizzard API Integration - Context

**Gathered:** 2026-03-01
**Status:** Ready for planning

<domain>
## Phase Boundary

The application can obtain and cache a valid Blizzard OAuth2 access token, and can fetch the full commodity listings from the Blizzard Game Data API filtered to watched item IDs. This phase delivers the token service and fetch action — the scheduled job, aggregation, and storage belong to Phase 5.

</domain>

<decisions>
## Implementation Decisions

### Error handling & resilience
- Claude's discretion on retry/failure strategy — pick what integrates best with the Laravel queue job in Phase 5
- Moderate logging: log errors, token refreshes, and successful fetches with item counts (not every HTTP detail)
- 30-second HTTP timeout for all Blizzard API calls (commodities payload is large, ~70K+ listings)
- Lazy credential validation — bad credentials surface as a clear error on first use, no boot-time check

### Token service design
- Use Laravel's default file cache driver via `Cache::remember()` — no extra infrastructure needed for a single-user local app
- Register `BlizzardTokenService` as a singleton in the service container
- Cache token for 23 hours (1-hour safety margin before Blizzard's 24h expiry), matching roadmap success criteria
- Use Laravel `Http` facade (not raw Guzzle) for token endpoint POST — enables `Http::fake()` in tests

### Commodity fetch scope
- `PriceFetchAction` fetches the full commodities response, then filters in memory to only watched item IDs before returning
- Action accepts an array of `blizzard_item_id` values as a parameter — does not query the database itself (clean separation)
- Return raw Blizzard JSON arrays (filtered) — Phase 5's aggregation action handles parsing into snapshots
- Build the `namespace` parameter dynamically as `dynamic-{region}` from the `BLIZZARD_REGION` config value (already in `config/services.php`)

### Testing & dev workflow
- Store sample Blizzard API responses as JSON fixture files in `tests/Fixtures/` — realistic structure, reusable across tests
- Use `Http::fake()` with fixture file loading in Pest feature tests
- No extra artisan command — use `php artisan tinker` for manual verification (per success criteria)
- Test coverage: happy path + basic error scenarios (401 unauthorized, 5xx server error, cache hit) — covers success criteria without over-testing
- Minimal fixture sample (5-10 commodity items with realistic structure) — fast tests, easy to maintain

### Claude's Discretion
- Retry strategy and backoff timing for failed API calls
- Exact error message formatting and log levels
- Whether to use a dedicated service provider or register in AppServiceProvider
- Internal method organization within the service and action classes

</decisions>

<specifics>
## Specific Ideas

- Token request must use Basic Auth (`client_id:secret` base64-encoded) per Blizzard OAuth2 spec — never pass credentials as query params
- Bearer token goes in the `Authorization` header on commodity calls, never as a query parameter
- The commodities endpoint URL format: `{region}.api.blizzard.com/data/wow/auctions/commodities`
- PriceFetchAction should be a simple invokable action class (consistent with the existing `app/Actions/` directory structure)

</specifics>

<code_context>
## Existing Code Insights

### Reusable Assets
- `config/services.php`: Blizzard credentials already wired (`client_id`, `client_secret`, `region`)
- `app/Actions/` directory: Empty but established as the convention for action classes
- `app/Services/` directory: Empty but established as the convention for service classes
- `WatchedItem` model: Has `blizzard_item_id` field — source for item ID filtering

### Established Patterns
- Pest for testing (`tests/Feature/` with existing auth and watchlist tests)
- `declare(strict_types=1)` on all PHP files
- Eloquent models with typed casts and explicit `$fillable`

### Integration Points
- `BlizzardTokenService` will be resolved from the container by `PriceFetchAction`
- `PriceFetchAction` will be called by `FetchCommodityPricesJob` in Phase 5
- Watched item IDs come from `WatchedItem::where(...)` queries (done by the caller, not the action)

</code_context>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 04-blizzard-api-integration*
*Context gathered: 2026-03-01*
