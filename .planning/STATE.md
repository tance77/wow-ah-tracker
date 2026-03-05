---
gsd_state_version: 1.0
milestone: v1.1
milestone_name: Shuffles
status: active
last_updated: "2026-03-04"
progress:
  total_phases: 4
  completed_phases: 0
  total_plans: 0
  completed_plans: 0
---

# Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-03-04)

**Core value:** See at a glance when crafting material prices dip or spike so users can act on buy/sell opportunities before the market corrects.
**Current focus:** Phase 9 — Data Foundation

## Current Position

Phase: 9 of 12 (Data Foundation)
Plan: — of — in current phase
Status: Ready to plan
Last activity: 2026-03-04 — Roadmap created for v1.1 Shuffles milestone

Progress: [░░░░░░░░░░] 0%

## Performance Metrics

**Velocity:**
- Total plans completed: 0 (v1.1)
- Average duration: — min
- Total execution time: —

**By Phase:**

| Phase | Plans | Total | Avg/Plan |
|-------|-------|-------|----------|
| - | - | - | - |

*Updated after each plan completion*

## Accumulated Context

### Decisions

Decisions are logged in PROJECT.md Key Decisions table.
All v1.0 decisions archived — see `milestones/v1.0-ROADMAP.md` for full history.

**v1.1 pending decisions (resolve before Phase 9 migration):**
- Auto-watch provenance: nullable `created_by_shuffle_id` FK on `watched_items` vs. separate pivot table. FK approach recommended for single-user app.
- Yield schema: `output_qty_min` / `output_qty_max` integer columns must be in Phase 9 migration even if min/max UI ships later.

### Pending Todos

None.

### Blockers/Concerns

- Phase 9 migration decisions must be made before coding starts (yield column types, auto-watch provenance schema). Both are irrecoverable after data entry begins.

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

## Session Continuity

Last session: 2026-03-04
Stopped at: Roadmap created for v1.1 Shuffles (Phases 9-12). Ready to plan Phase 9.
Resume file: None
