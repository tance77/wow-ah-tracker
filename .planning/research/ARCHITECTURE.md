# Architecture Research

**Domain:** WoW Auction House Price Tracker (Laravel)
**Researched:** 2026-03-01
**Confidence:** HIGH

## Standard Architecture

### System Overview

```
┌─────────────────────────────────────────────────────────────────┐
│                        Web Layer                                 │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │  Browser → Auth Middleware → Dashboard / Admin Routes     │   │
│  │            (single user, simple login)                    │   │
│  └──────────────────────────────────────────────────────────┘   │
├─────────────────────────────────────────────────────────────────┤
│                     Application Layer                            │
│  ┌──────────────┐  ┌─────────────────┐  ┌──────────────────┐   │
│  │  Livewire    │  │  Controllers    │  │  Artisan         │   │
│  │  Components  │  │  (Admin CRUD)   │  │  (manual trigger)│   │
│  └──────┬───────┘  └────────┬────────┘  └────────┬─────────┘   │
│         │                   │                     │             │
│  ┌──────▼───────────────────▼─────────────────────▼──────────┐  │
│  │               Actions / Services                           │  │
│  │  BlizzardTokenService  |  PriceFetchAction                 │  │
│  │  PriceAggregateAction  |  WatchedItemService               │  │
│  └──────────────────────────────────┬──────────────────────┘  │
├────────────────────────────────────┼────────────────────────────┤
│                 Queue Layer         │                            │
│  ┌──────────────────────────────────▼──────────────────────┐   │
│  │              Laravel Scheduler (every 15 min)            │   │
│  │              → dispatches FetchCommodityPricesJob        │   │
│  └──────────────────────────────────────────────────────────┘   │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │              Queue Worker (database / redis)              │   │
│  │              processes FetchCommodityPricesJob            │   │
│  └──────────────────────────────────────────────────────────┘   │
├─────────────────────────────────────────────────────────────────┤
│                     External Layer                               │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │              Blizzard Game Data API                       │   │
│  │              OAuth2 token endpoint + commodities endpoint │   │
│  └──────────────────────────────────────────────────────────┘   │
├─────────────────────────────────────────────────────────────────┤
│                     Data Layer                                   │
│  ┌────────────────┐  ┌───────────────┐  ┌──────────────────┐   │
│  │  watched_items │  │  price_snaps  │  │  Laravel Cache   │   │
│  │  (config)      │  │  (time series)│  │  (OAuth2 token)  │   │
│  └────────────────┘  └───────────────┘  └──────────────────┘   │
└─────────────────────────────────────────────────────────────────┘
```

### Component Responsibilities

| Component | Responsibility | Typical Implementation |
|-----------|----------------|------------------------|
| Laravel Scheduler | Fires poll trigger every 15 minutes | `routes/console.php` Schedule::job() |
| FetchCommodityPricesJob | Single unit of polling work, retryable | `app/Jobs/FetchCommodityPricesJob.php` |
| BlizzardTokenService | Obtain + cache access token (24h TTL) | `app/Services/BlizzardTokenService.php` |
| PriceFetchAction | Call Blizzard API, filter to watched items | `app/Actions/PriceFetchAction.php` |
| PriceAggregateAction | Derive min/avg/median/volume from raw listings | `app/Actions/PriceAggregateAction.php` |
| WatchedItemService | CRUD for managed item list | `app/Services/WatchedItemService.php` |
| Dashboard (Livewire) | Render charts, date range, signals | `app/Livewire/Dashboard.php` |
| Admin Controller | Add/remove watched items | `app/Http/Controllers/AdminController.php` |

## Recommended Project Structure

