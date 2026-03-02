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

## Cross-Milestone Trends

### Process Evolution

| Milestone | Sessions | Phases | Key Change |
|-----------|----------|--------|------------|
| v1.0 | ~5 | 8 | Initial milestone — established GSD workflow |

### Cumulative Quality

| Milestone | Tests | Audit Score | Requirements |
|-----------|-------|-------------|-------------|
| v1.0 | 16 dashboard + auth + watchlist + ingestion | 21/21 | 21/21 satisfied |

### Top Lessons (Verified Across Milestones)

1. Lock irrecoverable decisions early — schema types, indexes, auth patterns
2. Per-test HTTP fake helpers prevent stub accumulation across test suites
