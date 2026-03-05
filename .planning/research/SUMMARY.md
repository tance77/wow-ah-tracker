# Project Research Summary

**Project:** WoW AH Tracker — v1.2 Crafting Profitability
**Domain:** WoW Auction House commodity price tracker with recipe-based crafting profit calculator
**Researched:** 2026-03-05
**Confidence:** HIGH overall — stack verified against existing codebase; API gaps documented via official Blizzard developer forums

## Executive Summary

The v1.2 Crafting Profitability milestone adds a recipe-based profit calculator to the existing WoW AH Tracker. The core task is walking a 4-step Blizzard API chain (profession index → profession detail → skill tier → recipe detail) to seed static recipe data, then wiring that data to the live commodity price infrastructure already in place. The good news: zero new Composer packages are required. Every capability needed — HTTP pooling, OAuth token acquisition, Eloquent models, Volt SFC pages — already exists and is in production. The new work is 3 migrations, 3 models, 1 Artisan command, 1 action class, and 2 Volt pages.

The recommended approach is to treat recipe data as static seed data fetched once per game patch via `artisan blizzard:sync-recipes`, mirroring the existing `blizzard:sync-catalog` command design. Profit is never stored — it is calculated live at render time from the latest `PriceSnapshot.median_price` values, exactly as shuffles calculate profit. The two new Livewire pages (profession overview and per-profession recipe table) follow the existing Volt SFC pattern. The one critical schema decision is to store per-quality crafted item IDs as explicit columns on the `recipes` table (`crafted_item_id_silver` and `crafted_item_id_gold`), because the Blizzard API does not reliably return both quality-tier item IDs from the recipe endpoint — this has been a known gap since Dragonflight.

The single highest-risk unknown is the `crafted_item` field gap in the Blizzard recipe API. Since Dragonflight, this field is absent for many expansion recipes, meaning the output item ID cannot be derived purely from the API. This must be resolved during the seeding phase using either Wowhead-sourced item ID mappings or the existing `assignQualityTiers()` name-based assignment already in the codebase. All other API gaps (missing modified slot quantities, absent yield counts, gear vs. commodity distinction) have documented mitigations. Build the `SeedRecipesCommand` first and validate the full API call chain against live data before building any UI.

## Key Findings

### Recommended Stack

The v1.2 stack is identical to v1.1 — no new packages. The existing Laravel 12 / Livewire 4 / Volt / SQLite / Pest 3 stack handles every new capability. The `BlizzardTokenService` is injected as-is into the new command. `Http::pool()` with 20-item batches — already implemented in `SyncCatalogCommand::processBatch()` — is the correct concurrent fetch pattern for 400-650 recipe detail API calls. All price arithmetic stays in copper integers using PHP 8.4 `intdiv()` — no BCMath, no floats.

**Core technologies (unchanged):**
- **Laravel 12 + PHP 8.4**: Framework, Eloquent ORM, Artisan commands — unchanged; all patterns already in production
- **Livewire 4 / Volt SFCs**: Two new pages (crafting overview, per-profession recipe table) — same pattern as existing `pages.shuffles` and `pages.shuffle-detail`
- **`Http::pool()` (built-in)**: Concurrent Blizzard API fetching for recipe detail layer — copy `SyncCatalogCommand::processBatch()` exactly; 20-item batches, 1-second pause between batches, 429 retry with 10-second backoff
- **SQLite**: Three new tables (`professions`, `recipes`, `recipe_reagents`) — pure Eloquent migrations, zero config changes
- **Pest 3**: Unit tests for `RecipeProfitAction`; integration tests for `SeedRecipesCommand`

**Critical integration note:** All recipe/profession endpoints use the `static-{region}` namespace — identical to the existing item detail lookups in `SyncCatalogCommand`. No new auth scopes or API credentials required.

### Expected Features

**Must have for v1.2 launch (table stakes):**
- Recipe import Artisan command (`blizzard:sync-recipes`) — seeds `professions`, `recipes`, `recipe_reagents` from Blizzard API; idempotent upsert on `blizzard_recipe_id`; re-runnable after patches
- Auto-watch reagents and crafted items — creates `WatchedItem` records on seeding so existing 15-min poller fetches prices; mirrors v1.1 shuffle auto-watch pattern with deduplication
- Profession overview page (`/crafting`) — cards per Midnight crafting profession with top 3-5 profitable recipes per card
- Per-profession recipe table (`/crafting/{profession}`) — all recipes with sortable columns: reagent cost, Tier 1 (Silver) profit, Tier 2 (Gold) profit, median profit
- Profit formula: `(sell_price × 0.95) - reagent_cost` applied per quality tier; 5% AH cut is non-negotiable
- Missing price indicator — flag recipes where any reagent or crafted item has no active price snapshot