```
app/
├── Actions/
│   ├── PriceFetchAction.php        # calls API, returns raw commodity data
│   └── PriceAggregateAction.php    # derives metrics from raw listings
├── Jobs/
│   └── FetchCommodityPricesJob.php # queued job dispatched by scheduler
├── Services/
│   ├── BlizzardTokenService.php    # OAuth2 client credentials + caching
│   └── WatchedItemService.php      # item management business logic
├── Models/
│   ├── WatchedItem.php             # items to track (id, name, blizzard_item_id)
│   └── PriceSnapshot.php           # one row per item per poll
├── Livewire/
│   ├── Dashboard.php               # main chart view (date range, item select)
│   └── AdminItems.php              # watched item management UI
├── Http/
│   └── Controllers/
│       └── Auth/LoginController.php
database/
├── migrations/
│   ├── create_watched_items_table.php
│   └── create_price_snapshots_table.php
routes/
├── web.php                         # dashboard, admin, auth routes
└── console.php                     # Schedule::job(FetchCommodityPricesJob::class)->everyFifteenMinutes()
```

### Structure Rationale

- **Actions/:** Single-responsibility classes for discrete operations (fetch, aggregate). Reusable from jobs, artisan commands, and tests without going through the HTTP layer.
- **Services/:** Classes with multiple related methods or stateful concerns (token caching, item management).
- **Jobs/:** Thin orchestrator — calls actions, handles retry/failure logic. Keeps action classes testable in isolation.
- **Livewire/:** Self-contained components; each owns its own data query and chart config. Avoids over-fetching in controllers.

## Architectural Patterns

### Pattern 1: Scheduler → Job → Actions (Thin Job, Fat Action)

**What:** The scheduler fires a queued job every 15 minutes. The job is a thin orchestrator that calls discrete action classes. Actions contain the actual logic.

**When to use:** Always for this project — ensures actions are independently testable and reusable from artisan commands.

**Trade-offs:** Slightly more files than putting logic directly in the job. Worth it: jobs can be retried without retesting action logic.

**Example:**
```php
// app/Jobs/FetchCommodityPricesJob.php
class FetchCommodityPricesJob implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public $uniqueFor = 840; // 14 minutes — prevents overlap at 15-min boundary

    public function handle(
        PriceFetchAction $fetch,
        PriceAggregateAction $aggregate,
        WatchedItemService $items
    ): void {
        $watchedItems = $items->getActive();
        $rawListings  = $fetch->execute($watchedItems);

        foreach ($rawListings as $itemId => $listings) {
            $snapshot = $aggregate->execute($itemId, $listings);
            PriceSnapshot::create($snapshot);
        }
    }
}

// routes/console.php
Schedule::job(new FetchCommodityPricesJob)->everyFifteenMinutes();
```

### Pattern 2: Token-Cached OAuth2 Service (Cache::remember with TTL)

**What:** BlizzardTokenService requests a token on cache miss, stores it in Laravel cache with a TTL slightly shorter than the 24-hour Blizzard token lifetime. All API calls obtain the token through this service.

**When to use:** Any time a third-party API uses short-lived tokens with client credentials flow.

**Trade-offs:** Requires a cache backend (file is fine for single-server; Redis preferred). Token expiry mismatch is the main failure mode — mitigated by using 23-hour TTL.

**Example:**
```php
// app/Services/BlizzardTokenService.php
class BlizzardTokenService
{
    public function getToken(): string
    {
        return Cache::remember('blizzard_access_token', now()->addHours(23), function () {
            $response = Http::withBasicAuth(
                config('services.blizzard.client_id'),
                config('services.blizzard.client_secret')
            )->asForm()->post('https://oauth.battle.net/token', [
                'grant_type' => 'client_credentials',
            ]);

            return $response->json('access_token');
        });
    }
}
```

### Pattern 3: Price Snapshot Table (Append-Only Time Series)

**What:** Each poll writes one row per watched item to `price_snapshots`. The table is append-only — no updates. Dashboard queries aggregate from this table over a time window.

**When to use:** Any price/metric tracking that needs historical trend lines.

**Trade-offs:** Row count grows with time (96 rows/day per item at 15-min intervals = ~700 rows/week for 7 items). Manageable at this scale without partitioning; prune old data via scheduled job if needed.

**Schema:**
```php
// price_snapshots migration
Schema::create('price_snapshots', function (Blueprint $table) {
    $table->id();
    $table->foreignId('watched_item_id')->constrained()->cascadeOnDelete();
    $table->unsignedBigInteger('min_price');   // copper, integer avoids float rounding
    $table->unsignedBigInteger('avg_price');
    $table->unsignedBigInteger('median_price');
    $table->unsignedInteger('total_volume');   // quantity available
    $table->timestamp('polled_at');            // when Blizzard API was called
    $table->index(['watched_item_id', 'polled_at']); // composite for range queries
});
```

