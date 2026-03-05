# WoW AH Tracker

## What This Is

A Laravel web application that tracks World of Warcraft Auction House commodity prices using the Blizzard API. It polls prices every 15 minutes for a curated list of crafting materials, stores price history with aggregated metrics, and presents an interactive dashboard with trend charts and threshold-based buy/sell signal indicators. Built for personal use on the US Retail region.

## Core Value

See at a glance when crafting material prices dip or spike so I can act on buy/sell opportunities before the market corrects.

## Requirements

### Validated

- ✓ Fetch commodity prices from Blizzard API on a 15-minute schedule — v1.0
- ✓ Store price history for tracked items over time — v1.0
- ✓ Dashboard with price trend charts (line charts over days/weeks) — v1.0
- ✓ Visual indicators for price dips and spikes (buy/sell signals) — v1.0
- ✓ Admin UI to add/remove watched items with thresholds — v1.0
- ✓ Simple authentication (single user login) — v1.0
- ✓ Blizzard API credentials managed via .env — v1.0
- ✓ Shuffles navigation section with saved conversion chains — v1.1
- ✓ Multi-step conversion chains with per-step ratios — v1.1
- ✓ Fixed or min/max yield ranges per conversion step — v1.1
- ✓ Auto-watch items added to shuffles — v1.1
- ✓ Batch calculator with per-step yields and profit — v1.1
- ✓ Profit summary with total cost, value, and net profit — v1.1

### Active

- [ ] Crafting profitability section with all Midnight expansion recipes
- [ ] Recipe data fetched from Blizzard API profession/recipe endpoints
- [ ] Profession overview page with top profitable recipes per profession
- [ ] Profession detail page with full recipe table sorted by profit
- [ ] Per-recipe profit calculation: reagent cost vs crafted item sell price
- [ ] Two quality tiers (Tier 1 & Tier 2) with profit shown per tier
- [ ] Median profit across both tiers
- [ ] Auto-watch reagents so AH prices stay fresh

### Out of Scope

- Multi-region support — US only, commodities are region-wide
- Classic/SoD support — Retail only, different API shape
- Multi-user / public access — personal tool
- Mobile native app — web only, responsive Tailwind
- Gear/equipment tracking — commodities only
- ~~Crafting profit calculator (full recipe-based)~~ — Promoted to v1.2 milestone
- Additional item categories (gear, pets, mounts) — commodities only (ADVN-02, deferred)

## Current Milestone: v1.2 Crafting Profitability

**Goal:** Add a Crafting section that shows profit margins for all Midnight expansion recipes using live AH prices, organized by profession with sortable tables.

**Target features:**
- All Midnight expansion recipes fetched from Blizzard API
- Profession overview page with profit highlights
- Per-profession detail pages with full sortable recipe tables
- Two-tier quality pricing with per-tier and median profit
- Auto-watch for recipe reagents

## Context

Shipped v1.0 with 15,058 LOC PHP across 202 files.
Tech stack: Laravel 12, Livewire 4, Volt, Tailwind CSS v4, ApexCharts, Pest 3, SQLite.
All 21 v1 requirements satisfied. Audit passed 21/21.
WoW dark theme with gold/amber accents applied across all views.
Dual deduplication gates (Last-Modified header + MD5 hash fallback) prevent duplicate snapshots.
Frequency-distribution median correctly weights high-quantity listings.

## Constraints

- **Tech stack:** Laravel 12, Livewire 4, Tailwind CSS v4, PHP ^8.4
- **Infrastructure:** Queue worker needed for scheduled jobs (Laravel scheduler + queue)
- **API rate limits:** Blizzard API (~100 req/s, 36K/hr) — not a concern at 15-min intervals
- **Data volume:** Commodities endpoint returns thousands of items per call, only watched items stored

## Key Decisions

| Decision | Rationale | Outcome |
|----------|-----------|---------|
| Commodities endpoint (not realm-specific AH) | Crafting materials are commodities — region-wide, simpler API | ✓ Good |
| 15-minute polling interval | Balances freshness with API courtesy, matches AH update cadence | ✓ Good |
| Tailwind CSS v4 for styling | User preference, CSS-first config (no tailwind.config.js) | ✓ Good |
| Single-user simple auth via Breeze | Personal tool, no need for complex user management | ✓ Good |
| BIGINT UNSIGNED for copper prices | Avoids floating point rounding — irrecoverable schema decision | ✓ Good |
| Composite index (watched_item_id, polled_at) | Time-series query performance — must exist before data accumulates | ✓ Good |
| Direct ApexCharts via window global | Volt SFC scripts can't use ES module imports; livewire-charts incompatible with LW4 | ✓ Good |
| Frequency-distribution median | High-quantity listings at one price dominate market — simple sort misleading | ✓ Good |
| Dual dedup gates (header + hash) | Last-Modified may be absent; hash fallback covers all cases | ✓ Good |
| One snapshot per WatchedItem row (not per blizzard_item_id) | Multiple users watching same item get independent history | ✓ Good |

---
*Last updated: 2026-03-05 after v1.2 milestone started*