**Should have (v1.2 differentiators):**
- Median profit column — `(tier1_profit + tier2_profit) / 2` — specified in PROJECT.md; simple to implement once T1/T2 are correct
- Price staleness indicator — flag recipes where any snapshot is > 1 hour old (same mechanism as shuffles)
- Per-recipe reagent cost breakdown — hover/expand showing each reagent line item with qty, unit price, subtotal

**Defer to v1.x after validation:**
- Manual crafted item ID override — escape hatch if API gaps prevent correct item ID resolution for specific recipes
- Periodic recipe refresh job — manual artisan command is sufficient; scheduling adds complexity without benefit

**Hard out-of-scope (v2+):**
- Gear/weapon crafting profit — requires connected-realm AH API; realm-specific pricing pipeline; different data shape
- Specialization-aware profit — requires character profile API with different OAuth scope
- Concentration optimizer — CraftSim territory; full simulation engine; explicitly deferred in PROJECT.md

### Architecture Approach

The v1.2 architecture is purely additive. Three new models (`Profession`, `Recipe`, `RecipeReagent`) bridge static recipe data to the existing `CatalogItem` / `PriceSnapshot` pipeline via `blizzard_item_id` foreign keys — the same join convention already used by `WatchedItem` and `ShuffleStep`. Profit is calculated at render time in a dedicated `RecipeProfitAction` class (no stored profit columns). Two new Volt SFC pages are added under `/crafting`. Navigation gets one new link. Two routes are added to `routes/web.php`. No existing files require schema changes.

**Major components:**
1. **`SeedRecipesCommand`** — one-time Artisan command; 4-step API chain; `Http::pool()` batches of 20; upserts on `blizzard_recipe_id`; creates auto-watch `WatchedItem` entries for all reagents and crafted items; `--dry-run` flag; progress bar
2. **`RecipeProfitAction`** — pure calculation class; takes a `Recipe` model with eager-loaded relationships; returns `['reagent_cost', 'crafted_value', 'profit']` per quality tier; independently unit-testable
3. **`pages.crafting` Volt SFC** — profession cards with top profitable recipes; eager-loads profession → recipes → reagents → latestPriceSnapshot; profit calculated in PHP before render
4. **`pages.crafting-profession` Volt SFC** — full sortable recipe table; sort state as Livewire public properties (`$sortBy`, `$sortDir`); collection sort in PHP, not DB `ORDER BY`; "price unavailable" and "realm AH — not tracked" row states

**Key patterns to follow:**
- Profit is never persisted — always calculated live from latest `PriceSnapshot.median_price`
- `crafted_item_id_silver` and `crafted_item_id_gold` stored as nullable columns; UI shows "price unavailable" when NULL
- Highest-ID skill tier per profession = current expansion tier (robust to Blizzard name format changes across expansions)
- All copper arithmetic via `intdiv()`; `formatGold()` called at display time only

### Critical Pitfalls

1. **`crafted_item` field absent from recipe API since Dragonflight** — The recipe endpoint does not reliably return the output item ID for expansion recipes. Store per-quality columns (`crafted_item_id_silver`, `crafted_item_id_gold`) as nullable. Use the existing `CatalogItem::assignQualityTiers()` name-based assignment as the primary resolution strategy. Must be resolved before any profit calculation is built; this is the Phase 1 gate.

2. **Quality tier item IDs are not derivable from the item API** — Silver and Gold variants return near-identical API responses with no `quality_tier` field. The existing `catalog_items.quality_tier` column populated by `assignQualityTiers()` already handles this: items with the same name are grouped and assigned T1/T2 in ascending `blizzard_item_id` order. Use this system rather than querying the item endpoint.

3. **AH cut must be applied to sell side only** — `profit = (sell_price * 0.95) - reagent_cost`. Define `const AH_CUT_RATE = 0.05`. Write a unit test: sell_price=10000, reagent_cost=5000 → assert profit=4500, not 5000. Reagent purchases do not incur a buyer-side fee.

