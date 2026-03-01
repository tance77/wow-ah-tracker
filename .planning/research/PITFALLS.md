# Pitfalls Research

**Domain:** WoW Auction House price tracker (Blizzard Game Data API + Laravel + price dashboard)
**Researched:** 2026-03-01
**Confidence:** MEDIUM — Core API pitfalls confirmed via official Blizzard forums and developer posts. Laravel patterns confirmed via official docs. Some data-specific claims from community sources only.

---

## Critical Pitfalls

### Pitfall 1: Sending OAuth Token as URL Query String

**What goes wrong:**
The Blizzard API permanently stopped accepting OAuth access tokens passed via URL query string as of September 30, 2024. Any request using `?access_token=...` returns a 401 Unauthorized. This was a breaking API gateway change with a hard deadline — no grace period after the cutoff.

**Why it happens:**
Older tutorials and blog posts show the token appended to the URL. Developers copy examples without checking the current Blizzard developer portal.

**How to avoid:**
Always send the token in the HTTP Authorization header:
```
Authorization: Bearer YOUR_CLIENT_TOKEN
```
Never pass it as a query parameter. Verify this on day one before building anything else on top of it.

**Warning signs:**
- 401 Unauthorized responses despite a valid token
- Token works in Postman with header but not in code that appends it to the URL

**Phase to address:**
Phase 1 (API integration foundation). Must be correct from the first HTTP request.

---

### Pitfall 2: Not Caching the Access Token (Re-fetching on Every Request)

**What goes wrong:**
Blizzard client credentials tokens expire after 24 hours. There is no refresh token — you request a brand new token each time. If the token is fetched on every poll cycle (every 15 minutes), you make 96 unnecessary token requests per day and risk hitting authentication endpoint limits, adding latency to every scheduled job, and leaking credentials in logs.

**Why it happens:**
The client credentials flow feels simple — just request a token before each API call. Developers skip the caching step thinking it doesn't matter at low volume.

**How to avoid:**
Cache the token in Laravel's cache store with a TTL slightly shorter than 24 hours (e.g., 23 hours). Before each API call, retrieve from cache; only request a new token if cache misses. Use Laravel's `Cache::remember()` pattern. Store token expiry from the API response (`expires_in` field) rather than hardcoding 24 hours.

**Warning signs:**
- Auth token request logged every 15 minutes
- Token not persisted anywhere in the codebase
- `expires_in` from the token response is ignored

**Phase to address:**
Phase 1 (API integration foundation).

---

### Pitfall 3: Ignoring the Hourly Data Update Ceiling

**What goes wrong:**
The commodities endpoint data is generated at most once per hour by Blizzard's backend. Polling every 15 minutes fetches identical data three out of four times, storing duplicate price snapshots and making the "15-minute granularity" a lie in the database.

**Why it happens:**
The polling interval is set independently of the API's actual refresh cadence. The endpoint returns 200 OK with the same payload — there's no obvious signal that data hasn't changed.

**How to avoid:**
Two options:
1. Use the `Last-Modified` response header to check if the data has changed before storing a new snapshot. If `Last-Modified` matches the previous fetch, skip storage.
2. Hash the raw response and only write to the database when the hash differs from the previous stored hash.

Option 1 is preferred — Blizzard returns `Last-Modified` on this endpoint. Polling every 15 minutes is fine for freshness, but gate the database write on actual data change.

**Warning signs:**
- Price snapshots at identical values across multiple consecutive 15-minute intervals
- `price_snapshots` table growing 4x faster than actual AH updates

**Phase to address:**
Phase 2 (scheduled polling job). The data-change gate must be part of the initial job design, not a retrofit.

---

### Pitfall 4: Storing Prices as Float Instead of Integer (Copper Units)

**What goes wrong:**
The Blizzard API returns prices in copper (integer). One gold = 10,000 copper. Treating this as a float introduces floating-point rounding errors that compound over time in aggregations (averages, medians). Displaying prices requires converting copper → gold, and division by 10,000 creates fractional values that must not be stored as the canonical value.

**Why it happens:**
Prices look like "large numbers" so developers cast them to `float` or `decimal` without recognizing the integer nature of copper. Others store in gold (divide before storing) and lose precision permanently.

**How to avoid:**
Store prices as `BIGINT` (unsigned) in copper units, exactly as returned by the API. Convert to gold only at display time. Use `number_format($copper / 10000, 2)` for display, never for storage.

**Warning signs:**
- Database column type is `float`, `double`, or `decimal` for price fields
- Code that divides by 10,000 before inserting into the database

**Phase to address:**
Phase 2 (database schema design). The schema must use `BIGINT` before any data is stored.

