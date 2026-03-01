# Project Research Summary

**Project:** WoW Auction House Commodity Price Tracker
**Domain:** Game economy dashboard — time-series price tracking via external API
**Researched:** 2026-03-01
**Confidence:** HIGH (stack, architecture); MEDIUM (features, pitfalls)

## Executive Summary

This is a read-heavy personal web dashboard that polls the Blizzard Game Data API every 15 minutes, stores aggregated price snapshots, and visualizes commodity price history over time to surface buy/sell opportunities. The established approach for this class of tool is: a scheduled background job handles all data ingestion, an append-only time-series table stores aggregated snapshots (never raw listings), and a server-rendered reactive dashboard delivers the visualization. All major competitors (TSM, Booty Bay Broker, Saddlebag Exchange) follow this pattern, confirming the design is well-understood and achievable with the Laravel stack.

The recommended approach is Laravel 12 + Livewire 4 + SQLite + database-backed queues. This stack avoids unnecessary infrastructure (no Redis, no separate API layer) while delivering all required reactivity for a single-user dashboard. The architecture cleanly separates concerns: `BlizzardTokenService` handles OAuth2 with caching, `PriceFetchAction` and `PriceAggregateAction` handle data ingestion, `FetchCommodityPricesJob` orchestrates the pipeline, and Livewire components drive the dashboard. This is a thin, maintainable codebase with a clear build order driven by data dependencies.

The most significant risks are all in data integrity and API integration — not architecture. Three pitfalls are retroactively unfixable if skipped: storing prices as floats instead of integers, storing only min price instead of min/median/avg/volume, and missing the `Last-Modified` deduplication gate. These must be addressed in schema design and the first job implementation, before any data is written to the database. The Blizzard API also has one breaking change (token via Authorization header only, since Sept 2024) that must be implemented correctly from the first HTTP request.

## Key Findings

### Recommended Stack

Laravel 12 with Livewire 4, Tailwind CSS v4, SQLite, and database-backed queues is the correct stack for this project. It is minimal, matches the single-user personal tool scope, and avoids infrastructure that would add complexity without benefit at this scale. Livewire 4's Islands feature allows isolated chart refresh without a separate SPA build pipeline. ApexCharts is the right charting library — SVG-based, built-in datetime axis and zoom/pan, actively maintained. The `livewire-charts` wrapper is a candidate but requires Livewire 4 compatibility verification before use; a thin `@script` block with `$wire.on()` is the documented fallback.

**Core technologies:**
- Laravel 12: PHP web framework — current release, built-in scheduler and queue system, PHP 8.2–8.4
- Livewire 4.2: Reactive UI — server-rendered, no SPA overhead, Islands feature for chart isolation
- Tailwind CSS 4.2: Utility CSS — ships with Laravel 12, CSS-first config, Vite plugin only
- SQLite: Primary store — zero-configuration, trivially backed up, correct for single-writer read-heavy workload
- Laravel database queue driver: Async job processing — no Redis required at 1 job per 15 minutes
- ApexCharts 5.6: Charting — interactive time-series, SVG, framework-agnostic
- Laravel HTTP Client: Blizzard API — all third-party Blizzard PHP SDKs are unmaintained (last updated 2017–2023)
- Laravel Breeze (Blade stack): Auth scaffolding — minimal, removes unneeded registration flow

### Expected Features

Competitor analysis across TSM, Booty Bay Broker, WoW Price Hub, Saddlebag Exchange, and Undermine Exchange produced a clear MVP definition. The dependency graph is strict: polling infrastructure must exist before any data can be stored, and data must accumulate before signals (buy/sell indicators, averages) are meaningful.

**Must have (table stakes):**
- Blizzard API poller (15-min schedule) — without this, nothing works
- Price history storage (min, avg, median, volume per snapshot) — store volume from day one; cannot backfill
- Watched item admin CRUD — avoid hard-coding item IDs; enables threshold configuration
- Single-user auth — protects admin UI and personal data
- Price history line chart with configurable timeframe (24h / 7d / 30d) — core value delivery
- Buy/sell signal indicators (% from rolling average) — the "spot the opportunity" feature
- Dashboard summary card per item (price + trend direction) — at-a-glance overview