## Data Flow

### Poll Flow (Background, Every 15 Minutes)

```
Laravel Scheduler (cron every minute checks schedule)
    ↓ (everyFifteenMinutes match)
FetchCommodityPricesJob dispatched to queue
    ↓
Queue Worker picks up job
    ↓
BlizzardTokenService::getToken()
    ↓ (cache hit: return cached token / miss: POST to oauth.battle.net)
PriceFetchAction::execute($watchedItems)
    ↓ (GET /data/wow/auctions/commodities with Bearer token)
Raw listings array (all commodity listings in region)
    ↓ (filter: keep only watched item IDs)
PriceAggregateAction::execute($itemId, $listings)
    ↓ (compute min, avg, median, volume from listing quantities + unit prices)
PriceSnapshot::create([...])
    ↓
price_snapshots table row written
```

### Dashboard Flow (User Request)

```
Browser GET /dashboard
    ↓
Auth middleware (session check)
    ↓
Livewire Dashboard component renders
    ↓
Component queries: SELECT * FROM price_snapshots
                   WHERE watched_item_id IN (...)
                   AND polled_at >= NOW() - INTERVAL 7 DAY
                   ORDER BY polled_at ASC
    ↓
PHP formats into Chart.js dataset (labels + data arrays)
    ↓
Blade template renders chart canvas
    ↓
Chart.js draws line chart in browser
```

### Key Data Flows

1. **Token refresh:** BlizzardTokenService → Laravel Cache (file/redis) → TTL 23h → auto-refreshed on next request after expiry
2. **Price ingestion:** Blizzard API (all ~10k commodity listings) → filter to 6-7 watched items → aggregate metrics → single row per item written to DB
3. **Chart rendering:** price_snapshots (time-windowed query) → PHP arrays → Livewire → Blade → Chart.js (client-side render)
4. **Admin item changes:** Livewire AdminItems form → WatchedItemService::create/delete → watched_items table → next poll picks up new item list

## Integration Points

### External Services

| Service | Integration Pattern | Notes |
|---------|---------------------|-------|
| Blizzard OAuth (`oauth.battle.net/token`) | HTTP POST with Basic Auth (client_id:secret), cache result 23h | Token is 24h, use 23h TTL to avoid expiry during a poll. Authorization: Bearer header only — query string no longer accepted. |
| Blizzard Game Data API (`us.api.blizzard.com`) | HTTP GET with Bearer token header | Single commodities endpoint returns all region listings. Filter client-side to watched items. Rate limit is ~36k/hr — 15-min polling is negligible. |

### Internal Boundaries

| Boundary | Communication | Notes |
|----------|---------------|-------|
| Scheduler → Job | `Schedule::job()` dispatch | Job implements `ShouldBeUnique` to prevent 15-min overlap |
| Job → Actions | Constructor injection via Laravel container | Actions are stateless; inject BlizzardTokenService via handle() |
| Actions → Database | Eloquent models | Direct model usage acceptable at this scale — no repository layer needed |
| Livewire → Database | Direct Eloquent queries in component | Keep queries in `mount()` and reactive computed properties |
| Config → Services | `.env` + `config/services.php` | `BLIZZARD_CLIENT_ID`, `BLIZZARD_CLIENT_SECRET`, `BLIZZARD_REGION` |

## Anti-Patterns

### Anti-Pattern 1: Calling Blizzard API Directly from Scheduler Closure

**What people do:** Put HTTP calls inside `Schedule::call(function() { Http::get(...); })` in `routes/console.php`.

**Why it's wrong:** Scheduler runs on the main process. Long API calls block subsequent scheduled tasks. No retry on failure. No monitoring visibility.

**Do this instead:** Scheduler dispatches a queued job. Job handles the HTTP call with retry logic and timeout settings.

### Anti-Pattern 2: Storing Raw Listings Instead of Aggregated Snapshots

**What people do:** Write all commodity listing rows to the database (thousands of rows per poll).

