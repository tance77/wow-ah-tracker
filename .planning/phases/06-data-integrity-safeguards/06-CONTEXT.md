# Phase 6: Data Integrity Safeguards - Context

**Gathered:** 2026-03-01
**Status:** Ready for planning

<domain>
## Phase Boundary

The ingestion pipeline skips duplicate snapshots when Blizzard has not published new data, and the system tracks data freshness so the dashboard (Phase 7) can detect and display staleness. No dashboard UI is built here — only the data layer and detection logic.

</domain>

<decisions>
## Implementation Decisions

### Staleness tracking
- Time-based threshold: data is stale if last successful fetch is older than 30 minutes (2 missed polls)
- Store `last_fetched_at` timestamp in a global ingestion metadata table (single row, not per-item)
- Dashboard computes staleness on render from the timestamp — no precomputed boolean flag

### Dedup persistence
- `PriceFetchAction` returns the `Last-Modified` header alongside the listings (single HTTP call, DTO or array return)
- Primary gate: compare incoming `Last-Modified` against stored value; skip writes if unchanged
- Fallback gate: full response body hash (MD5 or SHA256) when `Last-Modified` header is absent
- Both `last_modified_at` and `response_hash` stored in the same global ingestion metadata table
- When dedup gate blocks a write, log at info level ("data unchanged, skipping write")

### Failure behavior
- On API failure: catch the exception in the job, log the error, skip the cycle (no snapshots written)
- Track `consecutive_failures` count in the metadata table for potential dashboard/alerting use
- No Laravel job retries — the 15-minute scheduler provides natural retry; avoids hammering a down API
- Staleness indicator (30-min threshold) covers dashboard surfacing — no separate error banner needed
- Reset `consecutive_failures` to 0 on successful fetch

### Edge cases
- Write zero-value snapshots for watched items with no AH listings (preserves time series continuity)
- Dedup gate applies globally (entire API response), not per-item — if Last-Modified/hash unchanged, skip all writes
- No --force bypass flag; tests mock the dedup gate directly
- No special handling for newly added watched items — they get data on the next regular poll with fresh API data

### Claude's Discretion
- Hash algorithm choice (MD5 vs SHA256) for response body fallback
- Exact metadata table schema and migration naming
- Log message formatting and context fields
- Whether to use a dedicated model or just raw DB queries for the metadata row

</decisions>

<specifics>
## Specific Ideas

No specific requirements — open to standard approaches

</specifics>

<code_context>
## Existing Code Insights

### Reusable Assets
- `FetchCommodityPricesJob`: Orchestrator that needs dedup gate injected before the write loop
- `PriceFetchAction`: Needs to capture and return `Last-Modified` header from the HTTP response
- `PriceSnapshot` model: No changes needed — dedup prevents writes before reaching the model
- `BlizzardTokenService`: Token caching pattern could inform metadata storage approach

### Established Patterns
- Actions return data, Jobs orchestrate — dedup gate fits as a check inside the job before writes
- `ShouldBeUnique` with 14-min lock already prevents overlapping job runs
- `Http::withToken()->retry()` pattern in PriceFetchAction — response headers accessible via `$response->header('Last-Modified')`
- Laravel `Cache::remember()` pattern used in BlizzardTokenService — but metadata needs DB persistence (survives restart)

### Integration Points
- `FetchCommodityPricesJob::handle()` — dedup check goes between fetch and write loop
- `PriceFetchAction::__invoke()` — return type changes from `array` to include Last-Modified header
- `routes/console.php` — scheduler already configured, no changes needed
- Phase 7 dashboard will query the metadata table for `last_fetched_at` to compute staleness

</code_context>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 06-data-integrity-safeguards*
*Context gathered: 2026-03-01*
