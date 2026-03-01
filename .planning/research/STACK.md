# Stack Research

**Domain:** WoW Auction House commodity price tracker (single-user Laravel web app)
**Researched:** 2026-03-01
**Confidence:** HIGH (core framework, Tailwind, Livewire verified via official docs and Packagist; charting via multiple web sources)

## Recommended Stack

### Core Technologies

| Technology | Version | Purpose | Why Recommended |
|------------|---------|---------|-----------------|
| Laravel | ^12.0 | PHP web framework | Current LTS-adjacent release (Feb 2025), security fixes through Feb 2027, minimal breaking changes from v11. PHP 8.2+ required — already a project constraint. Scheduler + queue system built in, which this app depends on heavily. |
| PHP | 8.2–8.4 | Runtime | Project constraint. Laravel 12 supports 8.2–8.4. PHP 8.3 is recommended for best performance on current hosting. |
| Livewire | ^4.2 | Reactive UI without SPA overhead | Released Jan 2026, v4.2.1 is current stable (2026-02-28). Server-rendered, stays in Blade ecosystem, no separate API layer needed. 60% faster DOM diffing than v3. Islands feature enables isolated chart refresh. Perfect for a dashboard with a few reactive components (filter controls, live data reload). No JavaScript build complexity for interactivity. |
| Tailwind CSS | ^4.2 | Utility-first CSS | Project constraint. v4 is the current release, ships as a Vite plugin (`@tailwindcss/vite`), CSS-first configuration replaces `tailwind.config.js`. Included by default in Laravel 12 starter kits. Oxide engine is significantly faster than v3. |
| Vite | Latest (bundled with Laravel) | Frontend asset bundling | Laravel 12's default bundler. Pre-configured for Tailwind v4 + Livewire. No Webpack or Mix needed. |
| ApexCharts | ^5.6 (latest via npm) | Interactive time-series line charts | Framework-agnostic vanilla JS (SVG-based), works directly in Blade/Livewire without React/Vue. Rich built-in time-series options (datetime x-axis, zoom/pan, tooltip formatting). Active development (v5.6.0 released Feb 2026). The `livewire-charts` package (`asantibanez/livewire-charts`) wraps ApexCharts in Livewire components, eliminating custom JS glue for chart data updates. |

### Database

| Technology | Version | Purpose | Why Recommended |
|------------|---------|---------|-----------------|
| SQLite | 3.x (system) | Primary data store | Zero-configuration for a single-user personal app. Single-file database — trivial to back up. Laravel 12 defaults to SQLite for new projects. Read-heavy workload (dashboard reads vastly outnumber API poll writes) where SQLite benchmarks well. No concurrent write contention risk: one queue worker writes price data every 15 minutes. |

### Queue & Scheduler

| Technology | Version | Purpose | Why Recommended |
|------------|---------|---------|-----------------|
| Laravel Scheduler | Built into Laravel 12 | Trigger 15-minute API poll | One crontab entry (`* * * * * php artisan schedule:run`) drives all scheduled work. No external scheduler needed. |
| Laravel Queues (database driver) | Built into Laravel 12 | Process API fetch jobs asynchronously | Database driver requires no extra services (no Redis, no Beanstalk). For a single job every 15 minutes with trivial volume, the database driver is the correct choice — Redis adds infrastructure complexity with zero benefit at this scale. A dedicated `php artisan queue:worker` process handles job execution. |
| Laravel Horizon | N/A — do not install | Queue monitoring UI | Overkill. Horizon requires Redis. Use `php artisan queue:failed` and the `failed_jobs` table for debugging at this scale. |

### Authentication

| Technology | Version | Purpose | Why Recommended |
|------------|---------|---------|-----------------|
| Laravel Breeze (Blade stack) | ^2.x | Single-user login | Minimal scaffolding: login, logout, password reset published as plain Blade + Tailwind views. No unnecessary registration flow (can be removed post-install). Matches the Livewire/Blade stack. Laravel Sanctum is overkill without a separate API; Fortify without Breeze requires more manual wiring. |

### Supporting Libraries

| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| `asantibanez/livewire-charts` | ^4.x | ApexCharts wrapped in Livewire components | Use this to render line charts driven by PHP data with automatic Livewire reactivity. Eliminates writing custom JavaScript event bridges between Livewire and ApexCharts. Verify v4 Livewire compatibility before installing — last confirmed for Livewire 3. If not yet updated for Livewire 4, use ApexCharts directly with a thin `@script` block instead. |
| Laravel HTTP Client | Built into Laravel 12 | Blizzard API OAuth2 + data requests | Use Laravel's first-party HTTP client (Guzzle wrapper) for all Blizzard API calls. Handles token fetch (POST to `https://oauth.battle.net/token`) and commodity data fetch (GET `/data/wow/auctions/commodities`). Built-in retry, timeout, and response assertion support. No third-party Blizzard PHP SDK needed — all are unmaintained (last updated 2017–2023). |
| `spatie/laravel-data` | ^4.x | Typed data objects for API responses | Optional but recommended. Maps raw Blizzard API JSON to typed PHP objects for price snapshot storage. Reduces null-safety bugs when the commodity response structure changes. |

### Development Tools

| Tool | Purpose | Notes |
|------|---------|-------|
| Laravel Sail | Local Docker environment | Optional. Provides MySQL, Redis, Mailpit if needed later. For a SQLite app with database queues, Sail is convenient but not required — `php artisan serve` + `php artisan queue:work` + `php artisan schedule:work` covers local dev. |
| Laravel Pint | PHP code style fixer | Included in Laravel 12 dev dependencies. Run `./vendor/bin/pint` before commits. |
| Pest PHP | Testing framework | Laravel 12 ships Pest as default. Use Feature tests for the API polling job (mock Blizzard HTTP calls) and for dashboard routes. |

## Installation

```bash
# New Laravel 12 project (SQLite default)
composer create-project laravel/laravel wow-ah-tracker
cd wow-ah-tracker

# Authentication scaffolding (Blade stack, Livewire option)
composer require laravel/breeze --dev
php artisan breeze:install blade

# Livewire 4
composer require livewire/livewire

# Charts (verify Livewire 4 support first; use as fallback if not available)
composer require asantibanez/livewire-charts

# Frontend: Tailwind v4 + ApexCharts
npm install @tailwindcss/vite apexcharts

# Run migrations (creates users, jobs, failed_jobs, cache tables)
php artisan migrate

# Local dev servers
php artisan serve &
php artisan queue:work &
php artisan schedule:work
```

## Alternatives Considered

| Recommended | Alternative | When to Use Alternative |
|-------------|-------------|-------------------------|
| Livewire 4 | Inertia.js + React/Vue | If the team is stronger in frontend JS frameworks, or if the dashboard needs complex client-side state management beyond what Livewire handles. Not warranted here — Livewire's server-rendered reactivity is sufficient for a time-series dashboard. |
| SQLite | MySQL / PostgreSQL | When you need concurrent multi-process writes, multiple servers, or the dataset exceeds a few hundred MB. Not applicable at this scale (storing ~6-7 items x 96 snapshots/day). |
| Database queue driver | Redis + Laravel Horizon | When job volume is high (hundreds/minute), you need visual job monitoring dashboards, or you have existing Redis infrastructure. At 1 job per 15 minutes, database queues are the correct default. |
| Laravel HTTP Client | Third-party Blizzard PHP SDKs | No maintained SDK for the current Battle.net Game Data API exists as of 2026. All packages on Packagist are unmaintained (2017–2023). Laravel's HTTP client is more capable and actively maintained. |
| Laravel Breeze | Laravel Jetstream | Jetstream adds Teams, 2FA, API tokens — all unnecessary for a single-user personal tool. Breeze is minimal and fast to customize. |

## What NOT to Use

| Avoid | Why | Use Instead |
|-------|-----|-------------|
| Redis (as queue driver) | No benefit at 1 job/15 minutes; adds infrastructure dependency (separate process, memory management, config) | Laravel database queue driver |
| Laravel Horizon | Requires Redis; dashboard monitoring is overkill for a single scheduled job | `failed_jobs` table + `php artisan queue:failed` |
| Any third-party Blizzard PHP SDK | All packages on Packagist are unmaintained (latest: 2017–2023). Will not have OAuth2 token caching or commodities endpoint support. | Laravel HTTP Client with manual token management |
| Chart.js | Less feature-rich than ApexCharts for time-series (no built-in zoom/pan, weaker datetime axis handling, canvas-based so no SVG export) | ApexCharts |
| Inertia.js | Brings an entire SPA build pipeline (TypeScript, React/Vue component tree) for a dashboard that is mostly server-rendered. Over-engineered for this scope. | Livewire 4 |
| Tailwind v3 | Superseded by v4 which ships with Laravel 12 by default. v3 uses a deprecated `tailwind.config.js` approach incompatible with the new CSS-first v4 plugin. | Tailwind CSS v4 |

