# Roadmap: WoW AH Tracker

## Milestones

- ✅ **v1.0 MVP** — Phases 1-8 (shipped 2026-03-02)
- ✅ **v1.1 Shuffles** — Phases 9-12 (shipped 2026-03-05)
- 🚧 **v1.2 Crafting Profitability** — Phases 13-16 (in progress)

## Phases

<details>
<summary>✅ v1.0 MVP (Phases 1-8) — SHIPPED 2026-03-02</summary>

- [x] Phase 1: Project Foundation (2/2 plans) — completed 2026-03-01
- [x] Phase 2: Authentication (2/2 plans) — completed 2026-03-01
- [x] Phase 3: Item Watchlist Management (3/3 plans) — completed 2026-03-01
- [x] Phase 4: Blizzard API Integration (3/3 plans) — completed 2026-03-01
- [x] Phase 5: Data Ingestion Pipeline (2/2 plans) — completed 2026-03-01
- [x] Phase 6: Data Integrity Safeguards (2/2 plans) — completed 2026-03-01
- [x] Phase 7: Dashboard and Price Charts (2/2 plans) — completed 2026-03-01
- [x] Phase 8: Buy/Sell Signal Indicators (2/2 plans) — completed 2026-03-02

Full details: `milestones/v1.0-ROADMAP.md`

</details>

<details>
<summary>✅ v1.1 Shuffles (Phases 9-12) — SHIPPED 2026-03-05</summary>

- [x] Phase 9: Data Foundation (2/2 plans) — completed 2026-03-05
- [x] Phase 10: Shuffle CRUD and Navigation (2/2 plans) — completed 2026-03-05
- [x] Phase 11: Step Editor, Yield Config, and Auto-Watch (2/2 plans) — completed 2026-03-05
- [x] Phase 12: Batch Calculator and Profit Summary (2/2 plans) — completed 2026-03-05

</details>

### v1.2 Crafting Profitability (In Progress)

**Milestone Goal:** Add a Crafting section that shows profit margins for all Midnight expansion recipes using live AH prices, organized by profession with sortable tables.

- [x] **Phase 13: Recipe Data Model and Seed Command** — Three migrations, three models, and a working `artisan blizzard:sync-recipes` command that seeds the database from the Blizzard API (completed 2026-03-05)
- [x] **Phase 14: Profit Calculation Action** — `RecipeProfitAction` class with unit-tested profit formula covering Tier 1/Tier 2, AH cut, and NULL price handling (completed 2026-03-05)
- [ ] **Phase 15: Profession Overview Page and Navigation** — `/crafting` page with profession cards showing top profitable recipes; "Crafting" nav link
- [ ] **Phase 16: Per-Profession Recipe Table** — `/crafting/{profession}` page with full sortable recipe table, missing-price indicators, staleness warnings, and non-commodity row states

## Phase Details

### Phase 9: Data Foundation
**Goal**: The shuffle data model is correct, migration-safe, and ready for all subsequent phases to build on
**Depends on**: Phase 8 (v1.0 complete)
**Requirements**: None directly — pure infrastructure prerequisite for all v1.1 requirements
**Success Criteria** (what must be TRUE):
  1. Running migrations creates `shuffles` and `shuffle_steps` tables with integer yield columns (`output_qty_min`, `output_qty_max`) and no float columns for quantities
  2. `Shuffle` and `ShuffleStep` Eloquent models exist with correct relationships (`User::shuffles()`, `Shuffle::steps()` ordered by `sort_order`)
  3. `ShuffleFactory` and `ShuffleStepFactory` can seed test data; Pest tests can create and relate shuffles without errors
  4. Cascade deletes work: deleting a shuffle removes all its steps from the database
**Plans:** 2/2 plans complete

Plans:
- [x] 09-01-PLAN.md — Migrations and Eloquent models (shuffles, shuffle_steps, provenance FK)
- [x] 09-02-PLAN.md — Factories and comprehensive test suite

### Phase 10: Shuffle CRUD and Navigation
**Goal**: Users can access a dedicated Shuffles section, view all their saved shuffles with profitability badges, and create or delete shuffles
**Depends on**: Phase 9
**Requirements**: SHUF-01, SHUF-03, SHUF-04, SHUF-05
**Success Criteria** (what must be TRUE):
  1. A "Shuffles" link appears in the main navigation (desktop and mobile) and routes to `/shuffles`
  2. User can create a new named shuffle and see it appear in the shuffles list immediately
  3. User can rename an existing shuffle and see the updated name reflected in the list
  4. User can delete a shuffle and it is removed from the list
  5. Shuffles list shows a profitability badge (green/red or neutral) next to each shuffle name