**Should have (competitive differentiators):**
- Volume / supply chart overlay — low volume + low price signals scarcity spike
- Per-item threshold configuration in UI — different commodities have different volatility baselines
- Percent-from-average label on chart — more actionable than raw gold amount
- Discord webhook alerts for threshold breach — add when dashboard-checking becomes the bottleneck

**Defer (v2+):**
- Crafting profit calculator — requires tracking crafted item sell prices separately; doubles data model complexity
- Multi-user support — requires auth overhaul and data isolation
- Additional item categories (gear, pets, mounts) — different data shape, different API endpoints
- Email / push notifications — external service integration, notification deduplication; out of scope for v1

### Architecture Approach

The architecture has three distinct layers with a clear data flow: the scheduler fires `FetchCommodityPricesJob` every 15 minutes, the job calls `PriceFetchAction` (Blizzard API) and `PriceAggregateAction` (compute metrics from raw listings), then writes one aggregated row per watched item to `price_snapshots`. The dashboard Livewire component queries this table with a date-range filter and renders charts. Token management is isolated in `BlizzardTokenService` with `Cache::remember()` at 23-hour TTL. No repository layer is needed — Eloquent models directly in actions and components is the correct approach at this scale.

**Major components:**
1. `BlizzardTokenService` — OAuth2 client credentials flow with cache (23h TTL); single source of truth for API access
2. `FetchCommodityPricesJob` + `PriceFetchAction` + `PriceAggregateAction` — thin job orchestrates stateless actions; actions independently testable
3. `price_snapshots` table — append-only time series; composite index on `(watched_item_id, polled_at)` mandatory from day one
4. `watched_items` table + admin UI — drives which item IDs the poller looks for; supports configurable thresholds
5. Livewire Dashboard component — queries price history with date bounds, renders ApexCharts, computes buy/sell signals

### Critical Pitfalls

1. **OAuth token as URL query string** — Blizzard permanently removed this Sept 2024. Always use `Authorization: Bearer TOKEN` header. Must be correct from the first HTTP request; cannot be a retrofit.
2. **Float/decimal columns for copper prices** — WoW prices are integers in copper units. Storing as float introduces rounding errors that compound in aggregations and cannot be fixed retroactively. Use `BIGINT UNSIGNED`, convert to gold only at display time.
3. **Missing `Last-Modified` deduplication gate** — Blizzard updates the commodity snapshot hourly at most. Polling every 15 minutes without this check writes 3x duplicate rows. The gate must be part of the initial job design. Blizzard-side outages (documented: 24-hour gaps with HTTP 200 responses) require storing `Last-Modified` to show a staleness warning on the dashboard.
4. **Storing only min_price per snapshot** — Min price is manipulable by single-listing market manipulation. Schema must store `price_min`, `price_median`, `price_avg`, and `total_volume` from day one. Historical median cannot be reconstructed from stored min.
5. **No overlap protection on scheduled job** — If a poll takes longer than 15 minutes, two job instances overlap and write duplicate data. `ShouldBeUnique` (14-minute `$uniqueFor`) on the job class prevents this with one declaration.

## Implications for Roadmap

Based on research, the component dependency graph dictates a 3-phase build order. Data must exist before charts, charts must exist before signals, and auth must exist before admin functionality is accessible.

### Phase 1: Foundation — Auth, Config, and Data Model

**Rationale:** Everything downstream depends on the schema and the watched item list. Auth protects admin functionality from day one. This phase establishes the data contract that all subsequent phases write to and read from. Building schema first prevents the retroactively-unfixable pitfalls (float columns, missing metrics columns).

**Delivers:** Working login, watched item CRUD admin UI, fully specified database schema (`watched_items` + `price_snapshots` with all required columns and indexes), project configuration (`BLIZZARD_CLIENT_ID`, `BLIZZARD_CLIENT_SECRET` in `.env` and `config/services.php`).

**Addresses (from FEATURES.md):** Watched item management (admin CRUD), single-user auth.

**Avoids (from PITFALLS.md):** Float price columns, missing metrics columns — schema is defined before any data is inserted. Admin routes behind auth middleware prevents unauthenticated access.

### Phase 2: Data Ingestion — API Integration and Scheduled Polling

