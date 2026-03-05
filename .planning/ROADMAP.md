# Roadmap: WoW AH Tracker

## Milestones

- ✅ **v1.0 MVP** — Phases 1-8 (shipped 2026-03-02)
- 🚧 **v1.1 Shuffles** — Phases 9-12 (in progress)

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

### 🚧 v1.1 Shuffles (In Progress)

**Milestone Goal:** Add a Shuffles section where users can define multi-step item conversion chains and calculate batch profit using live AH prices.

- [x] **Phase 9: Data Foundation** — Schema, models, and factories for shuffles and shuffle steps (completed 2026-03-05)
- [x] **Phase 10: Shuffle CRUD and Navigation** — List, create, edit, and delete named shuffles with navigation access (completed 2026-03-05)
- [ ] **Phase 11: Step Editor, Yield Config, and Auto-Watch** — Add/remove/reorder steps with yield ranges; auto-watch items for price polling
- [ ] **Phase 12: Batch Calculator and Profit Summary** — Enter input quantity, see per-step yields and costs, total profit with AH cut and break-even

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
**Plans:** 1/2 plans executed

Plans:
- [ ] 11-01-PLAN.md — Schema migrations (input_qty, nullable thresholds), ShuffleStep model updates, and feature tests
- [ ] 11-02-PLAN.md — Full step editor UI with item search, yield config, reorder, and auto-watch

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
**Plans**: TBD

## Progress

**Execution Order:** 9 -> 10 -> 11 -> 12

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
| 11. Step Editor, Yield Config, and Auto-Watch | 1/2 | In Progress|  | - |
| 12. Batch Calculator and Profit Summary | v1.1 | 0/? | Not started | - |
