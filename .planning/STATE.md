---
gsd_state_version: 1.0
milestone: v1.2
milestone_name: Crafting Profitability
status: defining
stopped_at: —
last_updated: "2026-03-05"
last_activity: 2026-03-05 - Completed quick task 12: Add realm auction support to blizzard:sync-catalog for BoE gear
progress:
  total_phases: 0
  completed_phases: 0
  total_plans: 0
  completed_plans: 0
  percent: 0
---

# Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-03-05)

**Core value:** See at a glance when crafting material prices dip or spike so users can act on buy/sell opportunities before the market corrects.
**Current focus:** Defining requirements for v1.2 Crafting Profitability

## Current Position

Phase: Not started (defining requirements)
Plan: —
Status: Defining requirements
Last activity: 2026-03-05 - Completed quick task 12: Add realm auction support to blizzard:sync-catalog for BoE gear

Progress: [░░░░░░░░░░] 0%

## Performance Metrics

**Velocity:**
- Total plans completed: 2 (v1.1)
- Average duration: 1.5 min
- Total execution time: 3 min

**By Phase:**

| Phase | Plans | Total | Avg/Plan |
|-------|-------|-------|----------|
| 09-data-foundation | 2 | 3 min | 1.5 min |

*Updated after each plan completion*
| Phase 10-shuffle-crud-navigation P01 | 2 | 3 tasks | 6 files |
| Phase 10-shuffle-crud-navigation P02 | 10 | 2 tasks | 3 files |
| Phase 11-step-editor-yield-config-and-auto-watch P01 | 3 | 2 tasks | 4 files |
| Phase 11-step-editor-yield-config-and-auto-watch P02 | 9min | 2 tasks | 4 files |
| Phase 12-batch-calculator-and-profit-summary P01 | 4min | 2 tasks | 3 files |
| Phase 12-batch-calculator-and-profit-summary P02 | 10 | 2 tasks | 2 files |

## Accumulated Context

### Decisions

Decisions are logged in PROJECT.md Key Decisions table.
All v1.0 decisions archived — see `milestones/v1.0-ROADMAP.md` for full history.

**v1.1 decisions resolved in Phase 9:**
- Auto-watch provenance: nullable `created_by_shuffle_id` FK on `watched_items` (FK approach confirmed, implemented)
- Yield schema: `output_qty_min` / `output_qty_max` as `unsignedInteger` columns (implemented in migration)
- Orphan cleanup: `deleting` model event (before delete) on Shuffle model, not DB-level cascade
- Blizzard item IDs on shuffle_steps: `unsignedBigInteger` per project convention
- [Phase 09-data-foundation]: Orphan cleanup subquery uses 'wi2.id' not bare 'id' to avoid SQLite ambiguous column error when joining multiple tables
- [Phase 09-data-foundation]: ShuffleStepFactory uses Shuffle::factory() for shuffle_id to enable standalone step creation in tests
- [Phase 10-shuffle-crud-navigation]: profitPerUnit() uses naive first-in/last-out calculation for Phase 10 badge display; Phase 12 batch calculator will refine for multi-step chains
- [Phase 10-shuffle-crud-navigation]: Shuffle detail page is a shell only in Phase 10 — step editor ships in Phase 11
- [Phase 10-shuffle-crud-navigation]: User isolation enforced via scoped relationship query (auth()->user()->shuffles()->findOrFail()), consistent with watchlist pattern
- [Phase 10-shuffle-crud-navigation]: Modal panel given relative z-10 and @click.stop to fix buttons being unclickable behind fixed backdrop overlay
- [Phase 11-step-editor-yield-config-and-auto-watch]: ShuffleStep uses deleted (post-delete) event for orphan cleanup so exists() check runs after step is removed from DB
- [Phase 11-step-editor-yield-config-and-auto-watch]: TDD RED: 16 step editor tests intentionally fail until Plan 02 implements addStep/saveStep/moveStep/deleteStep Livewire methods
- [Phase 11-step-editor-yield-config-and-auto-watch]: Auth split: EnsureShuffleOwner middleware handles HTTP-level 403 (Livewire mount must succeed for valid snapshot); addStep enforces 403 at action level for Livewire assertForbidden()
- [Phase 11-step-editor-yield-config-and-auto-watch]: EnsureShuffleOwner middleware manually resolves Shuffle from string ID when SubstituteBindings hasn't run yet due to Volt route middleware priority ordering
- [Phase 12-batch-calculator-and-profit-summary]: profitPerUnit() cascade: floor(qty * output_qty_min / input_qty) per step, starting from 1 unit input
- [Phase 12-batch-calculator-and-profit-summary]: Carbon staleness: use polled_at->diffInMinutes(now()) not now()->diffInMinutes(past) to get positive elapsed minutes
- [Phase 12-batch-calculator-and-profit-summary]: priceData() avoids N+1 via single CatalogItem query + single PriceSnapshot query with application-side groupBy
- [Phase 12-batch-calculator-and-profit-summary]: Alpine component defined inline in Blade (not external JS file) for consistency with step editor pattern; wire:ignore prevents Livewire morph from resetting batchQty state

### Pending Todos

None.

### Blockers/Concerns

None — Phase 9 data foundation complete.

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

## Session Continuity

Last session: 2026-03-05T18:47:15Z
Stopped at: Completed quick-12 (Add realm auction support to blizzard sync-catalog)
Resume file: None
