# Phase 4: Blizzard API Integration - Research

**Researched:** 2026-03-01
**Domain:** Laravel HTTP Client, Cache facade, Blizzard Game Data API (OAuth2 + Commodities)
**Confidence:** HIGH (Laravel patterns verified via official docs; Blizzard API structure verified via multiple community sources and official endpoint confirmation)

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions

**Error handling & resilience**
- Claude's discretion on retry/failure strategy — pick what integrates best with the Laravel queue job in Phase 5
- Moderate logging: log errors, token refreshes, and successful fetches with item counts (not every HTTP detail)
- 30-second HTTP timeout for all Blizzard API calls (commodities payload is large, ~70K+ listings)
- Lazy credential validation — bad credentials surface as a clear error on first use, no boot-time check

**Token service design**
- Use Laravel's default file cache driver via `Cache::remember()` — no extra infrastructure needed for a single-user local app
- Register `BlizzardTokenService` as a singleton in the service container
- Cache token for 23 hours (1-hour safety margin before Blizzard's 24h expiry), matching roadmap success criteria
- Use Laravel `Http` facade (not raw Guzzle) for token endpoint POST — enables `Http::fake()` in tests

**Commodity fetch scope**
- `PriceFetchAction` fetches the full commodities response, then filters in memory to only watched item IDs before returning
- Action accepts an array of `blizzard_item_id` values as a parameter — does not query the database itself (clean separation)
- Return raw Blizzard JSON arrays (filtered) — Phase 5's aggregation action handles parsing into snapshots
- Build the `namespace` parameter dynamically as `dynamic-{region}` from the `BLIZZARD_REGION` config value (already in `config/services.php`)

**Testing & dev workflow**
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

### Deferred Ideas (OUT OF SCOPE)
None — discussion stayed within phase scope
</user_constraints>

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| DATA-05 | Blizzard OAuth2 token cached and refreshed automatically | `Cache::remember()` with 23-hour TTL; `BlizzardTokenService` singleton pattern; Blizzard client credentials flow confirmed |
</phase_requirements>

---

## Summary

This phase implements two focused classes: `BlizzardTokenService` (OAuth2 token acquisition and caching) and `PriceFetchAction` (commodity data fetch and filter). Both use Laravel's Http facade, which allows `Http::fake()` in tests — a critical constraint. The Blizzard token endpoint is `https://oauth.battle.net/token`, uses HTTP Basic Auth (client_id:secret), and returns a 24-hour token. The commodities endpoint is `https://{region}.api.blizzard.com/data/wow/auctions/commodities` and takes a `namespace=dynamic-{region}` query parameter.

Laravel 12's Http facade provides `withBasicAuth()`, `withToken()`, `timeout()`, and `asForm()` — all needed for this phase. The `Cache::remember()` method accepts the TTL as an integer (seconds) or a Carbon instance. Singleton binding in `AppServiceProvider::register()` is the standard Laravel pattern and fits within the existing `app/Providers/AppServiceProvider.php` (currently empty).

The Blizzard commodities response structure is: `{"auctions": [...]}` where each entry has `{"id": int, "item": {"id": int}, "unit_price": int, "quantity": int, "time_left": string}`. The `unit_price` is always in copper (integer). The `item.id` field is the `blizzard_item_id` used for filtering. This response can contain 70K+ listings and the endpoint carries a 25-point API quota cost — keep the 30-second timeout.

**Primary recommendation:** Register `BlizzardTokenService` as a singleton in `AppServiceProvider::register()`, use `Http::withBasicAuth()->asForm()->timeout(30)->post()` for token acquisition, `Cache::remember('blizzard_token', 82800, ...)` for caching (82800 = 23 hours in seconds), and `Http::withToken($token)->timeout(30)->get()` with `['namespace' => 'dynamic-us']` for commodity fetches.

---

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| `Illuminate\Support\Facades\Http` | Laravel 12 | HTTP calls to Blizzard endpoints | Locked decision; enables `Http::fake()` in tests |
| `Illuminate\Support\Facades\Cache` | Laravel 12 | Token caching at 23-hour TTL | Locked decision; file driver, no extra infrastructure |
| `Illuminate\Support\Facades\Log` | Laravel 12 | Moderate logging of errors and refreshes | Standard Laravel logging |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| `Illuminate\Support\ServiceProvider` | Laravel 12 | Register singleton | `AppServiceProvider::register()` for `BlizzardTokenService` |
| Pest 3.x | Already installed | Feature tests with Http::fake() | All test coverage for this phase |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| `Http` facade | Raw Guzzle | Guzzle cannot use `Http::fake()` — ruled out by locked decision |
| File cache driver | Redis | Redis requires extra infrastructure — ruled out by locked decision |
| `AppServiceProvider` | Dedicated `BlizzardServiceProvider` | Both valid; AppServiceProvider is simpler for one singleton |

**Installation:** No new packages required. All dependencies are already in `composer.json`.

---

## Architecture Patterns

### Recommended Project Structure
```
app/
├── Services/
│   └── BlizzardTokenService.php   # OAuth2 token fetch + cache
├── Actions/
│   └── PriceFetchAction.php       # Commodities fetch + filter (invokable)
├── Providers/
│   └── AppServiceProvider.php     # Singleton registration
tests/
├── Feature/
│   └── BlizzardApi/
│       ├── BlizzardTokenServiceTest.php
│       └── PriceFetchActionTest.php
└── Fixtures/
    └── blizzard_commodities.json  # 5-10 item realistic fixture
```

### Pattern 1: OAuth2 Client Credentials Token Fetch

**What:** POST to `oauth.battle.net/token` with Basic Auth and `grant_type=client_credentials` as form body. Returns `{"access_token": "...", "token_type": "bearer", "expires_in": 86400}`.

**When to use:** Called inside `Cache::remember()` closure only — never directly.

```php
// Source: https://laravel.com/docs/12.x/http-client (verified)
// Source: https://us.forums.blizzard.com/en/blizzard/t/oauth2-client-credentials-implementations/131 (verified)
$response = Http::withBasicAuth(
    config('services.blizzard.client_id'),
    config('services.blizzard.client_secret')
)->asForm()->timeout(30)->post('https://oauth.battle.net/token', [
    'grant_type' => 'client_credentials',
]);

$token = $response->json('access_token');
```

### Pattern 2: Cache::remember() for Token Caching

**What:** Retrieve cached token or fetch fresh one. 23 hours = 82800 seconds.

**When to use:** The only public method on `BlizzardTokenService`.

```php
// Source: https://laravel.com/docs/12.x/cache (verified)
public function getToken(): string
{
    return Cache::remember('blizzard_token', 82800, function (): string {
        Log::info('BlizzardTokenService: fetching new token');
        $response = Http::withBasicAuth(...)
            ->asForm()
            ->timeout(30)
            ->post('https://oauth.battle.net/token', ['grant_type' => 'client_credentials']);

        if (! $response->successful()) {
            throw new \RuntimeException(
                'Blizzard token fetch failed: ' . $response->status()
            );
        }

        return $response->json('access_token');
    });
}
```

### Pattern 3: Singleton Registration in AppServiceProvider

**What:** Register `BlizzardTokenService` so the container always returns the same instance.

**When to use:** `AppServiceProvider::register()` — runs before the app boots.

```php
// Source: https://laravel.com/docs/12.x/container (verified)
public function register(): void
{
    $this->app->singleton(BlizzardTokenService::class, function () {
        return new BlizzardTokenService();
    });
}
```

### Pattern 4: Invokable Action Class (PriceFetchAction)

**What:** Single `__invoke()` method accepts `array $itemIds`, fetches commodities, filters, returns array.

**When to use:** Consistent with the existing `app/Actions/` convention established in CONTEXT.md.

```php
// Source: CONTEXT.md code_context
class PriceFetchAction
{
    public function __construct(
        private readonly BlizzardTokenService $tokenService,
    ) {}

    public function __invoke(array $itemIds): array
    {
        $region = config('services.blizzard.region', 'us');
        $token = $this->tokenService->getToken();

        $response = Http::withToken($token)
            ->timeout(30)
            ->get("https://{$region}.api.blizzard.com/data/wow/auctions/commodities", [
                'namespace' => "dynamic-{$region}",
            ]);

        if (! $response->successful()) {
            Log::error('PriceFetchAction: commodities fetch failed', ['status' => $response->status()]);
            throw new \RuntimeException('Blizzard commodities fetch failed: ' . $response->status());
        }

        $auctions = $response->json('auctions', []);

        // Filter to only watched item IDs
        return array_filter(
            $auctions,
            fn(array $entry) => in_array($entry['item']['id'], $itemIds, strict: true)
        );
    }
}
```

### Pattern 5: Http::fake() with JSON Fixture Files

**What:** Load fixture file in test, pass to `Http::fake()` to simulate Blizzard response.

**When to use:** All Pest feature tests for this phase.

```php
// Source: https://laravel.com/docs/12.x/http-client#testing (verified)
Http::fake([
    'oauth.battle.net/token' => Http::response(
        ['access_token' => 'fake-token', 'token_type' => 'bearer', 'expires_in' => 86400],
        200
    ),
    '*.api.blizzard.com/data/wow/auctions/commodities' => Http::response(
        json_decode(file_get_contents(base_path('tests/Fixtures/blizzard_commodities.json')), true),
        200
    ),
]);
```

### Pattern 6: Asserting Auth Headers in Tests

**What:** Verify token is sent as Bearer header (not query param), and token fetch uses Basic Auth.

```php
// Source: https://laravel.com/docs/12.x/http-client#testing (verified)
Http::assertSent(function (Request $request) {
    return str_contains($request->url(), 'auctions/commodities')
        && $request->hasHeader('Authorization', 'Bearer fake-token');
});
```

### Anti-Patterns to Avoid
- **Credentials as query params:** Blizzard removed query string token support in Sept 2024. Always use `Authorization: Bearer` header on API calls, and Basic Auth on token POST.
- **Fetching token on every request:** Token has 24h TTL; always go through `Cache::remember()` — never call the token endpoint directly in `PriceFetchAction`.
- **Query DB inside PriceFetchAction:** The action receives `$itemIds` as a parameter — the caller (Phase 5 job) handles the DB query. This keeps the action testable without DB setup.
- **Calling `Http::get()` without `withToken()`:** The commodities endpoint requires `Authorization: Bearer {token}` — the endpoint will return 401 otherwise.
- **Passing namespace in URL path:** `namespace` is a query string parameter, not part of the path: `?namespace=dynamic-us`.

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Token caching + TTL management | Custom file-based token store | `Cache::remember()` | Handles expiry, serialization, cache miss atomically |
| HTTP request retries | Manual retry loop | Laravel Http `retry()` method | Built-in, handles backoff, exception-aware |
| Auth header formatting | `base64_encode("$id:$secret")` manually | `withBasicAuth($id, $secret)` | Http facade handles encoding correctly |
| Bearer header | `withHeaders(['Authorization' => "Bearer $token"])` | `withToken($token)` | Cleaner, equivalent, less error-prone |
| Form-encoded body | Manual `http_build_query()` | `asForm()` | Sets correct Content-Type automatically |

**Key insight:** Laravel's Http facade was designed for exactly this pattern. Every step of OAuth2 client credentials (Basic Auth, form POST, bearer header) has a dedicated named method.

---

## Common Pitfalls

### Pitfall 1: Bearer Token in Query String
**What goes wrong:** Request fails with 401 or 403; Blizzard silently rejects the query string token approach.
**Why it happens:** Old Blizzard API tutorials pre-Sept 2024 showed `?access_token=` as a valid alternative.
**How to avoid:** Always use `Http::withToken($token)` which sets `Authorization: Bearer` header.
**Warning signs:** Getting 401 responses even with a valid-looking token string.

### Pitfall 2: Cache Miss on Every Tinker Call
**What goes wrong:** `getToken()` makes a fresh HTTP request each time even within the same test/tinker session.
**Why it happens:** `Cache::remember()` uses the default cache driver — in `testing` environment, Laravel uses the `array` driver which resets per-request. In tinker (local env), file driver persists.
**How to avoid:** Ensure `.env` `CACHE_DRIVER=file` for local. In tests, use `Http::fake()` and assert the token endpoint is called once (not twice) to validate cache hit behavior.

### Pitfall 3: Commodities Response Size / Timeout
**What goes wrong:** 30-second timeout exceeded or memory issues loading 70K+ listings into memory.
**Why it happens:** The commodities endpoint returns a very large JSON payload (~8-15MB). Without a timeout set, Guzzle's default (unlimited) applies.
**How to avoid:** Always chain `->timeout(30)` on the commodities `Http::get()` call (locked decision). In tests, use minimal fixtures (5-10 items).

### Pitfall 4: Namespace Parameter Format
**What goes wrong:** API returns 400 or empty results.
**Why it happens:** Wrong namespace string format — must be `dynamic-us` (not `dynamic_us`, not just `us`).
**How to avoid:** Build it as `"dynamic-{$region}"` where `$region` comes from `config('services.blizzard.region')` which defaults to `'us'`.

### Pitfall 5: Cache::remember() TTL as Hours vs Seconds
**What goes wrong:** Token cached for 82800 hours or 23 seconds instead of 23 hours.
**Why it happens:** Laravel `Cache::remember()` TTL is in **seconds**, not minutes or hours.
**How to avoid:** 23 hours = `23 * 3600 = 82800` seconds. Use the literal `82800` or `now()->addHours(23)` Carbon instance — both work per official docs.

### Pitfall 6: Http::fake() Must Be Called Before Service Instantiation
**What goes wrong:** `Http::fake()` doesn't intercept requests because the service was already instantiated before the fake was set up.
**Why it happens:** Singleton registered in container — if resolved before `Http::fake()`, the service may hold state from real requests.
**How to avoid:** Call `Http::fake([...])` at the top of each test before resolving or calling any service. Use `Cache::forget('blizzard_token')` in `beforeEach` to clear cached tokens between tests.

---

## Code Examples

Verified patterns from official sources:

### Blizzard Token Request (Official curl equivalent in PHP)
```php
// Source: https://us.forums.blizzard.com/en/blizzard/t/oauth2-client-credentials-implementations/131
// Equivalent to: curl -u {client_id}:{client_secret} -d grant_type=client_credentials https://oauth.battle.net/token
$response = Http::withBasicAuth(
    config('services.blizzard.client_id'),
    config('services.blizzard.client_secret')
)->asForm()->timeout(30)->post('https://oauth.battle.net/token', [
    'grant_type' => 'client_credentials',
]);
// $response->json('access_token') => "xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"
// $response->json('expires_in')   => 86400 (24 hours in seconds)
```

### Commodities Endpoint Request
```php
// Source: Blizzard forums + community.developer.battle.net confirmation
// URL format verified: https://us.api.blizzard.com/data/wow/auctions/commodities?namespace=dynamic-us
$response = Http::withToken($token)
    ->timeout(30)
    ->get('https://us.api.blizzard.com/data/wow/auctions/commodities', [
        'namespace' => 'dynamic-us',
    ]);
```

### Commodities Response JSON Structure
```json
{
  "auctions": [
    {
      "id": 12345678,
      "item": {
        "id": 224025
      },
      "unit_price": 150000,
      "quantity": 200,
      "time_left": "VERY_LONG"
    }
  ]
}
```
Key fields for Phase 5:
- `item.id` — the `blizzard_item_id` used to match `WatchedItem`
- `unit_price` — price per unit in copper (integer)
- `quantity` — number of units offered at this price in this listing

### Minimal Test Fixture (tests/Fixtures/blizzard_commodities.json)
```json
{
  "auctions": [
    {"id": 1, "item": {"id": 224025}, "unit_price": 150000, "quantity": 200, "time_left": "VERY_LONG"},
    {"id": 2, "item": {"id": 224025}, "unit_price": 148000, "quantity": 50,  "time_left": "LONG"},
    {"id": 3, "item": {"id": 210781}, "unit_price": 320000, "quantity": 100, "time_left": "VERY_LONG"},
    {"id": 4, "item": {"id": 999999}, "unit_price": 50000,  "quantity": 10,  "time_left": "SHORT"}
  ]
}
```
Items 224025 and 210781 are TWW-era items from the catalog seeder. Item 999999 is an unwatched item — used to verify filtering works.

### Pest Test: Token Cache Hit
```php
// Source: https://laravel.com/docs/12.x/http-client#testing (verified)
it('returns cached token without making an HTTP request on cache hit', function () {
    Http::fake([
        'oauth.battle.net/token' => Http::response(
            ['access_token' => 'test-token', 'expires_in' => 86400],
            200
        ),
    ]);

    Cache::forget('blizzard_token');

    $service = app(BlizzardTokenService::class);
    $token1 = $service->getToken(); // triggers HTTP call
    $token2 = $service->getToken(); // cache hit — no HTTP call

    expect($token1)->toBe('test-token')
        ->and($token2)->toBe('test-token');

    Http::assertSentCount(1); // only one actual HTTP request
});
```

### Pest Test: Bearer Token on Commodity Call
```php
it('sends Authorization Bearer header on commodity fetch', function () {
    Http::fake([
        'oauth.battle.net/token' => Http::response(
            ['access_token' => 'test-token', 'expires_in' => 86400], 200
        ),
        '*.api.blizzard.com/data/wow/auctions/commodities' => Http::response(
            json_decode(file_get_contents(base_path('tests/Fixtures/blizzard_commodities.json')), true),
            200
        ),
    ]);

    Cache::forget('blizzard_token');

    $action = app(PriceFetchAction::class);
    $action([224025]);

    Http::assertSent(function (Request $request) {
        return str_contains($request->url(), 'auctions/commodities')
            && $request->hasHeader('Authorization', 'Bearer test-token');
    });
});
```

---

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| `?access_token=TOKEN` query string | `Authorization: Bearer TOKEN` header | Sept 2024 | Old tutorials break; must use header |
| Per-realm auction endpoints | `/data/wow/auctions/commodities` (region-wide) | WoW 9.2.7 (2022) | One endpoint for all commodity listings |
| Guzzle directly | Laravel Http facade | Laravel 7+ (now standard) | Http::fake() makes testing straightforward |

**Deprecated/outdated:**
- `?access_token=TOKEN` query string: Removed by Blizzard Sept 2024. Any code using this will get 401.
- Per-realm AH commodity queries: Commodities are region-wide since 9.2.7. The new endpoint is `/auctions/commodities`, not `/connected-realm/{id}/auctions`.

---

## Open Questions

1. **Token response `expires_in` variability**
   - What we know: Blizzard officially states 24-hour (86400s) token lifetime
   - What's unclear: Whether `expires_in` could differ (e.g., during maintenance or partial outages)
   - Recommendation: Use the fixed 23-hour (82800s) TTL in `Cache::remember()` regardless of `expires_in` value. This matches the locked decision and is simpler.

2. **`Last-Modified` header on commodities endpoint**
   - What we know: STATE.md flags this as a concern for Phase 6 (DATA-04) — not Phase 4
   - What's unclear: Whether the header is reliably present on all responses
   - Recommendation: Out of scope for this phase. Phase 4 returns raw arrays; Phase 6 adds deduplication. No action needed now.

3. **Retry strategy (Claude's discretion)**
   - What we know: Laravel Http facade has a built-in `retry($times, $sleepMilliseconds)` method
   - What's unclear: Optimal retry count and backoff for a large-payload endpoint with 25-point quota cost
   - Recommendation: Use `->retry(2, 1000)` (2 retries, 1 second apart) as a conservative default. Integrate with Phase 5 job error handling. Avoid over-retrying given the quota cost.

---

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | Pest 3.8 with pest-plugin-laravel 3.2 |
| Config file | `tests/Pest.php` (already configured with RefreshDatabase for Feature suite) |
| Quick run command | `php artisan test --filter BlizzardApi` |
| Full suite command | `php artisan test` |

### Phase Requirements → Test Map

| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| DATA-05 | Token cached for 23 hours; cache hit skips HTTP call | feature | `php artisan test --filter BlizzardTokenServiceTest` | ❌ Wave 0 |
| DATA-05 | New token fetched after cache expiry | feature | `php artisan test --filter BlizzardTokenServiceTest` | ❌ Wave 0 |
| DATA-05 | Token request uses Basic Auth (not query params) | feature | `php artisan test --filter BlizzardTokenServiceTest` | ❌ Wave 0 |
| DATA-05 | Commodity fetch sends Bearer header (not query param) | feature | `php artisan test --filter PriceFetchActionTest` | ❌ Wave 0 |
| DATA-05 | Commodity fetch uses `namespace=dynamic-us` param | feature | `php artisan test --filter PriceFetchActionTest` | ❌ Wave 0 |
| DATA-05 | 401 from Blizzard throws a clear RuntimeException | feature | `php artisan test --filter PriceFetchActionTest` | ❌ Wave 0 |

### Sampling Rate
- **Per task commit:** `php artisan test --filter BlizzardApi`
- **Per wave merge:** `php artisan test`
- **Phase gate:** Full suite green before `/gsd:verify-work`

### Wave 0 Gaps
- [ ] `tests/Fixtures/blizzard_commodities.json` — shared fixture for all commodity response tests
- [ ] `tests/Feature/BlizzardApi/BlizzardTokenServiceTest.php` — covers token caching, Basic Auth, cache hit/miss
- [ ] `tests/Feature/BlizzardApi/PriceFetchActionTest.php` — covers Bearer header, namespace param, filtering, error handling

---

## Sources

### Primary (HIGH confidence)
- [Laravel 12 HTTP Client docs](https://laravel.com/docs/12.x/http-client) — `withBasicAuth()`, `withToken()`, `asForm()`, `timeout()`, `Http::fake()`, `Http::assertSent()`
- [Laravel 12 Cache docs](https://laravel.com/docs/12.x/cache) — `Cache::remember()` TTL format (seconds or Carbon), file driver behavior
- [Laravel 12 Service Container docs](https://laravel.com/docs/12.x/container) — `singleton()` registration in AppServiceProvider

### Secondary (MEDIUM confidence)
- [Blizzard OAuth2 forums - client credentials flow](https://us.forums.blizzard.com/en/blizzard/t/oauth2-client-credentials-implementations/131) — token endpoint URL `oauth.battle.net/token`, Basic Auth format, `grant_type=client_credentials`, confirmed by curl example
- [Blizzard commodities API announcement](https://us.forums.blizzard.com/en/blizzard/t/immediate-change-to-auction-apis-for-commodities-with-927/31522) — URL `us.api.blizzard.com/data/wow/auctions/commodities`, `namespace=dynamic-us`, Bearer header requirement, 25-point quota cost, 1-hour update cadence
- [JackBorah/get-wow-data GitHub](https://github.com/JackBorah/get-wow-data) — commodities JSON structure: `auctions[]` array, `item.id`, `unit_price` (copper integer), `quantity`, `time_left`

### Tertiary (LOW confidence)
- Blizzard token `expires_in` always equals 86400 — assumed from "24-hour token" community consensus; not verified against current official docs directly. Using fixed 82800s TTL as safeguard.

---

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — Laravel Http and Cache facades verified against official Laravel 12 docs
- Architecture: HIGH — patterns follow verified Laravel docs and CONTEXT.md locked decisions exactly
- Blizzard API endpoint/auth: MEDIUM — token endpoint and commodities URL confirmed via multiple community sources; JSON structure confirmed via Python library source and community forum; not verified against live Blizzard API directly
- Pitfalls: HIGH — query string removal confirmed by STATE.md accumulated context and community sources

**Research date:** 2026-03-01
**Valid until:** 2026-04-01 (stable APIs; Blizzard API changes infrequently)