---

### Pitfall 5: Treating Minimum Price as "The Price"

**What goes wrong:**
The commodities endpoint returns individual auction listings with quantity and unit_price. Displaying the single lowest `unit_price` as "the price" is misleading — that listing may be a market manipulator posting a tiny quantity at an artificially low price to fake a crash. The minimum price cannot be purchased at scale because only one listing may exist at that price.

**Why it happens:**
Minimum price is the most obvious "current price" to pull from the dataset. It's a natural first implementation.

**How to avoid:**
Calculate multiple price metrics per snapshot and store them all:
- `price_min`: Absolute lowest unit_price (can be misleading but useful for context)
- `price_avg`: Simple average across all listings
- `price_median`: Median unit_price across all listings (best single signal for commodity fair value)
- `volume_total`: Sum of all listing quantities (market depth signal)

Display median as the primary "market price." Show min alongside it with appropriate labeling. The median is resistant to single-listing manipulation.

**Warning signs:**
- Schema has only one price column
- "Current price" shown on dashboard is always from a single row MIN() query

**Phase to address:**
Phase 2 (schema) and Phase 3 (dashboard display). Both schema design and display logic must account for this from the start.

---

### Pitfall 6: No Detection for Stale API Data

**What goes wrong:**
The Blizzard backend that generates commodity snapshots can fail silently. In documented incidents (September 2024), the commodity API went 24 hours without updating. Your poller continues running successfully (HTTP 200, valid JSON, no errors) but the data is stale. The dashboard looks live but shows yesterday's prices.

**Why it happens:**
Developers monitor for HTTP errors and job failures, but not for data staleness. The job "succeeds" — it fetched and stored data — even when that data hasn't changed in 18 hours.

**How to avoid:**
Store the `Last-Modified` header value from each commodity fetch. Add a staleness check: if the most recent `Last-Modified` is older than 2 hours, flag the dashboard with a "data may be stale" warning and log an alert. This is the only way to detect a Blizzard-side outage.

**Warning signs:**
- All price snapshots in the last 3+ hours have identical values
- `Last-Modified` header not being stored anywhere

**Phase to address:**
Phase 2 (polling job) must capture `Last-Modified`. Phase 3 (dashboard) must display a staleness indicator.

---

## Technical Debt Patterns

| Shortcut | Immediate Benefit | Long-term Cost | When Acceptable |
|----------|-------------------|----------------|-----------------|
| Store only min_price per snapshot | Simpler schema, faster inserts | Cannot show median/avg later without re-fetching all history; loses market depth | Never — median and volume are trivially cheap to calculate at insert time |
| Skip `Last-Modified` check, always store | Simpler job logic | 3x duplicate rows for identical data; inflated table growth | Never — the check is a single header comparison |
| Float columns for copper prices | Feels natural | Floating-point errors in aggregations, impossible to fix retroactively | Never — BIGINT is always correct for copper |
| No failed job notification | Faster to ship | Silent 15-minute polling failures go undetected for hours | Only acceptable during local development |
| Hardcode watched item IDs in code | No admin UI needed | Adding/removing items requires a deploy | Acceptable for MVP if admin UI is phase 2 |
| Poll every 15 min without overlap protection | Simple cron setup | If job runs > 15 min, two instances overlap and write duplicate data | Never — `withoutOverlapping()` is one line |

---

## Integration Gotchas

| Integration | Common Mistake | Correct Approach |
|-------------|----------------|------------------|
| Blizzard OAuth | Passing token as `?access_token=` query param | `Authorization: Bearer TOKEN` header only (breaking change since Sept 2024) |
| Blizzard OAuth | Re-fetching token on every job run | Cache token with TTL from `expires_in` field; refresh only on expiry or 401 |
| Commodities endpoint | Using wrong namespace | Must include `?namespace=dynamic-us` (or eu/kr/tw). Missing or wrong namespace returns 404 |
| Commodities endpoint | Not handling the 25-point API cost | This endpoint costs 25 API quota points (vs 1 for most endpoints). At 15-min intervals it's only 96 requests/day × 25 points = 2,400 points/day, well within the 36,000/hour limit. Not a concern for single-user app but worth knowing |
| Commodities endpoint | Fetching via browser/client-side JS | The `Battlenet-Namespace` header is blocked by CORS. Must always fetch server-side |
| Commodities response | Treating all listings as current | The endpoint is a snapshot. Items posted and sold within the same snapshot window are invisible |
| Price display | Converting copper to gold before storage | Always store in copper (integer), convert only at display layer |

---

## Performance Traps

