# Project Research Summary

**Project:** WoW Auction House Tracker — v1.1 Shuffles Milestone
**Domain:** WoW AH commodity price tracker with conversion chain (shuffle) profit calculator
**Researched:** 2026-03-04
**Confidence:** HIGH overall (codebase-grounded; v1.0 already shipped)

## Executive Summary

This is a subsequent-milestone research document for an existing, shipped application (v1.0). The core WoW AH price-tracking infrastructure — Laravel 12, Livewire 4 Volt SFCs, SQLite, scheduled Blizzard API polling, and BIGINT copper price storage — is validated and unchanged. The v1.1 Shuffles milestone adds a dedicated section for defining and evaluating multi-step material conversion chains (e.g., "buy ore -> prospect -> sell gems"), a feature category with no competing web-based tool that combines saved chains, live prices, and a batch profit calculator. The gap is real and the stack is fully capable of filling it without new dependencies.

The recommended approach is entirely additive: two new Eloquent models (`Shuffle`, `ShuffleStep`), two new Volt SFC pages (index + detail/calculator), two new database tables, and minimal changes to three existing files (User model, navigation blade, routes). The profit calculator reuses existing infrastructure — `PriceSnapshot.median_price` for live prices, `FormatsAuctionData::formatGold()` for display, and the `firstOrCreate` pattern for auto-watching items in a chain. No new packages are needed.

The highest risks are all data model decisions that are irrecoverable after launch: storing yield as a single float (instead of integer min/max pairs), omitting the 5% AH cut from the profit formula, and implementing auto-watch without provenance tracking. All three must be designed correctly before any code is written. The Livewire-specific risk — N+1 price queries triggered on every reactive update — must be addressed with bulk `whereIn` fetches and computed property caching from day one.

---

## Key Findings

### Recommended Stack

The v1.0 stack is fully sufficient. No new packages or services are required for Shuffles. The only stack additions are two new database tables and two new Volt SFC files. PHP 8.4's BCMath\Number is available but not needed — all profit math stays in integer copper, which standard PHP integer arithmetic handles exactly. Livewire 4's native array property binding handles the dynamic multi-step form; the documented pattern is wildcard validation rules on save (not real-time per-keystroke), avoiding a known rough edge with nested array error assignment.

**Core technologies (unchanged from v1.0):**
- Laravel 12 / PHP 8.4: framework and runtime — validated in production
- Livewire 4 + Volt: reactive SFC pages — handles multi-step forms via array property binding
- Tailwind CSS v4: styling — existing WoW dark theme with gold/amber accents applies directly
- SQLite: primary data store — two new tables fit trivially; WAL mode not yet needed
- ApexCharts v5: charts — dashboard only; not used in Shuffles UI

**New data model (no package, pure Eloquent):**
- `shuffles` table: named chain owned by user (`id`, `user_id`, `name`, `timestamps`)
- `shuffle_steps` table: ordered steps with `sort_order` (tinyint), `input_catalog_item_id`, `output_catalog_item_id`, `input_qty`, `output_qty_min`, `output_qty_max` (nullable)

### Expected Features

The WoW shuffle community runs conversions via Google Sheets with TSM data exports. No dedicated web app with saved chains, live prices, and a batch calculator exists publicly. This is the gap — and the full v1.1 feature set fits within two new Volt pages.

**Must have (v1.1 launch — table stakes):**
- Named conversion chain CRUD (save, edit, delete shuffles) — required for reuse; one-time entry is the whole point
- Multi-step chain definition (A input -> B output, chained) — real shuffles have 2-4 steps
- Batch profit calculator with input quantity field — "I have 200 stacks; is it worth it?" is the core question
- Profit summary: total cost in, total value out (minus 5% AH cut), net profit — the go/no-go number
- Per-step cost and value breakdown — shows where margin is created or destroyed
- Auto-watch: create `WatchedItem` records for all chain items on shuffle save — prices will not exist otherwise
- Profitability status badge (green/red) — instant visual signal consistent with dashboard buy/sell indicators
- Price staleness warning — flags snapshots older than 1 hour so the user knows calculation confidence
- Shuffles index/list page — users maintain multiple shuffles; they need a landing page

**Should have (v1.x — add after validation):**
- Break-even input price — "what is the max I can pay for ore?" — inverse of the profit formula; cheap to add
- Yield range (min/max per step) — shows best/worst-case profit band; defer until average ratios are in active use
- Per-output vendor vs AH toggle — relevant when low-quality outputs (e.g., uncommon gems) vendor for more than AH price

