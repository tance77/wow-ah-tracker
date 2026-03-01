# Roadmap: WoW AH Tracker

## Overview

Build a personal Laravel dashboard that polls the Blizzard Auction House Commodities API every 15 minutes, stores aggregated price snapshots, and presents interactive trend charts with buy/sell signal indicators. The build order is dictated by data dependencies: schema must exist before data is written, data must exist before charts can render, and auth must protect admin functionality from the first commit. Eight phases take the project from empty repo to a running price-monitoring dashboard.

## Phases

**Phase Numbering:**
- Integer phases (1, 2, 3): Planned milestone work
- Decimal phases (2.1, 2.2): Urgent insertions (marked with INSERTED)

Decimal phases appear between their surrounding integers in numeric order.

- [x] **Phase 1: Project Foundation** - Laravel 12 scaffolding, environment config, and database schema (completed 2026-03-01)
- [x] **Phase 2: Authentication** - Single-user login, session persistence, and password reset (completed 2026-03-01)
- [ ] **Phase 3: Item Watchlist Management** - Per-user CRUD for tracked commodity items and thresholds
- [x] **Phase 4: Blizzard API Integration** - OAuth2 token service and commodity price fetch action (completed 2026-03-01)
- [ ] **Phase 5: Data Ingestion Pipeline** - Scheduled job, price aggregation, and append-only snapshot storage
- [ ] **Phase 6: Data Integrity Safeguards** - Deduplication gate, overlap protection, and staleness tracking
- [ ] **Phase 7: Dashboard and Price Charts** - Livewire dashboard, summary cards, and timeframe-toggled line charts
- [ ] **Phase 8: Buy/Sell Signal Indicators** - Threshold-based visual signals derived from rolling price averages

## Phase Details

### Phase 1: Project Foundation
**Goal**: A running Laravel 12 application with correct database schema, environment configuration, and development tooling in place — all retroactively-unfixable schema decisions made correctly before any data is written.
**Depends on**: Nothing (first phase)
**Requirements**: DATA-02, DATA-03
**Success Criteria** (what must be TRUE):
  1. `php artisan serve` starts the app with no errors and the default route responds
  2. `.env` contains `BLIZZARD_CLIENT_ID`, `BLIZZARD_CLIENT_SECRET`, and `BLIZZARD_REGION` keys wired into `config/services.php`
  3. `price_snapshots` migration exists with `min_price`, `avg_price`, `median_price`, and `total_volume` stored as unsigned big integers (copper, not gold)
  4. `watched_items` migration exists with `blizzard_item_id`, `name`, `buy_threshold`, and `sell_threshold` columns
  5. Composite index on `(watched_item_id, polled_at)` exists in the `price_snapshots` migration
**Plans**: 2 plans

Plans:
- [ ] 01-01-PLAN.md — Initialize Laravel 12 project with Livewire 4, Tailwind CSS v4, dev tooling, and Blizzard API credential wiring
- [ ] 01-02-PLAN.md — Create database migrations, Eloquent models, factories, and seeder for watched_items and price_snapshots

### Phase 2: Authentication
**Goal**: Users can securely access the application with their own session, and accounts are protected from unauthorized access.
**Depends on**: Phase 1
**Requirements**: AUTH-01, AUTH-02, AUTH-03, AUTH-04
**Success Criteria** (what must be TRUE):
  1. A user can register a new account with email and password via the registration form
  2. A logged-in user's session persists across browser restarts (remember me / session lifetime)
  3. A logged-in user can click "Log out" from any page and be returned to the login screen with session cleared
  4. A user who has forgotten their password can request a reset link by email and set a new password via that link
  5. All admin and dashboard routes redirect unauthenticated visitors to the login page
**Plans**: 2 plans

Plans:
- [x] 02-01-PLAN.md — Install Breeze Livewire/Volt stack, repair Livewire v4 + Tailwind v4 conflicts, apply WoW dark theme with gold/amber accents to all auth layouts
- [x] 02-02-PLAN.md — Wire route protection, root redirect logic, dashboard placeholder, and comprehensive Pest auth tests; human-verify full flow in browser

