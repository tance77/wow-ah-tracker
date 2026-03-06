---
gsd_state_version: 1.0
milestone: v1.0
milestone_name: milestone
status: completed
stopped_at: Completed quick-26
last_updated: "2026-03-06T00:22:54.251Z"
last_activity: 2026-03-06 - Completed quick task 27: Fix recipe list profit columns not rendering
progress:
  total_phases: 8
  completed_phases: 8
  total_plans: 15
  completed_plans: 15
  percent: 100
---

# Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-03-06)

**Core value:** See at a glance when crafting material prices dip or spike so I can act on buy/sell opportunities before the market corrects.
**Current focus:** Planning next milestone

## Current Position

Milestone: v1.2 Crafting Profitability — SHIPPED 2026-03-06
Status: Between milestones — run /gsd:new-milestone to start next
Last activity: 2026-03-06 - Completed v1.2 milestone archival
Last activity: 2026-03-05 - Completed Phase 16 Plan 02 (recipe table UI)

Progress: [██████████] 100%

## Performance Metrics

**Velocity:**
- Total plans completed: 8 (v1.1 phases 9-12)
- Average duration: ~4 min
- Total execution time: ~33 min

**By Phase:**

| Phase | Plans | Total | Avg/Plan |
|-------|-------|-------|----------|
| v1.1 phases (9-12) | 8 | ~33 min | ~4 min |

*Updated after each plan completion*
| Phase 13-recipe-data-model-and-seed-command P01 | 2 | 1 tasks | 10 files |
| Phase 13-recipe-data-model-and-seed-command P02 | 6 | 1 tasks | 2 files |
| Phase 14-profit-calculation-action P01 | 2 min | 1 tasks | 2 files |
| Phase 15-profession-overview-page-and-navigation P01 | 3 min | 2 tasks | 8 files |
| Phase 15-profession-overview-page-and-navigation P02 | 6 min | 2 tasks | 1 files |
| Phase 16-per-profession-recipe-table P01 | 3 min | 2 tasks | 2 files |
| Phase 16-per-profession-recipe-table P02 | 2 min | 2 tasks | 1 files |

## Accumulated Context

### Decisions

Decisions are logged in PROJECT.md Key Decisions table.
v1.0 and v1.1 decisions archived — see milestones/ for full history.

**v1.2 critical pre-decisions (from research):**
- Store per-quality crafted item IDs as nullable columns (`crafted_item_id_silver`, `crafted_item_id_gold`) — Blizzard API does not reliably return both
- Profit calculated live at render time from `PriceSnapshot.median_price` — never persisted
- Use highest-ID skill tier per profession to identify current expansion tier (robust to name changes)
- `Http::pool()` with 20-item batches for recipe detail fetch — sequential would take 80-130 seconds
- Gear output items flagged `is_commodity = false` and displayed as "realm AH — not tracked"
- [Phase 13-recipe-data-model-and-seed-command]: Dual nullable FK columns (crafted_item_id_silver, crafted_item_id_gold) with nullOnDelete for quality-tier crafted items
- [Phase 13-recipe-data-model-and-seed-command]: Http::fake() patterns must be ordered most-specific first — skill-tier and media patterns before generic profession/ID wildcard
- [Phase 13-recipe-data-model-and-seed-command]: Reagents use delete+re-insert for idempotency; unknown reagent items auto-create minimal CatalogItem entries to satisfy FK
- [Phase 14-profit-calculation-action]: Return null for reagent_cost (not partial sum) when any reagent has no price — prevents silent cost understatement
- [Phase 14-profit-calculation-action]: median_profit = (T1+T2)/2 when both tiers present, not statistical median over time series
- [Phase 15-profession-overview-page-and-navigation]: Slug route model binding on Profession via getRouteKeyName() override
- [Phase 15-profession-overview-page-and-navigation]: Model booted() events for auto-slug generation on create/update
- [Phase 15-profession-overview-page-and-navigation]: Dynamic underscore-prefixed properties on Profession model for computed display data
- [Phase 15-profession-overview-page-and-navigation]: onerror img handler for graceful broken icon URL fallback
- [Phase 16-per-profession-recipe-table]: Server-side #[Computed] recipeData builds full dataset passed to Alpine via @js(); staleness threshold at 60 minutes
- [Phase 16-per-profession-recipe-table]: Alpine-only client-side sorting/filtering with partition-sort ensuring missing-price and non-commodity rows always at bottom
- [Phase quick-26]: Use Storage::disk() (default) instead of Storage::disk('local') for cloud-configurable shared storage

