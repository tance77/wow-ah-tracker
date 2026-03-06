# Project Retrospective

*A living document updated after each milestone. Lessons feed forward into future planning.*

## Milestone: v1.0 — MVP

**Shipped:** 2026-03-02
**Phases:** 8 | **Plans:** 18 | **Commits:** 95

### What Was Built
- Full Laravel 12 dashboard with Livewire 4/Volt, Tailwind CSS v4, and WoW dark theme
- Blizzard OAuth2 integration with automatic token refresh and commodity price fetching
- 15-minute automated ingestion pipeline with frequency-distribution median and dual deduplication
- Interactive ApexCharts dashboard with summary cards, timeframe toggles, and buy/sell signal indicators
- Breeze authentication with registration, login, logout, and password reset
- Per-user watchlist management with catalog search, manual ID entry, and threshold editing

### What Worked
- Data-dependency-driven phase ordering eliminated rework — schema before data, data before charts
- Irrecoverable decisions (BIGINT UNSIGNED, composite index) locked in Phase 1 before any data written
- Per-test `fakeBlizzardHttp()` helper pattern avoided Http::fake() stub accumulation across test suites
- Dual dedup gate design (header primary + hash fallback) covered all Blizzard API behaviors without rework
- Volt SFC pattern (PHP + Blade in one file) kept dashboard component cohesive and testable
- Pest feature tests verified Livewire behavior (dispatched events, component state) without JavaScript

### What Was Inefficient
- Phase 3 ROADMAP progress table left stale ("2/3 In Progress" when actually complete) — manual tracking drifts
- Phase 5 SUMMARY frontmatter had empty `requirements_completed` despite requirements being satisfied — documentation discipline gap
- Breeze install downgraded Livewire to v3 requiring manual restoration to v4 — starter kit version conflicts cost extra time in Phase 2
- 19 TWW item IDs in catalog seeder are unverified placeholders — should have been validated against live API

### Patterns Established
- `app/Actions/` for single-responsibility invokable classes (PriceFetchAction, PriceAggregateAction)
- `app/Services/` for stateful service classes with singleton binding (BlizzardTokenService)
- `IngestionMetadata::singleton()` via `firstOrCreate(['id' => 1])` for single-row global state
- ApexCharts via `window.ApexCharts` global + `$wire.$on()` for Livewire-to-JS chart updates
- Signal sorting via array callback `[priority, -magnitude]` for multi-criteria collection ordering
- `wire:ignore.self` on chart containers to prevent Livewire DOM morphing conflicts

### Key Lessons
1. Lock irrecoverable schema decisions (column types, indexes) in the first phase — they cannot be fixed after data accumulates
2. Starter kit installs (Breeze) may downgrade manually-installed packages — verify versions after install
3. Http::fake() stubs accumulate across tests — use per-test helper functions instead of beforeEach
4. Volt bare `<script>` blocks cannot use ES module imports — register libraries as window globals in app.js
5. ApexCharts `updateOptions()` merges objects — annotations must be replaced (not appended) to prevent accumulation

### Cost Observations
- Model mix: balanced profile used throughout
- Sessions: ~5 sessions over 1 day
- Notable: Entire v1.0 MVP shipped in a single day with 95 commits

---

## Milestone: v1.1 — Shuffles

**Shipped:** 2026-03-05
**Phases:** 4 | **Plans:** 8

### What Was Built
- Shuffle data model with cascade deletes and auto-watched item orphan cleanup
- Full CRUD shuffles section with inline rename, profitability badges, and clone/export/import
- Step editor with item search combobox, fixed/range yield config, reorder, and chain flow arrows
- Batch calculator with Alpine.js cascading yields, profit summary, staleness detection, and byproduct EV

### What Worked
- Phase dependency chain (data model -> CRUD -> editor -> calculator) enabled clean integration
- Alpine.js islands for batch calculator kept server round-trips to zero for calculator interaction
- `profitPerUnit()` on the Shuffle model centralized profit logic for both list badges and detail page
- Orphan cleanup via Eloquent boot events (both Shuffle::deleting and ShuffleStep::deleted) prevented leaked watched items

### What Was Inefficient
- v1.1 MILESTONES.md entry was not created during milestone completion — had to backfill during v1.2 archival
- ShuffleStep orphan cleanup query needed careful NOT EXISTS scoping — initial implementation missed cross-shuffle references

