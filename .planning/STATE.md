# Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-03-01)

**Core value:** See at a glance when crafting material prices dip or spike so users can act on buy/sell opportunities before the market corrects.
**Current focus:** Phase 1 - Project Foundation

## Current Position

Phase: 1 of 8 (Project Foundation)
Plan: 2 of 3 in current phase
Status: In progress
Last activity: 2026-03-01 — Completed 01-02 (database schema: watched_items + price_snapshots migrations, Eloquent models, factories, seeder)

Progress: [██░░░░░░░░] 8%

## Performance Metrics

**Velocity:**
- Total plans completed: 2
- Average duration: 2.5 min
- Total execution time: 0.08 hours

**By Phase:**

| Phase | Plans | Total | Avg/Plan |
|-------|-------|-------|----------|
| 01-project-foundation | 2 | 5 min | 2.5 min |

**Recent Trend:**
- Last 5 plans: 01-01 (3 min), 01-02 (2 min)
- Trend: On pace

*Updated after each plan completion*

## Accumulated Context

### Decisions

Decisions are logged in PROJECT.md Key Decisions table.
Recent decisions affecting current work:

- Foundation: Schema columns must be BIGINT UNSIGNED for copper prices — float columns are irrecoverable after data is written
- Foundation: Composite index on (watched_item_id, polled_at) mandatory from day one — cannot be added efficiently later
- API: Blizzard token must use Authorization: Bearer header — query string was removed Sept 2024
- Ingestion: Last-Modified deduplication gate must be part of initial job design — duplicate rows cannot be removed retroactively
- 01-01: PHP ^8.4 enforced in composer.json (not Laravel default ^8.2) — locked project decision
- 01-01: Pest 3.x installed explicitly (not in default Laravel 12 scaffold); PHPUnit upgraded to support Pest v3
- 01-01: No Livewire starter kit — bare install to avoid Flux/Volt/auth scaffolding (Phase 2 scope)
- 01-01: Pint declare_strict_types applied to all scaffold files immediately from day one
- 01-02: All price columns are BIGINT UNSIGNED (copper denomination) — confirmed in schema, irrecoverable decision locked in
- 01-02: Composite index on (watched_item_id, polled_at) embedded in create migration — cannot add efficiently after data accumulates
- 01-02: nullable user_id FK on watched_items from day one — Phase 2 auth needs no ALTER TABLE migration
- 01-02: Hard delete only on WatchedItem and PriceSnapshot — no SoftDeletes, keeps queries simple

### Pending Todos

None yet.

### Blockers/Concerns

- Phase 4: Verify `Last-Modified` header behavior on live Blizzard commodities endpoint before finalizing deduplication implementation. Fallback: response-hash deduplication if header is absent/unreliable.
- Phase 7: Verify `asantibanez/livewire-charts` Livewire 4 compatibility before planning. Fallback: direct ApexCharts via `@script` block with `$wire.on()`.

## Session Continuity

Last session: 2026-03-01
Stopped at: Completed 01-02-PLAN.md — database schema ready, proceed to 01-03
Resume file: None