4. **Auto-watch reagent deduplication** — Across ~10 professions with 40-80 recipes each, many reagents are shared (e.g., Midnight Ore used by both Blacksmithing and Jewelcrafting). Collect all unique `blizzard_item_id` values across all recipes into a set, then run one `firstOrCreate` per unique ID — not per recipe-reagent pair. Run `WatchedItem::groupBy('blizzard_item_id')->havingRaw('COUNT(*) > 1')->count()` after seeding and assert zero.

5. **Sequential recipe API fetch will appear frozen** — 400-650 recipe detail calls at ~200ms each = 80-130 seconds sequentially. Use `Http::pool()` with concurrency limit of 20. Add `$this->withProgressBar()` to the Artisan command. Target: full sync completes in under 60 seconds. Test against all Midnight professions, not just one.

6. **Gear output items are realm-AH, not commodities** — Plate armor and weapons never appear in the commodities feed regardless of polling frequency. Flag these as `is_commodity = false` during seeding. Display "realm AH — not tracked" in the UI. Do not scope gear crafting profit in v1.2 — scoping to consumables (flasks, potions, enchants) is the correct boundary.

7. **Recipe data seeded as a migration cannot be resynced after patches** — Content patches add new recipes. Use an idempotent Artisan command, not a migration file. Add `last_synced_at` timestamp to the `recipes` table. Show "Recipe data last synced: X days ago" in the UI with a warning if > 14 days old.

## Implications for Roadmap

The build order is tightly dependency-constrained. The API seeding layer must succeed and be validated against live Blizzard API data before any UI work begins. Four phases map cleanly to the dependency graph.

### Phase 1: Data Model and Seed Command

**Rationale:** Every other phase depends on recipe data in the database. The `SeedRecipesCommand` is the highest-risk deliverable — it validates the full 4-step Blizzard API call chain and resolves the `crafted_item` field gap. Schema decisions made here (per-quality item ID columns, `is_commodity` flag, `yield_quantity`, `has_optional_reagents`) are cheaper now than as retrofitted migrations later.

**Delivers:** Three migrations (professions, recipes, recipe_reagents), three Eloquent models, three Pest factories, and a working `artisan blizzard:sync-recipes` command that populates the database from the live Blizzard API with progress bar and `--dry-run` flag.

**Features addressed:** Recipe import, per-quality item ID resolution, `is_commodity` flagging, `yield_quantity` seeding (needed for alchemy/cooking), auto-watch creation for all reagents and crafted items.

**Pitfalls addressed:** Pitfall 1 (missing `crafted_item`), Pitfall 2 (quality tier IDs), Pitfall 4 (yield quantity absent from API), Pitfall 5 (auto-watch dedup), Pitfall 7 (idempotent command, not migration), Pitfall 8 (Http::pool concurrency), Pitfall 9 (commodity vs realm-AH flagging).

### Phase 2: Profit Calculation Action

**Rationale:** With real Midnight recipe data in the database, `RecipeProfitAction` can be built and unit-tested against actual recipes before any UI is wired. Correctness of the formula (5% AH cut, yield multiplier, T1/T2 tier separation, NULL price handling) is verified in isolation. This prevents debugging formula errors inside a Livewire component.

**Delivers:** `app/Actions/RecipeProfitAction.php` with Pest unit tests covering: AH cut (assert 4500 not 5000), yield quantity multiplier, NULL price handling returns null profit, both quality tiers independently calculated.

**Features addressed:** Tier 1 (Silver) profit, Tier 2 (Gold) profit, median profit calculation, 5% AH cut application.

**Pitfalls addressed:** Pitfall 3 (AH cut non-negotiable), `modified_crafting_slots` optional reagents flagged via `has_optional_reagents` boolean.

**Stack used:** PHP 8.4 `intdiv()` arithmetic; Pest 3 unit tests with factories.

### Phase 3: Profession Overview Page and Navigation

**Rationale:** The overview page (`/crafting`) is the entry point to the feature and validates data loading patterns, eager-load chain design, and route/navigation integration before tackling the more complex sortable detail table. Simpler page first establishes the pattern.

**Delivers:** `pages.crafting` Volt SFC with profession cards showing top 3-5 profitable recipes per profession, "Crafting" navigation link added to `navigation.blade.php`, and two new Volt routes in `web.php`.