**Rationale:** The dashboard has nothing to show without data. The polling pipeline is the system's core dependency. This phase must be correct from the start on API integration (token header, namespace param, `Last-Modified` gate, overlap protection) because errors here create irrecoverable data gaps.

**Delivers:** `BlizzardTokenService` with token caching, `PriceFetchAction` and `PriceAggregateAction`, `FetchCommodityPricesJob` with `ShouldBeUnique` and `Last-Modified` deduplication, scheduler wiring in `routes/console.php`, Pest feature tests with mocked Blizzard HTTP calls.

**Uses (from STACK.md):** Laravel HTTP Client, Laravel Scheduler, Laravel database queue driver, `spatie/laravel-data` (optional, for typed API response mapping).

**Implements (from ARCHITECTURE.md):** Scheduler → Job → Actions pattern; Token-Cached OAuth2 pattern; Append-Only Snapshot pattern.

**Avoids (from PITFALLS.md):** Token as query string, token not cached, missing namespace param, no `Last-Modified` gate, no overlap protection, no failed-job alerting.

### Phase 3: Dashboard — Visualization and Buy/Sell Signals

**Rationale:** This phase delivers the user-facing value. It requires data in `price_snapshots` from Phase 2. Livewire components query with date-range bounds (never unbounded), compute signals from rolling averages, and render ApexCharts. Buy/sell signal indicators are v1 features, not v1.x — they are the primary "spot the opportunity" value this tool promises.

**Delivers:** Livewire Dashboard component with per-item summary cards, ApexCharts line chart with 24h/7d/30d toggle, buy/sell signal badges (% from rolling N-day average, configurable threshold), `Last-Modified` staleness indicator, price displayed in gold (never copper).

**Uses (from STACK.md):** Livewire 4, ApexCharts 5.6 (via `livewire-charts` if Livewire 4 compatible, or direct `@script` block), Tailwind CSS v4.

**Implements (from ARCHITECTURE.md):** Livewire Dashboard component with date-range filtering, composite index query pattern.

**Avoids (from PITFALLS.md):** Min price displayed as "current price" (use median), no staleness indicator, unbounded chart history query, copper displayed on dashboard, no "last updated" timestamp.

### Phase Ordering Rationale

- Schema first because float columns and missing metrics are irrecoverable after data is written.
- Polling pipeline before dashboard because the dashboard needs data to render.
- Auth alongside foundation because admin routes must be protected before watched item CRUD is functional.
- Signals in Phase 3 (not deferred to v1.x) because they require only rolling average computation from already-stored data — implementation cost is LOW and user value is HIGH.
- Volume tracking is part of the Phase 2 schema and job, not Phase 3 — it costs nothing to store at insert time but is impossible to backfill.

### Research Flags

Phases likely needing deeper research during planning:
- **Phase 2:** `Last-Modified` header behavior on the Blizzard commodities endpoint needs verification against live API. Community sources (MEDIUM confidence) confirm it exists but behavior during Blizzard-side outages is documented only from forum posts. Validate against a live API call before finalizing the deduplication implementation.
- **Phase 3:** `asantibanez/livewire-charts` Livewire 4 compatibility is unconfirmed as of research date. Before planning Phase 3, check the GitHub repo for v4 support. If unsupported, the fallback (`@script` block with `$wire.on()`) is well-documented and standard — no research needed for the fallback.

Phases with standard patterns (skip research-phase):
- **Phase 1:** Laravel Breeze installation, Eloquent migrations, simple CRUD — fully documented, standard patterns. No research needed.
- **Phase 2:** Laravel Scheduler, queue jobs, HTTP client — all first-party Laravel, fully documented, HIGH confidence sources.
- **Phase 3:** Livewire component structure, date-range filtering, Tailwind utility layout — standard patterns. Only uncertainty is the charting library compatibility (flagged above).

## Confidence Assessment

