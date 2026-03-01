# Phase 1: Project Foundation - Context

**Gathered:** 2026-03-01
**Status:** Ready for planning

<domain>
## Phase Boundary

Laravel 12 scaffolding with correct database schema, environment configuration, and development tooling. All schema decisions must be correct before any data is written — retroactive fixes to column types or indexes are painful once snapshots accumulate. Authentication, watchlist CRUD, and API integration are separate phases.

</domain>

<decisions>
## Implementation Decisions

### Schema design
- Include `user_id` (nullable FK to `users`) on `watched_items` from the start — avoids a migration change when Phase 2 adds auth
- Hard delete for watched items — items are just Blizzard IDs, trivial to re-add. No SoftDeletes
- Bare minimum columns on `watched_items`: `blizzard_item_id`, `name`, `buy_threshold`, `sell_threshold`, `user_id` — no extra metadata columns (icon, category, quality). Fetch on demand later if needed
- `polled_at` on `price_snapshots` uses a datetime column (Laravel `timestamp()`), not a Unix integer — works natively with Carbon, Eloquent casting, and chart libraries
- Price columns (`min_price`, `avg_price`, `median_price`, `total_volume`) stored as unsigned big integers (copper denomination) per roadmap requirements

### Dev tooling
- Pest for testing — matches roadmap plan references and provides expressive syntax
- Minimal dev tools in Phase 1: Pint (code style) + Pest. Add Debugbar, Telescope, IDE Helper as later phases need them
- Include model factories and DatabaseSeeder with sample watched items and fake price snapshots — enables manual testing from day one
- Target PHP 8.4 — latest stable with property hooks, asymmetric visibility, and `#[Deprecated]` attribute

### Project conventions
- Actions + Services pattern: `app/Actions/` for single-purpose operations (PriceFetchAction, PriceAggregateAction), `app/Services/` for stateful service classes (BlizzardTokenService)
- Models stay in `app/Models/` — Laravel default, no custom location
- `declare(strict_types=1)` on all new PHP files
- Full type hints — return types and parameter types on all methods

### Claude's Discretion
- Exact migration column order and naming beyond what's specified
- `.gitignore` contents and IDE config
- Composer script aliases
- Any additional indexes beyond the required composite `(watched_item_id, polled_at)`

</decisions>

<specifics>
## Specific Ideas

No specific requirements — open to standard approaches. The roadmap is precise about column types (unsigned big integers for copper prices) and the composite index requirement.

</specifics>

<code_context>
## Existing Code Insights

### Reusable Assets
- Greenfield project — no existing code to reuse

### Established Patterns
- No patterns established yet — Phase 1 sets the conventions for all subsequent phases

### Integration Points
- `.env` and `config/services.php` will be consumed by Phase 4 (BlizzardTokenService)
- `watched_items` and `price_snapshots` tables are used by every subsequent phase
- Model factories will be consumed by test suites in Phases 4-6

</code_context>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 01-project-foundation*
*Context gathered: 2026-03-01*