| Trap | Symptoms | Prevention | When It Breaks |
|------|----------|------------|----------------|
| Loading all price history for a chart without date range filtering | Chart page slow to render; query returns thousands of rows | Always pass date range (`WHERE created_at >= ?`) and limit rows with a configurable window (default: 7 days) | At ~100 snapshots/day, breaks visually at ~30 days of data; breaks performance at ~1 year |
| No index on `(item_id, created_at)` in price_snapshots table | Chart queries slow as table grows | Add composite index on `item_id, created_at DESC` at schema creation | Noticeable slowdown after ~50K rows |
| Computing median/percentile in PHP on raw rows | Job processing slow; memory spike during poller | Calculate median at insert time and store it; or use SQL's PERCENTILE_CONT if available | Breaks at ~5,000 listings per item in the commodities payload |
| Polling job runs without `withoutOverlapping()` | Duplicate snapshot rows; DB writes race | Add `->withoutOverlapping()` to scheduled command | Any time a job takes longer than 15 minutes (API timeout, slow query) |
| Fetching all price_snapshots in a single Eloquent query for dashboard | N+1 risk if chart data is loaded per-item in a loop | Use a single query grouped by item_id, or eager load per item with date bounds | At 7 watched items × 30 days × 96 snapshots/day = 20,160 rows pulled per page load |

---

## Security Mistakes

| Mistake | Risk | Prevention |
|---------|------|------------|
| Blizzard client_id and client_secret in source code or git history | Credentials leaked; Blizzard can terminate API access | Store only in `.env`, `.env` in `.gitignore`, verify at project init |
| No authentication on the dashboard | Anyone with the URL can see price data and admin UI | Use Laravel's built-in auth (even just a single hardcoded password via `HTTP Basic Auth` middleware is sufficient for a single-user tool) |
| Admin UI for watched items accessible without auth | Anyone can add/remove watched items, change polling behavior | Admin routes must be behind auth middleware |
| Logging full API responses including access tokens | Token exposed in log files | Mask or exclude the Authorization header from HTTP client logging |

---

## UX Pitfalls

| Pitfall | User Impact | Better Approach |
|---------|-------------|-----------------|
| Showing price in copper on the dashboard | Numbers like "450000" are meaningless without context | Always display as gold with 2 decimal places: "45.00g" |
| No "last updated" timestamp on chart | User can't tell if they're looking at fresh or stale data | Show "Last snapshot: X minutes ago" on every chart |
| Displaying min price as "current price" | A single low-quantity listing can make price look 50% lower than reality | Display median as primary price; show min as secondary with label "cheapest listing" |
| Trend arrow based on min-price change | Min price is volatile; trend signals are noisy and wrong | Base trend arrows on median price change over last N snapshots |
| Showing all history in one chart by default | Week-old data makes recent trends invisible | Default to 7-day window; let user select 1d / 7d / 30d / all |
| No visual indicator during the 3 hours when data isn't actually refreshing | User thinks price is live every 15 min | Show the `Last-Modified` time from Blizzard, not the job run time |

---

## "Looks Done But Isn't" Checklist

- [ ] **OAuth token caching:** Token appears to work but is re-fetched on every job run — verify a cache hit occurs on the second run
- [ ] **Duplicate snapshot prevention:** `Last-Modified` header is being compared before each DB write — verify duplicate rows are not created when data hasn't changed
- [ ] **Price staleness indicator:** Dashboard shows a warning when `Last-Modified` is > 2 hours old — verify it appears during a simulated outage
- [ ] **Overlap protection:** Scheduled command uses `->withoutOverlapping()` — verify no duplicate jobs run if a poll takes > 15 minutes
- [ ] **Failed job notification:** A failed poll job triggers an alert — verify a job that throws an exception is detected and surfaced
- [ ] **Integer price storage:** Database columns for prices are `BIGINT UNSIGNED` — verify no `float` or `decimal` columns exist for price fields
- [ ] **All price metrics stored:** Each snapshot stores `price_min`, `price_median`, `price_avg`, and `volume_total` — verify schema has all four columns
- [ ] **Auth protection:** Dashboard and admin routes are behind auth middleware — verify an unauthenticated request to `/admin` is rejected
- [ ] **`.env` not committed:** `client_id` and `client_secret` are not in any committed file — verify `.gitignore` includes `.env`
- [ ] **Namespace parameter present:** Every API call includes `?namespace=dynamic-us` — verify a request without it returns 404 in testing

---

## Recovery Strategies

