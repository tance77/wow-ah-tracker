# Milestones

## v1.2 Crafting Profitability (Shipped: 2026-03-06)

**Phases:** 13-16 (7 plans) | **Commits:** 27 | **LOC:** 29,895 PHP
**Timeline:** 2026-03-05 to 2026-03-06
**Git range:** `316638a` → `d33c293`

**Delivered:** A Crafting section showing profit margins for all 798 Midnight expansion recipes across 9 professions, with live AH prices, sortable tables, and missing-price/staleness indicators.

**Key accomplishments:**
1. Three-table recipe data model (professions, recipes, recipe_reagents) with cascade deletes and dual quality-tier FKs
2. `blizzard:sync-recipes` command with three-level API traversal, idempotent upserts, --dry-run, and --report-gaps
3. `RecipeProfitAction` invokable class computing per-recipe profit with 5% AH cut, two quality tiers, and NULL handling
4. Profession overview page with cards showing top 5 profitable recipes per profession
5. Per-profession recipe table with Alpine.js sorting, filtering, accordion reagent breakdowns, and staleness banner
6. Auto-watch integration for all recipe reagents and crafted items

**Requirements:** 19/19 satisfied | **Audit:** TECH DEBT (no blockers)
**Tech debt:** `crafted_quantity` not factored into profit calculation (medium — affects multi-yield recipes)
**Archives:** `milestones/v1.2-ROADMAP.md`, `milestones/v1.2-REQUIREMENTS.md`

---

## v1.1 Shuffles (Shipped: 2026-03-05)

**Phases:** 9-12 (8 plans) | **Timeline:** 2026-03-04 to 2026-03-05

**Delivered:** Shuffle conversion chains with multi-step yield configuration, auto-watch integration, and an interactive batch calculator with cascading yields and profit summary.

**Key accomplishments:**
1. Shuffle data model with cascade deletes and auto-watched item orphan cleanup
2. Full CRUD shuffles section with inline rename and profitability badges
3. Step editor with item search, fixed/range yield config, and chain flow visualization
4. Batch calculator with Alpine.js cascading yields, profit summary, and staleness detection

**Requirements:** 15/15 satisfied | **Audit:** PASSED
**Archives:** `milestones/v1.1-ROADMAP.md`, `milestones/v1.1-REQUIREMENTS.md`

---

## v1.0 MVP (Shipped: 2026-03-02)

**Phases:** 1-8 (18 plans) | **Commits:** 95 | **LOC:** 15,058 PHP | **Files:** 202
**Timeline:** 2026-03-01 (1 day)
**Git range:** `0f3ce69` → `2d72d8e`

**Delivered:** A personal Laravel dashboard that polls Blizzard Auction House commodity prices every 15 minutes, stores aggregated snapshots, and presents interactive trend charts with threshold-based buy/sell signal indicators.

**Key accomplishments:**
1. Laravel 12 foundation with Livewire 4, Tailwind CSS v4, and Pest 3
2. Full Breeze authentication with WoW dark theme (gold/amber accents)
3. Per-user watchlist management with catalog search and buy/sell thresholds
4. Blizzard OAuth2 token service and commodity price fetch with auto-refresh
5. Automated 15-min ingestion pipeline with dual deduplication gates (Last-Modified + MD5 hash)
6. Interactive ApexCharts dashboard with buy/sell signal indicators, rolling averages, and timeframe toggles

**Requirements:** 21/21 satisfied | **Audit:** PASSED
**Archives:** `milestones/v1.0-ROADMAP.md`, `milestones/v1.0-REQUIREMENTS.md`

---