**Features addressed:** Profession overview page, top-N profitable recipes per profession, navigation integration.

**Architecture:** Eager-load chain: profession → recipes → reagents → reagent.catalogItem.latestPriceSnapshot + craftedCatalogItem.latestPriceSnapshot. Consider extracting to `CraftingProfitService` if the eager-load chain is complex enough to reuse across both pages.

### Phase 4: Per-Profession Recipe Table

**Rationale:** The full sortable recipe table is the core user-facing deliverable. By this phase, recipe data is seeded, profit logic is tested, and the overview page pattern is established. This phase adds Livewire sort state, missing/stale price handling, and the non-commodity "realm AH" row state.

**Delivers:** `pages.crafting-profession` Volt SFC with complete sortable recipe table; columns: recipe name, reagent cost, Tier 1 profit, Tier 2 profit, median profit; `$sortBy` / `$sortDir` Livewire properties; "—" placeholder for missing prices; "realm AH — not tracked" for `is_commodity = false` recipes; optional reagent disclaimer for `has_optional_reagents = true` recipes.

**Features addressed:** Per-profession recipe table, sortable columns (default: median profit descending), missing price indicator, staleness indicator, non-commodity recipe display, optional reagent disclaimer.

**Pitfalls addressed:** N+1 loading (use `with(['reagents.catalogItem', 'craftedCatalogItem'])` eager load); collection sort in PHP, not DB `ORDER BY` (avoids complex Eloquent sort on computed columns).

### Phase Ordering Rationale

- Phase 1 is the critical path gate: no recipe data in the database means phases 2-4 work against factories that may not reflect actual Midnight recipe complexity (multi-reagent, quality tiers, multi-yield alchemy).
- Phase 2 before Phase 3-4: catching a missing AH cut or wrong yield multiplier in a unit test takes minutes; finding it inside a Livewire component render takes much longer.
- Phase 3 before Phase 4: the overview page is simpler (no sort state, no full table) and establishes the data loading and route patterns that Phase 4 reuses.
- Phases 3 and 4 may be built in the same pass if one developer is working the UI — the split only matters if parallelizing or if Phase 3 needs validation before Phase 4 begins.

### Research Flags

Phases needing deeper research during planning:

- **Phase 1:** The `crafted_item` field gap requires empirical validation against the live Midnight API. Add a `--report-gaps` flag to `SeedRecipesCommand` that logs the percentage of recipe responses with NULL `crafted_item`. If > 50% are missing, a Wowhead item ID mapping seed file is required before the command is considered complete.
- **Phase 1:** Midnight skill tier naming — the highest-ID skill tier heuristic is recommended based on community practice, but must be verified on the first live API run. Log the skill tier names returned per profession and confirm the heuristic selects the correct tier.

Phases with standard patterns (skip additional research):

- **Phase 2:** Profit formula arithmetic mirrors the existing shuffle `ProfitCalculator` pattern exactly. No new territory.
- **Phase 3 and 4:** Volt SFC page structure, sort state management, and eager loading are established patterns in the existing codebase. The `pages.shuffles` and `pages.shuffle-detail` pages provide direct precedents. No research phase needed.

## Confidence Assessment

| Area | Confidence | Notes |
|------|------------|-------|
| Stack | HIGH | Verified against existing production codebase. Zero new packages. `Http::pool()`, `BlizzardTokenService`, Volt SFCs — all patterns are already deployed and working. |
| Features | MEDIUM | Core mechanics (profit formula, quality tiers, profession grouping) confirmed across multiple sources. Midnight-specific quality tier simplification (Silver/Gold, no Bronze) confirmed via community guides. API behavior for quality-tier item IDs remains the one empirically unresolved area. |
| Architecture | HIGH | Data model design derived from existing `blizzard_item_id` FK conventions. Build order follows the dependency graph exactly. Anti-patterns documented with concrete rationale. Existing codebase directly inspected. |
| Pitfalls | MEDIUM-HIGH | Nine critical pitfalls identified. API gaps confirmed via official Blizzard developer forum threads spanning Dragonflight through The War Within (September 2024). Midnight-specific behavior extrapolated from TWW patterns given API continuity. Empirical validation of `crafted_item` gap behavior for Midnight recipes is the remaining uncertainty. |

**Overall confidence:** HIGH for build approach and architecture. MEDIUM for exact Blizzard API behavior with Midnight expansion recipes — empirical validation during Phase 1 will resolve the remaining uncertainty quickly.