**Defer (v2+):**
- Historical profitability trend chart — requires storing calculated profit results over time; new data type
- Full recipe-based crafting calculator — CraftSim territory; explicitly deferred in PROJECT.md as ADVN-01
- Automated profit alerts — additive to Discord webhook infrastructure if/when built

### Architecture Approach

The Shuffles feature integrates as a self-contained section within the existing app. Two Volt SFC pages handle all UI: `pages.shuffles` (index + create/delete) and `pages.shuffle-detail` (step editor + batch calculator). The profit calculation runs as a `#[Computed]` property in the detail component — extractable to `app/Actions/ShuffleProfitAction.php` if it grows complex, following the existing `PriceAggregateAction` precedent. All price data flows from `PriceSnapshot.median_price`; no new data sources are introduced. The build order is strictly dependency-driven: migrations and models must complete before factories, which must complete before feature tests can run.

**Major components:**
1. `Shuffle` + `ShuffleStep` Eloquent models — named chains and their ordered steps; `Shuffle::steps()` orders by `sort_order`
2. `pages.shuffles` Volt SFC — list all shuffles with profitability badge; create and delete
3. `pages.shuffle-detail` Volt SFC — step editor (add/remove/reorder), batch calculator, profit summary
4. Auto-watch integration — `ensureWatched()` called in `addStep()` / `updateStep()` using `firstOrCreate` only
5. `ShuffleProfitAction` (optional extraction) — encapsulates profit calculation for independent Pest testing

### Critical Pitfalls

1. **Float yield arithmetic on copper prices** — Store yields as integer `output_qty_min` / `output_qty_max`; never multiply `$copper_price * $float_ratio`. All profit math stays in integer copper; convert to gold only at display via `formatGold()`. This is the most important schema decision — irrecoverable after data entry begins.

2. **Auto-watch without provenance tracking** — Use `firstOrCreate` only (never `updateOrCreate`). If a `WatchedItem` already exists, leave it completely unchanged. When a shuffle is deleted, only remove auto-created `WatchedItem` records that no other shuffle references. Track provenance with a `created_by_shuffle_id` nullable FK or a join table.

3. **N+1 price queries in the batch calculator** — Collect all item IDs for the entire chain, then execute one bulk `whereIn` query for latest snapshots. Cache in a Livewire `#[Computed]` property. Separate price-fetch (on mount or manual refresh) from profit calculation (pure PHP math on cached data). Bind quantity input with `wire:model.lazy` or `wire:model.blur` to prevent per-keystroke server round-trips.

4. **AH cut omitted from profit formula** — Apply a named constant `AH_CUT = 0.05` as a deduction from all output sell values before computing net profit. Show "Revenue (after 5% AH cut)" and "Cost" as explicit line items. A calculator without this systematically overstates profit and will cause real gold loss.

5. **Circular chain references** — Before saving, traverse step graph and assert no `output_catalog_item_id` appears as an `input_catalog_item_id` in any earlier step. Block save with a user-facing validation error. Without this, a user-created cycle causes an infinite loop or 500 error in the calculator.

---

## Implications for Roadmap

The Shuffles feature has a clear dependency chain that dictates build order. Data model decisions identified in PITFALLS.md must be locked in Phase 1 — they cannot be corrected without migrations and data re-entry.

### Phase 1: Data Model and Migrations

**Rationale:** Everything else depends on correct schema. Float yield storage and missing min/max columns are identified as irrecoverable errors if discovered post-launch. This phase de-risks all downstream work before any application code is written.

**Delivers:** Migrations for `shuffles` and `shuffle_steps` tables (with integer `output_qty_min` / `output_qty_max`, `sort_order` tinyint, FK constraints with cascade delete); `Shuffle` and `ShuffleStep` Eloquent models; `User::shuffles()` relationship; `ShuffleFactory` and `ShuffleStepFactory` for test seeding.

**Addresses:** Shuffle CRUD prerequisites; auto-watch data requirements; batch calculator data requirements.

**Avoids:** Float yield storage (Pitfall 1); missing yield variance columns (Pitfall 4); linked-list step ordering anti-pattern (use `sort_order` integer column, not `previous_step_id`).

**Research flag:** Standard patterns — no additional research needed. Laravel migrations with integer columns and Eloquent models are fully documented and match existing codebase patterns.

### Phase 2: Shuffle Index Page and Navigation

**Rationale:** CRUD validation (list, create, delete) can be tested without any calculator logic. Establishing auth scoping and route patterns before adding calculator complexity is the correct order.

**Delivers:** `pages.shuffles` Volt SFC (list all shuffles, create with name, delete with confirmation); two new routes (`/shuffles`, `/shuffles/{shuffle}`); navigation link in both desktop and mobile sections of `navigation.blade.php`; Pest feature tests for CRUD and auth scoping.