**Plans:** 2/2 plans complete

Plans:
- [x] 10-01-PLAN.md — Routes, navigation, profitPerUnit model method, shuffles list page, and feature tests
- [x] 10-02-PLAN.md — Shuffle detail shell page and visual verification

### Phase 11: Step Editor, Yield Config, and Auto-Watch
**Goal**: Users can build multi-step conversion chains on a shuffle detail page, configure fixed or variable yield ratios per step, reorder steps, and have all items auto-watched for price polling
**Depends on**: Phase 10
**Requirements**: SHUF-02, YILD-01, YILD-02, YILD-03, INTG-01
**Success Criteria** (what must be TRUE):
  1. User can add a conversion step to a shuffle by selecting an input item and output item; chains of 2 or more steps are supported (A -> B -> C)
  2. User can set a fixed yield quantity per step (e.g., 5 ore -> 1 gem)
  3. User can set a min/max yield range per step for probabilistic conversions (e.g., 5 ore -> 1-3 gems)
  4. User can drag or click to reorder steps within a chain and the new order is saved
  5. When a step is added or saved, all input and output items in the chain are automatically added to the watchlist (using `firstOrCreate` — existing watched items and thresholds are not modified)
**Plans:** 2/2 plans complete

Plans:
- [x] 11-01-PLAN.md — Schema migrations (input_qty, nullable thresholds), ShuffleStep model updates, and feature tests
- [x] 11-02-PLAN.md — Full step editor UI with item search, yield config, reorder, and auto-watch

### Phase 12: Batch Calculator and Profit Summary
**Goal**: Users can enter an input quantity and immediately see cascading yields, per-step cost and value breakdowns, total profit after the 5% AH cut, and the break-even input price — all calculated from live AH median prices
**Depends on**: Phase 11
**Requirements**: INTG-02, INTG-03, CALC-01, CALC-02, CALC-03, CALC-04
**Success Criteria** (what must be TRUE):
  1. User can type an input quantity and see cascading output yields for every step update without per-keystroke server round-trips
  2. User can see a per-step breakdown showing cost (input value) and value (output value) for the entered quantity
  3. User can see a profit summary row showing total cost in, total value out after 5% AH cut, and net profit — all displayed in gold/silver/copper format
  4. User can see the break-even input price (maximum they can pay per input unit and still profit)
  5. A staleness warning is shown when the latest price snapshot for any item in the chain is older than 1 hour
**Plans:** 2/2 plans complete

Plans:
- [x] 12-01-PLAN.md — Refactor profitPerUnit() cascade, add priceData/calculatorSteps computed properties, and tests
- [x] 12-02-PLAN.md — Alpine.js batch calculator UI with step breakdown, profit summary, and visual verification

### Phase 13: Recipe Data Model and Seed Command
**Goal**: Recipe data from all Midnight expansion professions is seeded into the local database and all reagents and crafted items are auto-watched for price polling
**Depends on**: Phase 12 (v1.1 complete)
**Requirements**: IMPORT-01, IMPORT-02, IMPORT-03, IMPORT-04, IMPORT-05, IMPORT-06
**Success Criteria** (what must be TRUE):
  1. Running `artisan blizzard:sync-recipes` populates `professions`, `recipes`, and `recipe_reagents` tables with data from the live Blizzard API for all Midnight expansion professions
  2. All reagents and crafted items seeded by the command appear in the watchlist so the 15-minute poller fetches their prices (no duplicates created on re-run)
  3. Running the command a second time (simulating a game patch) updates existing records rather than creating duplicates — the database state is identical after any number of runs
  4. Running `artisan blizzard:sync-recipes --dry-run` logs what would be written without modifying any database rows
  5. Running `artisan blizzard:sync-recipes --report-gaps` logs the percentage of recipe API responses missing `crafted_item`, `crafted_quantity`, or other tracked fields
  6. Each recipe row has a `last_synced_at` timestamp reflecting when it was last fetched from the API
**Plans**: 2/2 plans complete

Plans:
- [x] 13-01-PLAN.md — Migrations, models, and factories (professions, recipes, recipe_reagents)
- [x] 13-02-PLAN.md — SyncRecipesCommand with three-level API traversal, auto-watch, and tests