### Pending Todos

None.

### Blockers/Concerns

- **Phase 13 gate:** `crafted_item` field absent from Blizzard recipe API since Dragonflight. Use `--report-gaps` flag and `assignQualityTiers()` name-based resolution. If >50% missing, Wowhead mapping seed file required before Phase 13 is complete.
- **Phase 13 gate:** Validate highest-ID skill tier heuristic on first live API run — log tier names returned per profession and confirm selection.

### Quick Tasks Completed

| # | Description | Date | Commit | Directory |
|---|-------------|------|--------|-----------|
| 1 | Add helpful descriptions to Distance to Buy, Distance to Sell, and 7-Day Volatility on item page | 2026-03-04 | 5998a9f | [1-add-helpful-descriptions-to-distance-to-](./quick/1-add-helpful-descriptions-to-distance-to-/) |
| 2 | Fix incorrect "Time since last update" on item page — use ordered DB query for latest snapshot | 2026-03-04 | 4511c69 | [2-fix-incorrect-time-since-last-update-on-](./quick/2-fix-incorrect-time-since-last-update-on-/) |
| 3 | Improve readability of buy/sell signal alert bar — structured labeled layout with bright values | 2026-03-04 | a06385f | [3-improve-readability-of-buy-sell-signal-a](./quick/3-improve-readability-of-buy-sell-signal-a/) |
| 4 | Add descriptions to all remaining stat cards on item detail page | 2026-03-04 | 875de47 | [4-add-descriptions-to-all-remaining-item-p](./quick/4-add-descriptions-to-all-remaining-item-p/) |
| 5 | Add profession grouping to dashboard with manual tagging on watchlist | 2026-03-04 | bf4656c | [5-add-profession-grouping-to-dashboard-wit](./quick/5-add-profession-grouping-to-dashboard-wit/) |
| 6 | Add tooltips and inline 7-day avg to dashboard signals and trend arrows | 2026-03-04 | 1940891 | [6-add-tooltips-and-inline-7-day-avg-to-das](./quick/6-add-tooltips-and-inline-7-day-avg-to-das/) |
| 7 | Add register link to login page | 2026-03-04 | 8e24758 | [7-add-register-button-to-login-page](./quick/7-add-register-button-to-login-page/) |
| 8 | Spruce up login page layout — button full-width on own row, links centered below | 2026-03-04 | d14db15 | [8-spruce-up-login-page-layout](./quick/8-spruce-up-login-page-layout/) |
| 9 | Change name field to username on register/profile/navigation — DB migration + all views + tests | 2026-03-05 | 3eaa4fb | [9-change-name-field-to-username-on-registe](./quick/9-change-name-field-to-username-on-registe/) |
| 10 | Fix New Shuffle button outside Livewire boundary — move from header slot into Livewire-tracked DOM | 2026-03-04 | fc2a062 | [10-the-new-shuffle-button-after-there-is-al](./quick/10-the-new-shuffle-button-after-there-is-al/) |
| 11 | Make step wizard more user-friendly — step numbering, conversion ratio summaries, sectioned add-step form | 2026-03-05 | 8b2e325 | [11-make-the-step-wizard-more-user-friendly](./quick/11-make-the-step-wizard-more-user-friendly/) |
| 12 | Add --realm flag to sync-catalog for BoE auction items from connected-realm endpoint | 2026-03-05 | 6e2149d | [12-add-realm-auction-support-to-blizzard-sy](./quick/12-add-realm-auction-support-to-blizzard-sy/) |
| 13 | Fix realm sync stopping after fetching auctions — stream response to disk instead of loading into memory | 2026-03-05 | 18e1cc5 | [13-fix-realm-sync-stopping-after-fetching-a](./quick/13-fix-realm-sync-stopping-after-fetching-a/) |
| 14 | Fix realm auction regex to handle item objects with extra fields (context, bonus_list, modifiers) | 2026-03-05 | ea8d09d | [14-fix-realm-auction-regex-to-handle-item-o](./quick/14-fix-realm-auction-regex-to-handle-item-o/) |
| 15 | Add hourly realm auction price polling for BoE items — parallel pipeline with brace-depth parsing | 2026-03-05 | 267682f | [15-add-hourly-realm-auction-price-polling-f](./quick/15-add-hourly-realm-auction-price-polling-f/) |
| 16 | Add secondary byproducts with drop chance to shuffle steps — model, UI, calculator EV | 2026-03-05 | 1df5c82 | [16-add-secondary-byproducts-with-drop-chanc](./quick/16-add-secondary-byproducts-with-drop-chanc/) |
| 17 | Fix blizzard:sync-catalog command timeout on Laravel Cloud (exceeds 15min limit) | 2026-03-05 | b44b10d | [17-fix-blizzard-sync-catalog-command-timeou](./quick/17-fix-blizzard-sync-catalog-command-timeou/) |
| 18 | Refactor sync-catalog to dispatch batched jobs instead of processing inline | 2026-03-05 | 0c6b84f | [18-refactor-sync-catalog-to-dispatch-batche](./quick/18-refactor-sync-catalog-to-dispatch-batche/) |
| 19 | Merge commodity and realm price polling into single hourly run | 2026-03-05 | acde456 | [19-merge-commodity-and-realm-price-polling-](./quick/19-merge-commodity-and-realm-price-polling-/) |
| 20 | Fix shuffle batch calculator: show byproduct values and cap copper to 2 decimals | 2026-03-05 | ffd24b8 | [20-fix-shuffle-batch-calculator-show-byprod](./quick/20-fix-shuffle-batch-calculator-show-byprod/) |
| 21 | Clone shuffle from shuffles list with all steps, byproducts, and auto-watched items | 2026-03-05 | 0029b08 | [21-being-able-to-clone-a-shuffle-on-the-shu](./quick/21-being-able-to-clone-a-shuffle-on-the-shu/) |
| 22 | Export and import shuffles as JSON for sharing between accounts | 2026-03-05 | 8ff5927 | [22-export-and-import-shuffles-as-json-for-s](./quick/22-export-and-import-shuffles-as-json-for-s/) |
| 23 | Rename shuffle button labels: Import JSON -> Import Shuffle, Export -> Share | 2026-03-05 | c4cbba7 | [23-rename-import-json-to-import-shuffle-and](./quick/23-rename-import-json-to-import-shuffle-and/) |
| 24 | Replace file export/import with clipboard copy/paste for shuffles | 2026-03-05 | d2c2d24 | [24-replace-file-export-import-with-clipboar](./quick/24-replace-file-export-import-with-clipboar/) |
| 25 | Fix share button — wrong Livewire 3 event detail access pattern | 2026-03-06 | 964017d | [25-fix-share-button-wrong-livewire-3-event-](./quick/25-fix-share-button-wrong-livewire-3-event-/) |
| 26 | Fix fopen error on Cloud -- replace absolute paths with Storage facade keys | 2026-03-06 | 5193093 | [26-fix-fopen-error-on-cloud-use-storage-fac](./quick/26-fix-fopen-error-on-cloud-use-storage-fac/) |
| 27 | Fix recipe list profit columns not rendering and default sort to highest profit | 2026-03-06 | ba7896a | [27-fix-recipe-list-profit-columns-not-rende](./quick/27-fix-recipe-list-profit-columns-not-rende/) |
| 28 | Scope watched item cleanup to current user in shuffle operations | 2026-03-06 | 6b4444d | [28-scope-watched-item-cleanup-to-current-us](./quick/28-scope-watched-item-cleanup-to-current-us/) |
| 29 | Remove auto-watch from SyncRecipesCommand — watchlist is user-managed only | 2026-03-06 | 5eca757 | [29-remove-auto-watch-from-syncrecipescomman](./quick/29-remove-auto-watch-from-syncrecipescomman/) |
| 30 | Fix recipe list blank Tier 1, Tier 2, and Median Profit columns — always render 3 td cells | 2026-03-05 | 0a131f5 | [30-fix-recipe-list-blank-tier-1-tier-2-and-](./quick/30-fix-recipe-list-blank-tier-1-tier-2-and-/) |

## Session Continuity

Last session: 2026-03-06T00:32:00Z
Stopped at: Completed quick-30
Resume file: None