**Addresses:** Shuffles navigation section / index page (table stakes); shuffle create/edit/delete (table stakes).

**Avoids:** Authorization bypass — scope all queries to `auth()->user()->shuffles()` from day one.

**Research flag:** Standard patterns — Volt SFC CRUD is identical to existing `pages.watchlist` pattern; no research needed.

### Phase 3: Step Editor and Auto-Watch

**Rationale:** The step editor is the highest-complexity UI piece and the prerequisite for the calculator — bad step data makes calculator results meaningless. Auto-watch must be implemented in this phase because items added to a chain need at least one polling cycle before prices are available for the calculator.

**Delivers:** `pages.shuffle-detail` Volt SFC with step add/remove/reorder; `CatalogItem` combobox reuse for item selection (same pattern as Watchlist); `ensureWatched()` auto-watch using `firstOrCreate` only; cycle detection on step save; Pest integration test verifying existing `WatchedItem` thresholds are not overwritten on auto-watch.

**Addresses:** Multi-step chain definition; auto-watch items in a shuffle; price staleness warning groundwork.

**Avoids:** Auto-watch threshold collision (Pitfall 7); auto-watch orphaning on shuffle delete (Pitfall 2); circular chain references (Pitfall 5); `updateOrCreate` misuse on auto-watch.

**Research flag:** The auto-watch provenance implementation choice — nullable `created_by_shuffle_id` FK on `watched_items` vs. a separate pivot table — should be decided before coding starts. PITFALLS.md documents the problem clearly but leaves the schema choice open. Recommend a quick architecture decision record before Phase 3 planning.

### Phase 4: Batch Calculator and Profit Summary

**Rationale:** The calculator is the core value delivery but depends on Steps (Phase 3) and prices existing in the database. Building it last avoids debugging calculator output against missing or incorrectly modeled step data.

**Delivers:** `$batchQuantity` Livewire property with `wire:model.lazy` binding; `profitBreakdown()` computed property with bulk `whereIn` price fetch; per-step cost/value/margin display; profit summary row (total cost in, total value out after 5% AH cut, net profit); profitability status badge (green/red); price staleness warning (flag if snapshot > 1 hour old); `formatGold()` display throughout; optional extraction to `ShuffleProfitAction` if complexity warrants.

**Addresses:** Batch calculator; profit summary with AH cut; profitability status badge; price staleness warning; per-step breakdown (all P1 table stakes).

**Avoids:** N+1 price queries (Pitfall 3); float arithmetic in profit calculation (Pitfall 1); AH cut omission (Pitfall 6); per-keystroke server round-trips (debounce with `.lazy`); raw copper display (always `formatGold()`); `min_price` used instead of `median_price` for cost basis.

**Research flag:** Standard patterns — Livewire computed properties and `whereIn` bulk fetching are well-documented and already used in this codebase. No research phase needed.

### Phase Ordering Rationale

- **Migrations before models before factories before tests:** Build order from ARCHITECTURE.md is explicit. Feature tests cannot run until factories exist, and factories require correct model definitions.
- **CRUD before calculator:** The step editor produces the data the calculator consumes. Building the calculator against empty or wrongly modeled step data wastes debugging cycles.
- **Auto-watch in Phase 3, not Phase 4:** Items added to a chain need prices before the calculator is useful. If auto-watch ships with the calculator, users face a "no price data" state on first use.
- **Pitfall prevention tied to the phase where the decision is made:** Float yield storage is a migration decision (Phase 1). AH cut omission is a calculator decision (Phase 4). Tying each pitfall to the correct phase prevents "add it later" deferral that becomes irrecoverable technical debt.
- **Schema includes `output_qty_min` / `output_qty_max` in Phase 1** even if the UI only displays average profit in Phase 4. This allows yield range display to be added in v1.x without a schema migration.

### Research Flags

Phases needing deeper research during planning:
- **Phase 3 (Auto-Watch Provenance):** Nullable `created_by_shuffle_id` FK on `watched_items` vs. a separate `shuffle_watched_items` pivot table. The FK approach is simpler and sufficient for a single-user app; the pivot table is more normalized. Decide before writing the migration.

Phases with standard patterns (skip research-phase):
- **Phase 1:** Laravel migrations with integer columns and Eloquent model relationships — fully documented, matches existing patterns in the codebase.
- **Phase 2:** Volt SFC CRUD — identical pattern to existing `pages.watchlist`; no research needed.
- **Phase 4:** Livewire computed properties, bulk `whereIn` Eloquent queries, `formatGold()` display — all established in-project patterns; no research needed.