## Stack Patterns by Variant

**For Blizzard API token management:**
- Fetch client credentials token via `Http::withBasicAuth($clientId, $clientSecret)->asForm()->post('https://oauth.battle.net/token', ['grant_type' => 'client_credentials'])`
- Cache the token in Laravel's cache (`Cache::put('blizzard_token', $token, $expiresIn - 60)`) to avoid re-fetching on every 15-minute job
- Store `BLIZZARD_CLIENT_ID` and `BLIZZARD_CLIENT_SECRET` in `.env`

**For charts in Livewire 4 (if `livewire-charts` is not yet v4-compatible):**
- Render ApexCharts with a `@script` block inside the Livewire component
- Use `$wire.on('pricesUpdated', (data) => chart.updateSeries(...))` to push new data to the chart
- This is the official Livewire 4 pattern for JavaScript interop

**For queue worker in production:**
- Run `php artisan queue:work --sleep=3 --tries=3` as a supervised process (Supervisor on Linux, or a simple `Procfile` if using Laravel Cloud/Forge)
- The scheduler requires one cron entry: `* * * * * cd /path && php artisan schedule:run >> /dev/null 2>&1`

## Version Compatibility

| Package | Compatible With | Notes |
|---------|-----------------|-------|
| Laravel ^12.0 | PHP 8.2–8.4 | PHP 8.1 not supported in Laravel 12 |
| Livewire ^4.2 | Laravel 10+ | v4.2.1 released 2026-02-28. Confirm Breeze stack compatibility. |
| Tailwind CSS ^4.2 | Vite + `@tailwindcss/vite` | Do NOT use PostCSS plugin approach with v4 — use Vite plugin only |
| ApexCharts ^5.6 | Vanilla JS / Livewire `@script` | No framework adapter needed for Blade; use `npm install apexcharts` |
| `asantibanez/livewire-charts` | Livewire 3 (confirmed) | Verify Livewire 4 support before installing. Check GitHub issues. |
| Laravel Breeze ^2.x | Laravel 12 | Blade stack publishes Tailwind v4 compatible views |

## Sources

- [Laravel 12 Release Notes](https://laravel.com/docs/12.x/releases) — Version, PHP requirements, support timeline (HIGH confidence)
- [Livewire v4.2.1 on Packagist](https://packagist.org/packages/livewire/livewire) — Latest version, release date 2026-02-28 (HIGH confidence)
- [Livewire 4 Official Blog Post](https://laravel.com/blog/livewire-4-is-here-the-artisan-of-the-day-is-caleb-porzio) — Feature confirmation (HIGH confidence)
- [Tailwind CSS v4 Installation with Vite](https://tailwindcss.com/docs/installation/using-vite) — Version ^4.2, Vite plugin approach (HIGH confidence)
- [ApexCharts npm](https://www.npmjs.com/package/apexcharts) — v5.6.0 current (MEDIUM confidence — npm page verified)
- [Laravel 12 Queue Docs](https://laravel.com/docs/12.x/queues) — Database driver recommendation (HIGH confidence)
- [Laravel 12 Scheduling Docs](https://laravel.com/docs/12.x/scheduling) — Scheduler architecture (HIGH confidence)
- [janostlund.com — Choosing Laravel Queue Drivers](https://janostlund.com/2025-10-29/choosing-laravel-drivers-queues-sessions-cache) — Database driver for small apps (MEDIUM confidence — single article, consistent with official docs)
- [Logansua Blizzard API Client on Packagist](https://packagist.org/packages/logansua/blizzard-api-client) — Unmaintained (last v2.0.2, 2017) (HIGH confidence)
- [Francis Schiavo Blizzard API on Packagist](https://packagist.org/packages/francis-schiavo/blizzard_api) — Last release 2023-04-16, no commodities support confirmed (MEDIUM confidence)
- [livewire-charts package](https://github.com/asantibanez/livewire-charts) — ApexCharts Livewire wrapper (MEDIUM confidence — Livewire 4 compatibility unconfirmed as of research date)

---
*Stack research for: WoW AH Commodity Price Tracker*
*Researched: 2026-03-01*