### Patterns Established
- Alpine.js `x-data` islands for client-side computation (batch calculator, recipe table sorting)
- `wire:ignore` on Alpine-managed sections to prevent Livewire DOM morphing conflicts
- Item search combobox pattern reused for both input and output item selection
- `firstOrCreate` for auto-watch with null thresholds and shuffle provenance tracking

### Key Lessons
1. Alpine.js islands are ideal for computation-heavy UI that doesn't need server state — keeps interaction instant
2. Orphan cleanup must check ALL references, not just the deleting parent — `whereNotExists` subquery pattern is essential
3. Byproducts with drop chance need expected-value (EV) math in calculators — percentage * value, not binary

### Cost Observations
- Model mix: balanced profile (sonnet executors, sonnet verifiers)
- Sessions: ~3 sessions over 2 days
- Notable: 25 quick tasks shipped alongside milestone work

---

## Milestone: v1.2 — Crafting Profitability

**Shipped:** 2026-03-06
**Phases:** 4 | **Plans:** 7

### What Was Built
- Three-table recipe data model (professions, recipes, recipe_reagents) with cascade deletes
- `blizzard:sync-recipes` command with three-level API traversal, Http::pool() batching, idempotent upserts
- `RecipeProfitAction` invokable class with TDD — 11 tests covering all profit edge cases
- Profession overview page with cards showing top 5 profitable recipes per profession
- Per-profession recipe table with Alpine.js sorting, filtering, accordion reagent breakdowns, staleness banner

### What Worked
- TDD on RecipeProfitAction caught edge cases early (null propagation, single-tier median, negative profit)
- Three-level API traversal with Http::pool() reduced sync time from ~130s sequential to ~20s batched
- Server-side `#[Computed] recipeData` builds the full dataset once, Alpine.js handles all interaction client-side
- Separation of data pipeline (Plan 01) from UI (Plan 02) in Phase 16 enabled independent testing

### What Was Inefficient
- `crafted_quantity` stored by SyncRecipesCommand but never used by RecipeProfitAction — discovered only during milestone audit integration check
- SUMMARY.md frontmatter for Phase 13 and 14 plans had empty `requirements_completed` despite requirements being satisfied — same documentation discipline gap as v1.0
- Database had 0 recipes at visual verification time — forgot to run `blizzard:sync-recipes` before testing UI

### Patterns Established
- `app/Actions/RecipeProfitAction` follows same invokable Action pattern as `PriceAggregateAction`
- Dual nullable FK columns for quality tiers (`crafted_item_id_silver`, `crafted_item_id_gold`)
- Slug-based route model binding via `getRouteKeyName()` override on Profession model
- Alpine.js partition-sort: normal rows sorted first, then missing-price/non-commodity rows always at bottom

### Key Lessons
1. Always run data import commands before visual verification — empty database produces false negatives
2. Integration checks should happen earlier than milestone audit — `crafted_quantity` gap would have been caught sooner
3. SUMMARY frontmatter `requirements_completed` needs enforcement — same gap appeared in v1.0 and v1.2
4. Highest-ID skill tier heuristic works reliably for identifying current expansion content

### Cost Observations
- Model mix: balanced profile (sonnet executors, sonnet verifiers)
- Sessions: ~2 sessions over 2 days
- Notable: 4 phases shipped in under 6 hours of wall time

---

## Cross-Milestone Trends

### Process Evolution

| Milestone | Sessions | Phases | Key Change |
|-----------|----------|--------|------------|
| v1.0 | ~5 | 8 | Initial milestone — established GSD workflow |
| v1.1 | ~3 | 4 | Alpine.js islands for client-side computation |
| v1.2 | ~2 | 4 | TDD on action classes, integration checks at audit |

### Cumulative Quality

| Milestone | Tests | Audit Score | Requirements |
|-----------|-------|-------------|-------------|
| v1.0 | 16 dashboard + auth + watchlist + ingestion | 21/21 | 21/21 satisfied |
| v1.1 | +19 shuffle step editor + 18 batch calculator | 15/15 | 15/15 satisfied |
| v1.2 | +21 recipe model + 11 profit action + 8 crafting overview + 7 recipe table | 19/19 | 19/19 satisfied |

### Top Lessons (Verified Across Milestones)

1. Lock irrecoverable decisions early — schema types, indexes, auth patterns
2. Per-test HTTP fake helpers prevent stub accumulation across test suites
3. SUMMARY frontmatter `requirements_completed` needs enforcement — gap appeared in v1.0 and v1.2
4. Alpine.js islands are ideal for computation-heavy UI that doesn't need server state
5. Always run data import commands before visual verification
