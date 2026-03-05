---
gsd_state_version: 1.0
milestone: v1.2
milestone_name: Crafting Profitability
status: planning
stopped_at: Phase 13 context gathered
last_updated: "2026-03-05T19:07:59.690Z"
last_activity: 2026-03-05 - Roadmap created for v1.2 (phases 13-16, 19 requirements mapped)
progress:
  total_phases: 8
  completed_phases: 4
  total_plans: 8
  completed_plans: 8
  percent: 0
---

# Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-03-05)

**Core value:** See at a glance when crafting material prices dip or spike so I can act on buy/sell opportunities before the market corrects.
**Current focus:** Phase 13 — Recipe Data Model and Seed Command

## Current Position

Phase: 13 of 16 (Recipe Data Model and Seed Command)
Plan: — (not yet planned)
Status: Ready to plan
Last activity: 2026-03-05 - Roadmap created for v1.2 (phases 13-16, 19 requirements mapped)

Progress: [░░░░░░░░░░] 0%

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

## Session Continuity

Last session: 2026-03-05T19:07:59.688Z
Stopped at: Phase 13 context gathered
Resume file: .planning/phases/13-recipe-data-model-and-seed-command/13-CONTEXT.md