### Phase 14: Profit Calculation Action
**Goal**: Profit for any recipe can be calculated correctly from live AH prices, with the 5% AH cut applied to the sell side, per-quality-tier breakdown, and NULL prices handled gracefully
**Depends on**: Phase 13
**Requirements**: PROFIT-01, PROFIT-02, PROFIT-03, PROFIT-04
**Success Criteria** (what must be TRUE):
  1. Reagent cost for a recipe equals the sum of (reagent quantity x median AH price) across all reagents, calculated from the latest price snapshots
  2. Crafted item sell price is shown separately for Tier 1 (Silver) and Tier 2 (Gold) quality
  3. Profit is calculated as `(sell_price * 0.95) - reagent_cost` — a unit test asserts sell_price=10000, reagent_cost=5000 produces profit=4500 (not 5000)
  4. Median profit across both tiers is calculable and displayed per recipe
**Plans**: 1 plan

Plans:
- [ ] 14-01-PLAN.md — RecipeProfitAction TDD (action class + comprehensive Pest tests)

### Phase 15: Profession Overview Page and Navigation
**Goal**: Users can navigate to a Crafting section and see all Midnight professions at a glance with the most profitable recipes highlighted per profession
**Depends on**: Phase 14
**Requirements**: OVERVIEW-01, OVERVIEW-02, NAV-01
**Success Criteria** (what must be TRUE):
  1. A "Crafting" link appears in the main navigation and routes to `/crafting`
  2. The `/crafting` page displays one card per Midnight crafting profession
  3. Each profession card shows the top 3-5 most profitable recipes with their median profit values
**Plans**: TBD

### Phase 16: Per-Profession Recipe Table
**Goal**: Users can view all recipes for a single profession in a sortable table, see full profit breakdowns, and identify recipes with missing or stale price data
**Depends on**: Phase 15
**Requirements**: TABLE-01, TABLE-02, TABLE-03, TABLE-04, TABLE-05, TABLE-06
**Success Criteria** (what must be TRUE):
  1. Clicking a profession card navigates to `/crafting/{profession}` showing all recipes in a table sorted by median profit descending by default; clicking any column header re-sorts the table
  2. Each table row shows: recipe name, reagent cost, Tier 1 profit, Tier 2 profit, and median profit
  3. Recipes where any reagent or crafted item has no price snapshot are flagged with a visible "price unavailable" indicator
  4. A staleness warning is displayed when any price snapshot used in the table is more than 1 hour old
  5. Clicking a recipe row or expand control reveals a per-reagent cost breakdown showing quantity, unit price, and subtotal for each reagent
  6. Recipes that produce non-commodity gear items display "realm AH — not tracked" instead of profit values
**Plans**: TBD

## Progress

**Execution Order:** 9 -> 10 -> 11 -> 12 -> 13 -> 14 -> 15 -> 16

| Phase | Milestone | Plans Complete | Status | Completed |
|-------|-----------|----------------|--------|-----------|
| 1. Project Foundation | v1.0 | 2/2 | Complete | 2026-03-01 |
| 2. Authentication | v1.0 | 2/2 | Complete | 2026-03-01 |
| 3. Item Watchlist Management | v1.0 | 3/3 | Complete | 2026-03-01 |
| 4. Blizzard API Integration | v1.0 | 3/3 | Complete | 2026-03-01 |
| 5. Data Ingestion Pipeline | v1.0 | 2/2 | Complete | 2026-03-01 |
| 6. Data Integrity Safeguards | v1.0 | 2/2 | Complete | 2026-03-01 |
| 7. Dashboard and Price Charts | v1.0 | 2/2 | Complete | 2026-03-01 |
| 8. Buy/Sell Signal Indicators | v1.0 | 2/2 | Complete | 2026-03-02 |
| 9. Data Foundation | v1.1 | 2/2 | Complete | 2026-03-05 |
| 10. Shuffle CRUD and Navigation | v1.1 | 2/2 | Complete | 2026-03-05 |
| 11. Step Editor, Yield Config, and Auto-Watch | v1.1 | 2/2 | Complete | 2026-03-05 |
| 12. Batch Calculator and Profit Summary | v1.1 | 2/2 | Complete | 2026-03-05 |
| 13. Recipe Data Model and Seed Command | v1.2 | 2/2 | Complete | 2026-03-05 |
| 14. Profit Calculation Action | 1/1 | Complete   | 2026-03-05 | - |
| 15. Profession Overview Page and Navigation | v1.2 | 0/TBD | Not started | - |
| 16. Per-Profession Recipe Table | v1.2 | 0/TBD | Not started | - |