---

## Confidence Assessment

| Area | Confidence | Notes |
|------|------------|-------|
| Stack | HIGH | Direct codebase inspection of `composer.json`, existing models, and shipped v1.0 pages. No new packages needed — all confirmed in the project already. |
| Features | HIGH (core) / MEDIUM (differentiators) | Core shuffle mechanics (yield ratios, AH cut, batch calculator) are well-documented in community sources and Wowpedia. Differentiator gap analysis (no competing web tool) is inference from absence, not direct confirmation. |
| Architecture | HIGH | Direct codebase inspection. All patterns (Volt SFC, `#[Computed]`, `firstOrCreate`, `formatGold()`) are present and working in the shipped v1.0 codebase. |
| Pitfalls | HIGH (integration) / MEDIUM (Livewire edge cases) | Integration pitfalls derived from actual codebase analysis and known Blizzard/WoW domain constraints. Livewire 4 nested array real-time validation rough edge is confirmed as a known limitation but the exact failure mode may vary by version. |

**Overall confidence:** HIGH

### Gaps to Address

- **Auto-watch provenance schema choice:** Nullable `created_by_shuffle_id` FK on `watched_items` vs. a separate pivot table. Choose before Phase 3 starts. The FK approach is recommended for a single-user app — lower complexity, one less migration, same cleanup semantics.
- **Yield range display in Phase 4:** FEATURES.md defers min/max yield UI to v1.x, but PITFALLS.md recommends storing `output_qty_min` / `output_qty_max` in Phase 1 schema. The schema must include both columns in Phase 1 even if the calculator only shows average profit at launch. Confirm this is understood before writing the migration.
- **`wire:model.lazy` vs `wire:model.blur` for quantity input:** Both prevent per-keystroke queries but differ in UX (`.lazy` fires on change; `.blur` fires on focus loss). Validate the preferred behavior for a numeric input field during Phase 4 implementation.

---

## Sources

### Primary (HIGH confidence)
- Project v1.0 codebase (`composer.json`, `app/Models/`, `resources/views/livewire/`, `routes/web.php`, `database/migrations/`) — stack baseline, architecture patterns, existing relationships
- `.planning/PROJECT.md` — auto-watch requirement, batch calculator requirement, deferred features (ADVN-01)
- [Wowpedia — Prospecting](https://wowpedia.fandom.com/wiki/Prospecting) — probabilistic yield mechanics, 5-ore input confirmed
- [WoW Forums (Blizzard) — AH cut](https://us.forums.blizzard.com/en/wow/t/what-does-the-ah-take/346603) — 5% commodity AH cut confirmed
- [Livewire 4 Validation Docs](https://livewire.laravel.com/docs/4.x/validation) — wildcard array validation rules supported
- [Livewire 4 wire:model Docs](https://livewire.laravel.com/docs/4.x/wire-model) — array property binding pattern confirmed
- [Laravel Eloquent Relationships Docs](https://laravel.com/docs/12.x/eloquent-relationships) — `orderBy()` on `hasMany`, route model binding scoping

### Secondary (MEDIUM confidence)
- [The Lazy Goldmaker — Mathematics of Prospecting](https://thelazygoldmaker.com/the-mathematics-of-goldmaking-prospecting) — fractional yield mechanics, variance in shuffle outputs
- [The Lazy Goldmaker — Enchanting Shuffle](https://thelazygoldmaker.com/the-enchanting-shuffle-is-goldmaking-that-anyone-can-get-into) — multi-step shuffle patterns
- [Mozzletoff — BFA Inscription Milling Shuffle](https://gunnydelight.github.io/mozzletoff-wow-goldfarm-site/bfa-inscription-milling-shuffle.html) — milling yield ratios
- [Livewire Best Practices](https://github.com/michael-rubel/livewire-best-practices) — large model passing pitfall, non-deferred model binding as query killer
- Competitor analysis: TSM, Booty Bay Broker, Saddlebag Exchange, WoW Price Hub, Oribos Exchange — feature presence/absence; no dedicated web-based shuffle tracker found

### Tertiary (LOW confidence)
- [Undermine Exchange](https://undermine.exchange/) — in maintenance mode at research time; features inferred from search results
- [Blizzard AH Cut — Warmane Forum](https://forum.warmane.com/showthread.php?t=318786) — community confirmation of 5% cut; consistent with official behavior but community source
- [WoWAuctions.net](https://www.wowauctions.net/) — features inferred from search result snippet, not directly fetched

---
*Research completed: 2026-03-04*
*Ready for roadmap: yes*