| Pitfall | Recovery Cost | Recovery Steps |
|---------|---------------|----------------|
| Token passed via query string (all requests failing) | LOW | Update HTTP client to use Authorization header; redeploy; test immediately |
| Float columns for prices (data already stored) | HIGH | Cannot fix stored data retroactively without precision loss. Requires new BIGINT column, data migration with rounding to nearest copper, then column swap |
| No `Last-Modified` check (duplicate rows exist) | MEDIUM | Add deduplication to job; write a one-time migration to deduplicate rows keeping one per hourly boundary; add index |
| Only min_price stored (no median/avg history) | HIGH | Historical median cannot be reconstructed from stored min price alone. Accept loss of historical median; start storing correctly from fix date forward |
| Watched item IDs hardcoded (need to change set) | LOW | Add the items table and admin UI in a follow-up phase; migration is straightforward |
| No overlap protection (duplicate snapshot rows) | MEDIUM | Add `withoutOverlapping()`; write deduplication query for existing duplicates using DISTINCT ON or GROUP BY |

---

## Pitfall-to-Phase Mapping

| Pitfall | Prevention Phase | Verification |
|---------|------------------|--------------|
| Token as query string | Phase 1: API client setup | Make a request with wrong token format; confirm 401; confirm correct format works |
| Token not cached | Phase 1: API client setup | Run two consecutive requests; confirm second uses cached token (log shows no new token fetch) |
| Missing namespace param | Phase 1: API client setup | Test request without namespace; confirm 404; confirm with namespace works |
| Float price columns | Phase 2: Database schema | Run `DESCRIBE price_snapshots`; confirm price columns are `bigint unsigned` |
| Only min_price stored | Phase 2: Database schema | Confirm schema has min, median, avg, volume columns before any data is inserted |
| No `Last-Modified` gate | Phase 2: Polling job | Run poller twice with no intervening AH update; confirm second run did not insert a row |
| No overlap protection | Phase 2: Polling job | Confirm `->withoutOverlapping()` present in scheduler definition |
| No staleness detection | Phase 2 + Phase 3: Job + Dashboard | Simulate stale data (mock old `Last-Modified`); confirm dashboard shows warning banner |
| Min price as "current price" | Phase 3: Dashboard display | Verify dashboard labels and primary metric use median, not min |
| No auth on routes | Phase 3: Authentication | Confirm unauthenticated GET to `/dashboard` redirects to login |
| No failed job alerting | Phase 2: Polling job | Force job to throw exception; confirm failure is logged/surfaced |

---

## Sources

- [Upcoming Changes to Battle.net's API Gateway — Blizzard Forums](https://us.forums.blizzard.com/en/blizzard/t/upcoming-changes-to-battlenet%E2%80%99s-api-gateway/51561) — confirmed OAuth query string deprecation, deadline Sept 2024
- [Immediate change to Auction APIs for Commodities with 9.2.7 — Blizzard Forums](https://us.forums.blizzard.com/en/blizzard/t/immediate-change-to-auction-apis-for-commodities-with-927/31522) — confirmed commodities endpoint costs 25 API points; `if-modified-since` quirks; hourly update frequency
- [Fixed: WoW Auction commodities endpoint 429 Errors — Blizzard Forums](https://us.forums.blizzard.com/en/blizzard/t/fixed-wow-auction-commodities-endpoint-429-errors/52461) — confirmed API gateway instability history (Nov 2024)
- [Resolved: WoW AH commodities API has not reported a result in 24 hours — Blizzard Forums](https://us.forums.blizzard.com/en/wow/t/resolved-wow-ah-commodities-api-has-not-reported-a-result-in-24-hours/1961522) — confirmed Blizzard-side staleness outage can last 24 hours with HTTP 200 responses
- [OAuth API Refresh token? — Blizzard Forums](https://us.forums.blizzard.com/en/blizzard/t/oauth-api-refresh-token/559) — confirmed no refresh token; 24-hour expiry; must re-request
- [Task Scheduling — Laravel 12.x Docs](https://laravel.com/docs/12.x/scheduling) — confirmed `withoutOverlapping()` behavior and lock mechanism
- [Battlenet-Namespace header blocked by CORS — Blizzard Forums](https://us.forums.blizzard.com/en/blizzard/t/battlenet-namespace-header-blocked-by-cors/1556) — confirmed server-side only for namespace header
- WoW gold integer overflow: Slashdot report on 2^31 signed integer limit — confirmed copper-as-integer is the correct storage model
- Community knowledge (MEDIUM confidence): hourly snapshot frequency, min price manipulation patterns, copper-to-gold display conventions

---
*Pitfalls research for: WoW Auction House price tracker (Laravel + Blizzard Game Data API)*
*Researched: 2026-03-01*