### Phase 3: Item Watchlist Management
**Goal**: Each logged-in user can maintain their own independent watchlist of commodity items with per-item buy and sell thresholds, managed through an admin interface.
**Depends on**: Phase 2
**Requirements**: ITEM-01, ITEM-02, ITEM-03, ITEM-04, ITEM-05
**Success Criteria** (what must be TRUE):
  1. A logged-in user can search for a WoW commodity item by name or item ID and add it to their watchlist
  2. A logged-in user can remove an item from their watchlist and it disappears from the list immediately
  3. A logged-in user can set a buy threshold (percentage below average) on any watched item and save it
  4. A logged-in user can set a sell threshold (percentage above average) on any watched item and save it
  5. Two different logged-in users each have completely separate watchlists — items added by one user do not appear in the other's list
**Plans**: 3 plans

Plans:
- [ ] 03-01-PLAN.md — Create CatalogItem model/migration/seeder with TWW crafting materials, add User->watchedItems relationship, add unique constraint on (user_id, blizzard_item_id)
- [ ] 03-02-PLAN.md — Build Watchlist Volt component with catalog combobox, manual ID entry, inline threshold editing, instant remove; wire route, nav link, and dashboard count
- [ ] 03-03-PLAN.md — Write comprehensive Pest test suite for watchlist CRUD and user isolation; human-verify full UI flow in browser

### Phase 4: Blizzard API Integration
**Goal**: The application can obtain and cache a valid Blizzard OAuth2 access token, and can fetch the full commodity listings from the Blizzard Game Data API using the correct request format.
**Depends on**: Phase 1
**Requirements**: DATA-05
**Success Criteria** (what must be TRUE):
  1. Running `php artisan tinker` and calling `app(BlizzardTokenService::class)->getToken()` returns a non-empty string without making an HTTP request on a cache hit
  2. The token request uses Basic Auth (client_id:secret) and sets the `Authorization: Bearer TOKEN` header on commodity API calls — never a query string parameter
  3. A commodity fetch returns the raw listings array from `us.api.blizzard.com/data/wow/auctions/commodities` with the correct `namespace` parameter
  4. A cached token is reused for up to 23 hours; a new token is fetched automatically after the cache TTL expires
**Plans**: TBD

Plans:
- [ ] 04-01: Implement `BlizzardTokenService` with `Cache::remember()` at 23-hour TTL and correct OAuth2 client credentials POST
- [ ] 04-02: Implement `PriceFetchAction` that calls the commodities endpoint with Bearer token header and `namespace=dynamic-us` parameter, returning raw listings filtered to watched item IDs
- [ ] 04-03: Write Pest feature tests for token caching and commodity fetch using mocked HTTP responses

### Phase 5: Data Ingestion Pipeline
**Goal**: The application automatically fetches commodity prices every 15 minutes, aggregates the raw listings into summary metrics, and writes one snapshot row per watched item to the database.
**Depends on**: Phase 3, Phase 4
**Requirements**: DATA-01, DATA-02, DATA-03, DATA-06
**Success Criteria** (what must be TRUE):
  1. Running `php artisan schedule:run` triggers a commodity price fetch without manual intervention
  2. After a successful fetch, one new row per watched item appears in `price_snapshots` with `min_price`, `avg_price`, `median_price`, and `total_volume` all populated as non-zero integers
  3. All prices in the database are stored in copper (integers), never gold (floats or decimals)
  4. The scheduler fires the job every 15 minutes; a second job instance cannot start if the first is still running (14-minute unique lock)
  5. `PriceAggregateAction` correctly computes the median from the frequency distribution of `{quantity, unitPrice}` listing pairs — not a simple array sort
**Plans**: 2 plans

Plans:
- [ ] 05-01-PLAN.md — Implement PriceAggregateAction (frequency-distribution median), FetchCommodityPricesJob (ShouldBeUnique orchestrator), and scheduler wiring in routes/console.php
- [ ] 05-02-PLAN.md — Write Pest feature tests for PriceAggregateAction (pure math) and FetchCommodityPricesJob (full pipeline integration with Http::fake())