| Area | Confidence | Notes |
|------|------------|-------|
| Stack | HIGH | Core: Laravel 12, Livewire 4.2.1, Tailwind v4, SQLite all verified via official docs and Packagist. Only uncertainty: `livewire-charts` Livewire 4 compatibility unconfirmed. |
| Features | MEDIUM | Competitor analysis across 6+ tools; feature judgments are inferred from live pages and descriptions, not user surveys. MVP definition is well-reasoned but not externally validated. |
| Architecture | HIGH | Component boundaries, data flow, and patterns all grounded in official Laravel docs. Blizzard API token behavior confirmed via official Blizzard developer forums. |
| Pitfalls | MEDIUM | Critical API pitfalls (token header, namespace param) confirmed via official Blizzard forums with specific dated announcements. Data integrity pitfalls (float columns, min-only storage) from community sources consistent with documented API behavior. |

**Overall confidence:** HIGH — sufficient to proceed to roadmap and requirements without additional research.

### Gaps to Address

- **`livewire-charts` Livewire 4 support:** Verify before Phase 3 planning. If unsupported, use ApexCharts directly with `@script` block — the fallback is well-documented and adds minimal complexity.
- **`Last-Modified` header on commodities endpoint:** Validate in a live API test during Phase 2. If the header is absent or unreliable, switch to response-hash deduplication (hash the raw JSON body; only store if hash differs from the previous snapshot). The detection mechanism differs but the gate remains.
- **Blizzard API rate limits at scale:** At 96 requests/day the app is well within limits. If items grow beyond ~50 watched items, re-evaluate. Not a concern for current scope.
- **Median calculation from raw listings:** The Blizzard commodities response returns all listings as `[{quantity, unitPrice}]` pairs, not individual unit rows. Median must be computed from the frequency distribution (expand quantity into virtual rows, find midpoint), not a simple sort-and-pick. Verify the `PriceAggregateAction` implementation handles this correctly during Phase 2.

## Sources

### Primary (HIGH confidence)
- [Laravel 12 Release Notes](https://laravel.com/docs/12.x/releases) — version, PHP requirements, support timeline
- [Laravel 12 Queue Docs](https://laravel.com/docs/12.x/queues) — database driver recommendation, `ShouldBeUnique` behavior
- [Laravel 12 Scheduling Docs](https://laravel.com/docs/12.x/scheduling) — `withoutOverlapping()`, `everyFifteenMinutes()`
- [Livewire v4.2.1 on Packagist](https://packagist.org/packages/livewire/livewire) — latest version, release date 2026-02-28
- [Tailwind CSS v4 Vite Installation](https://tailwindcss.com/docs/installation/using-vite) — v4 Vite plugin approach
- [Blizzard API Gateway Changes — Blizzard Forums](https://us.forums.blizzard.com/en/blizzard/t/upcoming-changes-to-battlenet%E2%80%99s-api-gateway/51561) — OAuth query string deprecation, Sept 2024 deadline
- [Commodities API Change — Blizzard Forums](https://us.forums.blizzard.com/en/blizzard/t/immediate-change-to-auction-apis-for-commodities-with-927/31522) — commodities endpoint costs 25 API points; hourly update frequency; `Last-Modified` behavior

### Secondary (MEDIUM confidence)
- [Livewire 4 Official Blog Post](https://laravel.com/blog/livewire-4-is-here-the-artisan-of-the-day-is-caleb-porzio) — Islands feature confirmation
- [ApexCharts npm](https://www.npmjs.com/package/apexcharts) — v5.6.0 current
- [livewire-charts GitHub](https://github.com/asantibanez/livewire-charts) — ApexCharts Livewire wrapper; Livewire 4 compat unconfirmed
- [Blizzard 24-hour AH Outage Thread](https://us.forums.blizzard.com/en/wow/t/resolved-wow-ah-commodities-api-has-not-reported-a-result-in-24-hours/1961522) — staleness outage with HTTP 200 confirmed
- [TradeSkillMaster](https://tradeskillmaster.com/), [Booty Bay Broker](https://bootybaybroker.com/), [Saddlebag Exchange](https://saddlebagexchange.com/wow), [WoW Price Hub](https://wowpricehub.com/) — competitor feature analysis

### Tertiary (LOW confidence)
- [Undermine Exchange](https://undermine.exchange/) — in maintenance at research time; features inferred from search results
- Community sources on hourly snapshot frequency, copper-as-integer storage pattern, min-price manipulation patterns

---
*Research completed: 2026-03-01*
*Ready for roadmap: yes*