**Why it's wrong:** Blizzard's commodities endpoint returns listings for all region commodities — potentially 10,000+ rows per call. Storing raw listings for even 7 items still multiplies row count by listing depth (hundreds per item). Dashboard queries become slow. No business value in individual listing rows after the poll.

**Do this instead:** Aggregate in PHP immediately after fetch (min price, avg, median, volume). Write one summary row per item per poll to `price_snapshots`.

### Anti-Pattern 3: Fetching Blizzard Token on Every API Call

**What people do:** POST to `oauth.battle.net/token` before every commodities API request.

**Why it's wrong:** Doubles API calls. Adds latency to every poll. Blizzard tokens last 24 hours — refreshing every 15 minutes is wasteful and may trigger rate limits on the auth endpoint.

**Do this instead:** Cache token with `Cache::remember()` using a 23-hour TTL. Re-request only on cache miss or 401 response.

### Anti-Pattern 4: Over-Engineering with Repository Layer

**What people do:** Add Repository interfaces between Eloquent and service/action classes for "flexibility."

**Why it's wrong:** This project has two models (WatchedItem, PriceSnapshot) and will never swap databases. Repository layer adds indirection with zero practical benefit for a single-user app this size.

**Do this instead:** Use Eloquent models directly in actions and Livewire components. Introduce repositories only when a concrete need (swap DB, complex query isolation) arises.

## Scaling Considerations

| Scale | Architecture Adjustments |
|-------|--------------------------|
| Single user (current) | SQLite or MySQL, file cache, database queue driver — all fine |
| 10-50 users (if ever public) | Switch queue to Redis, add Horizon for monitoring, add database indexes |
| 100+ users | TimescaleDB or PostgreSQL for price_snapshots hypertables, Redis cache, separate queue worker process |

### Scaling Priorities

1. **First bottleneck:** Dashboard chart queries slow down as price_snapshots grows. Fix: add composite index on `(watched_item_id, polled_at)` (already in schema above), then add data pruning job to delete rows older than 90 days.
2. **Second bottleneck:** Queue worker contention if multiple jobs run. Fix: Redis queue driver + Laravel Horizon. Not relevant at current scope.

## Build Order Implications

The component dependency graph drives phase order:

```
BlizzardTokenService          (no dependencies)
    ↓
PriceFetchAction              (requires token service)
    ↓
PriceAggregateAction          (requires raw data shape from fetch)
    ↓
FetchCommodityPricesJob       (requires fetch + aggregate actions)
    ↓
Scheduler wiring              (requires job exists)
    ↓
PriceSnapshot model/migration (required before job can persist data)
    ↓
WatchedItem model + Admin UI  (required for job to know what to fetch)
    ↓
Dashboard + Charts            (requires data in price_snapshots)
```

**Recommended build phases:**
1. Foundation: auth, database migrations, watched_items CRUD, config
2. Data ingestion: token service, fetch action, aggregate action, job, scheduler
3. Dashboard: Livewire chart component, date range, buy/sell signal indicators

## Sources

- Laravel Scheduling (official docs, v12): https://laravel.com/docs/12.x/scheduling
- Laravel Queues (official docs, v12): https://laravel.com/docs/12.x/queues
- Blizzard OAuth2 client credentials: https://us.forums.blizzard.com/en/blizzard/t/oauth2-client-credentials-implementations/131
- Blizzard token expiry (24h): https://github.com/nextauthjs/next-auth/issues/6853 (MEDIUM confidence — community source, aligns with documented expires_in: 86399)
- Blizzard token must be in Authorization header (not query string): https://us.forums.blizzard.com/en/blizzard/t/upcoming-changes-to-battlenet%E2%80%99s-api-gateway/51561
- Laravel Action pattern community consensus: https://dev.to/tegos/laravel-actions-and-services-360d
- Laravel Livewire + Chart.js integration: https://www.georgebuckingham.com/laravel-livewire-chart-js-realtime/
- Time series aggregation in Laravel: https://timothepearce.github.io/laravel-time-series-docs/

---
*Architecture research for: WoW AH Tracker (Laravel commodity price dashboard)*
*Researched: 2026-03-01*