### Gaps to Address

- **`crafted_item` field behavior for Midnight recipes:** Research confirms the gap persists through TWW (September 2024). Whether Blizzard resolved it for Midnight is unknown. During Phase 1, log the percentage of recipe detail responses that include `crafted_item`. If > 80% are present, the existing name-based join via `assignQualityTiers()` is sufficient. If < 50%, a Wowhead mapping seed file is required — build that path before completing Phase 1.

- **Midnight skill tier ID:** The highest-ID skill tier heuristic is sound but untested against Midnight's profession index. Validate during the first Phase 1 test run. Log the skill tier names returned and confirm the selection.

- **`modified_crafting_slots` quantity bug in Midnight:** Confirmed absent in previous expansions. During Phase 1, log how many recipe responses include `modified_crafting_slots` with non-zero quantities. Use the `has_optional_reagents` boolean flag and UI disclaimer as the fallback regardless of what the API returns.

- **Yield quantity for alchemy/cooking:** The API's `crafted_quantity.value` field may be absent for multi-yield recipes. After Phase 1 seeding, verify at least one potion recipe has `yield_quantity > 1`. If the API populates this correctly for Midnight, the Wowhead supplemental sourcing step can be skipped.

## Sources

### Primary (HIGH confidence)

- Existing codebase (`SyncCatalogCommand.php`, `BlizzardTokenService.php`, `CatalogItem.php`, migrations, Volt SFC pages) — directly inspected; all patterns verified in production
- Blizzard Developer Forum — [Dragonflight profession recipes crafted item id](https://us.forums.blizzard.com/en/blizzard/t/dragonflight-profession-recipes-crafted-item-id/37444) — `crafted_item` absent confirmed; persists through TWW
- Blizzard Developer Forum — [Help with reagent quality from API](https://us.forums.blizzard.com/en/blizzard/t/help-with-reagent-quality-from-api/51961) — quality tier not in item API; each tier is a distinct item ID
- Blizzard Developer Forum — [Commodities API - Item Rank/Quality](https://us.forums.blizzard.com/en/blizzard/t/commodities-api-item-rankquality/51895) — quality not exposed through Dragonflight; manual mapping required
- Wowhead — [Midnight Alchemy overview](https://www.wowhead.com/guide/midnight/professions/alchemy-overview-trainer-locations-recipes-tools) — ~39 recipes; Silver/Gold two-tier system confirmed
- WoW Forums — [AH cut](https://us.forums.blizzard.com/en/wow/t/what-does-the-ah-take/346603) — 5% commodity AH cut confirmed; no buyer-side fee
- Blizzard Developer Forum — [Is it possible to find the recipe item ID in TWW?](https://us.forums.blizzard.com/en/blizzard/t/is-it-possible-to-find-the-recipe-item-id-in-tww/52052) — gap confirmed as of September 2024

### Secondary (MEDIUM confidence)

- BlizzardApi Ruby gem — [Profession class](https://rubydoc.info/gems/blizzard_api/BlizzardApi/Wow/Profession) — endpoint URL shapes and parameter names confirmed
- Blizzard Developer Forum — [Missing modified_crafting_slots quantity](https://us.forums.blizzard.com/en/blizzard/t/missing-modifiedcraftingslots-quantity-in-recipe-endpoint/49170) — optional reagent quantity bug confirmed; unresolved
- Blizzard Developer Forum — [BUG Professions API](https://us.forums.blizzard.com/en/blizzard/t/bug-professions-api/6234) — multi-yield `crafted_quantity` absent for alchemy/cooking
- WoW-Professions.com — Midnight Alchemy and Blacksmithing guides — quality tier mechanics, Silver/Gold two-tier confirmed
- WowCrafters — web competitor feature overview — confirms web-based crafting profit tools exist; no open-source pricing methodology
- Multiple community gold guides (Icy Veins, dtgre.com) — Silver/Gold quality price behavior; concentration mechanic impact

### Tertiary (LOW confidence)

- Midnight expansion recipe API behavior — empirical validation pending; all findings extrapolated from TWW patterns given Blizzard API continuity
- `crafted_item` gap resolution for Midnight specifically — unknown until Phase 1 seeder runs against live API and logs results

---
*Research completed: 2026-03-05*
*Ready for roadmap: yes*