### Phase 6: Data Integrity Safeguards
**Goal**: The ingestion pipeline skips duplicate snapshots when Blizzard has not published new data, and the dashboard can detect and display data staleness when Blizzard's API is unresponsive.
**Depends on**: Phase 5
**Requirements**: DATA-04
**Success Criteria** (what must be TRUE):
  1. When the Blizzard API returns the same `Last-Modified` header as the previous successful fetch, no new row is written to `price_snapshots`
  2. After a 15-minute poll cycle where data is unchanged, the `price_snapshots` row count for watched items does not increase
  3. The `price_snapshots` table (or a companion store) records the last `Last-Modified` value so the gate survives an app restart
  4. If `Last-Modified` is absent or unreliable, a fallback response-hash deduplication gate prevents duplicate writes
**Plans**: TBD

Plans:
- [ ] 06-01: Add `last_modified_at` tracking to the ingestion pipeline and implement `Last-Modified` header deduplication gate in `FetchCommodityPricesJob`
- [ ] 06-02: Implement response-hash fallback deduplication for cases where `Last-Modified` is absent; write tests for both gate strategies

### Phase 7: Dashboard and Price Charts
**Goal**: A logged-in user can see all of their watched items on a dashboard at a glance, with interactive line charts showing price history over selectable timeframes.
**Depends on**: Phase 5, Phase 3
**Requirements**: DASH-01, DASH-02, DASH-03, DASH-06
**Success Criteria** (what must be TRUE):
  1. The dashboard shows a summary card for each of the logged-in user's watched items displaying the current (most recent) price and trend direction (up/down/flat)
  2. Clicking a watched item opens a line chart showing price history for that item using ApexCharts
  3. The user can toggle the chart timeframe between 24h, 7d, and 30d, and the chart updates reactively without a full page reload
  4. The dashboard only shows the logged-in user's watched items — a second user sees only their own items
  5. Prices on the dashboard are displayed in gold (not copper), formatted as "X,XXX g XX s XX c"
**Plans**: TBD

Plans:
- [ ] 07-01: Install ApexCharts and build the Livewire `Dashboard` component with date-range filtering and bounded `price_snapshots` queries
- [ ] 07-02: Build per-item summary cards with current price (median), trend direction arrow, and last-updated timestamp
- [ ] 07-03: Implement 24h/7d/30d timeframe toggle as a reactive Livewire property that re-renders the chart without page reload
- [ ] 07-04: Add gold/silver/copper price formatter and apply throughout all dashboard display points

### Phase 8: Buy/Sell Signal Indicators
**Goal**: The dashboard surfaces buy and sell opportunities by comparing each item's current price to the user's configured thresholds, with clear visual indicators when a threshold is breached.
**Depends on**: Phase 7, Phase 3
**Requirements**: DASH-04, DASH-05
**Success Criteria** (what must be TRUE):
  1. When an item's current price is below the user's buy threshold percentage relative to the rolling average, a visible "BUY" badge or highlight appears on that item's card
  2. When an item's current price is above the user's sell threshold percentage relative to the rolling average, a visible "SELL" badge or highlight appears on that item's card
  3. Items at normal price show no signal badge — signals are only shown when a threshold is actively breached
  4. The signal correctly uses median price (not min price) to avoid manipulation by single low-quantity listings
**Plans**: TBD

Plans:
- [ ] 08-01: Implement rolling average computation in the Dashboard component (N-day configurable window from stored snapshots)
- [ ] 08-02: Add buy/sell signal badge rendering to item summary cards based on threshold comparison against rolling median average

## Progress

**Execution Order:**
Phases execute in numeric order: 1 → 2 → 3 → 4 → 5 → 6 → 7 → 8

| Phase | Plans Complete | Status | Completed |
|-------|----------------|--------|-----------|
| 1. Project Foundation | 2/2 | Complete   | 2026-03-01 |
| 2. Authentication | 2/2 | Complete   | 2026-03-01 |
| 3. Item Watchlist Management | 2/3 | In Progress|  |
| 4. Blizzard API Integration | 3/3 | Complete   | 2026-03-01 |
| 5. Data Ingestion Pipeline | 0/2 | Not started | - |
| 6. Data Integrity Safeguards | 0/2 | Not started | - |
| 7. Dashboard and Price Charts | 0/4 | Not started | - |
| 8. Buy/Sell Signal Indicators | 0/2 | Not started | - |
