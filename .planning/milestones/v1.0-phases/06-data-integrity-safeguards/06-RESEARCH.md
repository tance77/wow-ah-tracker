# Phase 6: Data Integrity Safeguards - Research

**Researched:** 2026-03-01
**Domain:** Laravel job deduplication, HTTP response headers, DB-persisted metadata, hashing
**Confidence:** HIGH

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions

**Staleness tracking**
- Time-based threshold: data is stale if last successful fetch is older than 30 minutes (2 missed polls)
- Store `last_fetched_at` timestamp in a global ingestion metadata table (single row, not per-item)
- Dashboard computes staleness on render from the timestamp — no precomputed boolean flag

**Dedup persistence**
- `PriceFetchAction` returns the `Last-Modified` header alongside the listings (single HTTP call, DTO or array return)
- Primary gate: compare incoming `Last-Modified` against stored value; skip writes if unchanged
- Fallback gate: full response body hash (MD5 or SHA256) when `Last-Modified` header is absent
- Both `last_modified_at` and `response_hash` stored in the same global ingestion metadata table
- When dedup gate blocks a write, log at info level ("data unchanged, skipping write")

**Failure behavior**
- On API failure: catch the exception in the job, log the error, skip the cycle (no snapshots written)
- Track `consecutive_failures` count in the metadata table for potential dashboard/alerting use
- No Laravel job retries — the 15-minute scheduler provides natural retry; avoids hammering a down API
- Staleness indicator (30-min threshold) covers dashboard surfacing — no separate error banner needed
- Reset `consecutive_failures` to 0 on successful fetch

**Edge cases**
- Write zero-value snapshots for watched items with no AH listings (preserves time series continuity) — already implemented in Phase 5
- Dedup gate applies globally (entire API response), not per-item — if Last-Modified/hash unchanged, skip all writes
- No --force bypass flag; tests mock the dedup gate directly
- No special handling for newly added watched items — they get data on the next regular poll with fresh API data

### Claude's Discretion
- Hash algorithm choice (MD5 vs SHA256) for response body fallback
- Exact metadata table schema and migration naming
- Log message formatting and context fields
- Whether to use a dedicated model or just raw DB queries for the metadata row

### Deferred Ideas (OUT OF SCOPE)
None — discussion stayed within phase scope

</user_constraints>

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| DATA-04 | Duplicate snapshots skipped when API data hasn't changed (Last-Modified check) | Primary gate via `Last-Modified` header comparison against DB-stored value; fallback response-hash gate; both persisted in a global ingestion metadata table that survives restart |

</phase_requirements>

## Summary

Phase 6 adds a deduplication guard to the existing `FetchCommodityPricesJob` pipeline. The guard reads a global ingestion metadata row from the database (not cache — survives restarts), compares the Blizzard API response's `Last-Modified` header to the stored value, and short-circuits the write loop when the data has not changed. A secondary fallback hashes the full response body for the rare cases where the header is absent or unreliable.

The metadata table also records `last_fetched_at` (updated on every successful fetch) and `consecutive_failures` (incremented on API error, reset on success). `PriceFetchAction` needs a return-type change: it currently returns `array` (filtered listings); it must be changed to return both the listings and the `Last-Modified` header value so the job can apply the gate without making a second HTTP call. Everything else in the pipeline (aggregate, write loop, scheduler) is untouched.

The work splits cleanly into two plans matching the phase outline: (1) migration + metadata table infrastructure + `Last-Modified` gate in the job, and (2) response-hash fallback gate + tests for both strategies.

**Primary recommendation:** Use a single `IngestionMetadata` Eloquent model (or raw `DB::table()` calls) with `updateOrCreate` on a fixed `id=1` row to keep the global singleton pattern simple and testable.

## Standard Stack

### Core

| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| Laravel Framework | ^12.0 (12.53.0 installed) | HTTP client, Eloquent, migrations, jobs | Already in project |
| PHP | ^8.4 (8.4.18 installed) | `hash()` built-in for MD5/SHA256 | Already in project |
| Pest | ^3.8 | Test framework | Already in project |

### Supporting

| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| `Illuminate\Support\Facades\DB` | Laravel 12 | Raw DB queries for single-row metadata table | If a dedicated Eloquent model feels heavy for a one-row config table |
| `Illuminate\Support\Facades\Log` | Laravel 12 | Structured logging for skip events | Already used in job and action |

### Alternatives Considered

| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| DB-persisted metadata table | `Cache::put()` | Cache does NOT survive restarts with file/array driver; requirements explicitly need persistence |
| Eloquent model for metadata | Raw `DB::table()` | Model is cleaner for testing (can use factories/assertions); `DB::table()` avoids model boilerplate for 1-row config table — either works |
| SHA256 for hash fallback | MD5 | SHA256 is slower but collision-resistant; for dedup (not security) MD5 is sufficient and faster; Claude's discretion — recommend MD5 for simplicity |

**Installation:** No new packages required — all capabilities are in Laravel 12 core.

## Architecture Patterns

### Recommended Project Structure

```
app/
├── Actions/
│   └── PriceFetchAction.php         # return type change: array → array{listings: array, lastModified: ?string}
├── Jobs/
│   └── FetchCommodityPricesJob.php  # dedup gate injected between fetch and write loop
├── Models/
│   └── IngestionMetadata.php        # (new) single-row global metadata model
database/migrations/
│   └── YYYY_MM_DD_create_ingestion_metadata_table.php  # (new)
tests/Feature/DataIngestion/
│   └── FetchCommodityPricesJobTest.php  # extend with dedup scenarios
│   └── DataIntegrityTest.php            # (new) hash fallback + gate tests
```

### Pattern 1: Single-Row Global Metadata Table

**What:** One row in `ingestion_metadata` (id=1) stores `last_modified_at`, `response_hash`, `last_fetched_at`, `consecutive_failures`. Updated atomically on each successful fetch cycle.

**When to use:** Global state that must survive app restart. Cache is not appropriate because file/array cache is cleared on deploy or restart.

**Example:**
```php
// Migration pattern — single row, no FK constraints
Schema::create('ingestion_metadata', function (Blueprint $table) {
    $table->id();
    $table->string('last_modified_at')->nullable();   // raw header string, e.g. "Fri, 28 Feb 2026 18:00:00 GMT"
    $table->string('response_hash')->nullable();      // MD5/SHA256 hex of raw response body
    $table->timestamp('last_fetched_at')->nullable(); // updated on every successful fetch
    $table->unsignedInteger('consecutive_failures')->default(0);
    $table->timestamps();
});

// Seeder / first-run bootstrap — ensure row exists
IngestionMetadata::firstOrCreate(['id' => 1]);
```

**Accessing the row:**
```php
$meta = IngestionMetadata::firstOrCreate(['id' => 1]);
```

### Pattern 2: PriceFetchAction Return Type Change

**What:** `PriceFetchAction::__invoke()` currently returns `array` (filtered listings). It must return both listings and the `Last-Modified` header so the job can apply the gate without a second HTTP call.

**Current signature:**
```php
public function __invoke(array $itemIds): array
```

**New signature (two options — either works):**

Option A — named array shape (simple, no new class):
```php
/** @return array{listings: array<int, array<string, mixed>>, lastModified: ?string} */
public function __invoke(array $itemIds): array
```

Option B — dedicated DTO (cleaner type safety):
```php
// app/Data/FetchResult.php
final class FetchResult {
    public function __construct(
        public readonly array $listings,
        public readonly ?string $lastModified,
        public readonly string $rawBody,  // needed for hash fallback
    ) {}
}
```

**Recommendation:** Option A (named array) keeps the project consistent with its existing Action return conventions and avoids a new class for a single consumer. However Option B is cleaner. Given that `rawBody` is also needed for the hash fallback, a DTO or a 3-key array avoids passing three separate values through the job call.

**Accessing Last-Modified header in Laravel HTTP client:**
```php
// Source: Laravel HTTP client docs — Response::header() returns string
$lastModified = $response->header('Last-Modified'); // '' (empty string) when absent, not null
// Normalize to null for storage:
$lastModified = $response->header('Last-Modified') ?: null;
```

**Accessing raw response body for hashing:**
```php
$rawBody = $response->body(); // returns string — full response body
$hash = md5($rawBody);
// OR
$hash = hash('sha256', $rawBody);
```

### Pattern 3: Dedup Gate in FetchCommodityPricesJob

**What:** After fetch, before write loop — compare incoming values to stored metadata. Short-circuit if unchanged.

**Example:**
```php
public function handle(
    PriceFetchAction $fetchAction,
    PriceAggregateAction $aggregateAction,
): void {
    $watchedItems = WatchedItem::all();
    if ($watchedItems->isEmpty()) {
        Log::info('FetchCommodityPricesJob: no watched items, skipping.');
        return;
    }

    $itemIds = $watchedItems->pluck('blizzard_item_id')->unique()->values()->all();

    try {
        $result = ($fetchAction)($itemIds);
    } catch (\RuntimeException $e) {
        Log::error('FetchCommodityPricesJob: fetch failed, skipping cycle', [
            'error' => $e->getMessage(),
        ]);
        IngestionMetadata::incrementFailures();
        return;
    }

    $meta = IngestionMetadata::firstOrCreate(['id' => 1]);

    // Primary gate: Last-Modified header
    if ($result['lastModified'] !== null && $result['lastModified'] === $meta->last_modified_at) {
        Log::info('FetchCommodityPricesJob: data unchanged (Last-Modified match), skipping write');
        return;
    }

    // Fallback gate: response body hash (when Last-Modified absent)
    if ($result['lastModified'] === null && $result['hash'] === $meta->response_hash) {
        Log::info('FetchCommodityPricesJob: data unchanged (hash match), skipping write');
        return;
    }

    // Write snapshots...
    $polledAt = now();
    foreach ($watchedItems as $watchedItem) { /* ... */ }

    // Update metadata AFTER successful writes
    $meta->update([
        'last_modified_at'    => $result['lastModified'],
        'response_hash'       => $result['hash'],
        'last_fetched_at'     => now(),
        'consecutive_failures' => 0,
    ]);
}
```

### Anti-Patterns to Avoid

- **Cache for dedup persistence:** `Cache::put()` and `Cache::remember()` do not survive file/array cache clears or queue worker restarts. Use DB.
- **Per-item dedup tracking:** The gate is global — the entire response is either fresh or stale. Per-item tracking is over-engineered and unnecessary.
- **Retry logic on failed fetch:** The job explicitly has no retries (`$tries` not set, no `retry()` in job). The scheduler provides natural retry at 15-minute intervals. Adding job retries would hammer a down API.
- **Null vs empty string on header:** Laravel's `$response->header()` returns empty string `""` when header is absent, not `null`. Always normalize: `$response->header('Last-Modified') ?: null`.
- **Comparing header strings case-insensitively:** `Last-Modified` values are date strings. A byte-identical comparison (`===`) is correct; parsing to DateTime introduces unnecessary complexity and timezone edge cases.
- **Hashing after filtering:** Hash the **full raw response body** before item filtering. Hashing only filtered listings would miss items being added/removed from the API response.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Response hashing | Custom serialization | PHP built-in `md5()` / `hash('sha256', ...)` | MD5 is collision-resistant enough for dedup (not security); built-in, no dependencies |
| Single-row metadata store | Singleton pattern with in-memory state | Eloquent model with `firstOrCreate(['id' => 1])` | Survives restarts, testable, queryable by Phase 7 dashboard |
| HTTP header access | Manual `$response->getHeaders()` parsing | `$response->header('Last-Modified')` | Laravel HTTP client Response wraps PSR-7; `header()` is the correct accessor |

**Key insight:** The PHP standard library handles everything needed for hashing. Laravel's HTTP client already exposes headers via `->header()`. No new packages required.

## Common Pitfalls

### Pitfall 1: Empty String vs Null for Last-Modified

**What goes wrong:** `$response->header('Last-Modified')` returns `""` when the header is absent, not `null`. Comparing `"" === $meta->last_modified_at` (which is `null` on first run) evaluates to `false`, which is correct but for the wrong reason — the gate would always pass on first run. More critically, storing `""` as the header value would cause subsequent compares to match on `""` even when the real response has no header, falsely blocking writes.

**Why it happens:** Laravel HTTP client wraps PSR-7 which returns empty string for missing headers.

**How to avoid:** Always normalize: `$lastModified = $response->header('Last-Modified') ?: null;`

**Warning signs:** DB row shows `last_modified_at = ""` (empty string) instead of `null`.

### Pitfall 2: Race Between Metadata Read and Write

**What goes wrong:** The metadata row is read at the start of the gate check, then writes happen, then metadata is updated. If a concurrent job runs (which ShouldBeUnique prevents but tests can bypass), the second job reads stale metadata.

**Why it happens:** Non-atomic read-then-update.

**How to avoid:** The 14-minute `$uniqueFor` lock on `ShouldBeUnique` already prevents concurrent runs. Update metadata **after** all snapshots are written, not before.

**Warning signs:** `consecutive_failures` resets unexpectedly or `last_modified_at` lags behind actual fetch.

### Pitfall 3: Hashing Filtered vs Full Response

**What goes wrong:** Hashing only the filtered listings (after `array_filter()` by watched item IDs) means a change in non-watched items does not update the hash. This is actually correct behavior — but only if the intent is "changed data for watched items." However, the requirement says the gate is global: "entire API response." If hash is computed post-filter, a new item appearing in the API but not yet watched would not update the hash, potentially missing an edge case.

**Why it happens:** Convenience — the filtered array is already computed.

**How to avoid:** Hash `$response->body()` (the raw JSON string) before any filtering. Store the hash of the raw payload.

**Warning signs:** Hash changes on first run after adding a new watched item even when prices haven't changed.

### Pitfall 4: Test Isolation with Http::fake() and metadata state

**What goes wrong:** Tests that seed the metadata table and then call `handle()` may leave stale state if `RefreshDatabase` is not applied, or if `Http::fake()` stubs accumulate across tests.

**Why it happens:** `Http::fake()` in Pest merges stubs across calls in the same test suite run unless isolated per-test.

**How to avoid:** Use the existing `fakeBlizzardHttp()` per-test helper pattern (already established in Phase 5). For metadata state, use `IngestionMetadata::truncate()` or rely on `RefreshDatabase` trait (already in `Pest.php` for Feature tests).

### Pitfall 5: Metadata Row Missing on First Run

**What goes wrong:** If the metadata table is empty on the very first job run, `IngestionMetadata::first()` returns `null`, causing a null pointer error when accessing `->last_modified_at`.

**Why it happens:** Fresh install with no seed data.

**How to avoid:** Always use `firstOrCreate(['id' => 1])` not `first()`. This guarantees a row exists before the gate check.

## Code Examples

Verified patterns from official sources and existing codebase:

### Accessing HTTP Response Header in Laravel 12

```php
// Source: Laravel 12 HTTP client docs — Illuminate\Http\Client\Response
$response = Http::withToken($token)->get($url, $params);

// Header access — returns '' (empty string) when absent, never null
$lastModified = $response->header('Last-Modified') ?: null;

// Raw body for hashing
$rawBody = $response->body();
$hash = md5($rawBody); // or hash('sha256', $rawBody)
```

### Single-Row Global Metadata with Eloquent

```php
// app/Models/IngestionMetadata.php
class IngestionMetadata extends Model
{
    protected $fillable = [
        'last_modified_at',
        'response_hash',
        'last_fetched_at',
        'consecutive_failures',
    ];

    protected $casts = [
        'last_fetched_at' => 'datetime',
        'consecutive_failures' => 'integer',
    ];

    // Get-or-create singleton row
    public static function singleton(): self
    {
        return self::firstOrCreate(['id' => 1]);
    }
}
```

### Exception Catch Pattern in Job (aligned with existing code style)

```php
// In FetchCommodityPricesJob::handle()
// Existing: $listings = ($fetchAction)($itemIds);  — throws RuntimeException on failure
// New: wrap in try/catch, catch RuntimeException per CONTEXT.md decision

try {
    $result = ($fetchAction)($itemIds);
} catch (\RuntimeException $e) {
    Log::error('FetchCommodityPricesJob: fetch failed, skipping cycle', [
        'error' => $e->getMessage(),
    ]);
    $meta = IngestionMetadata::singleton();
    $meta->increment('consecutive_failures');
    return;
}
```

### Pest Test Pattern for Dedup Gate (consistent with Phase 5 patterns)

```php
// In tests/Feature/DataIngestion/FetchCommodityPricesJobTest.php (extended)
it('skips snapshot write when Last-Modified header is unchanged', function (): void {
    fakeBlizzardHttp(); // existing helper

    // Seed metadata with same Last-Modified value as fixture returns
    IngestionMetadata::create([
        'id' => 1,
        'last_modified_at' => 'Fri, 28 Feb 2026 18:00:00 GMT', // matches fixture header
    ]);

    $user = User::factory()->create();
    WatchedItem::factory()->create(['user_id' => $user->id, 'blizzard_item_id' => 224025]);

    (new FetchCommodityPricesJob)->handle(app(PriceFetchAction::class), app(PriceAggregateAction::class));

    expect(PriceSnapshot::count())->toBe(0); // gate blocked write
});
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| `$response->getHeader()` (PSR-7 direct) | `$response->header()` (Laravel wrapper) | Laravel 7+ | Returns string not array; normalize with `?: null` |
| `Http::fake()` in `beforeEach` | Per-test `Http::fake()` via helper | Phase 5 established | Prevents stub accumulation in Pest test suite |
| Cache for persistent job state | DB table for metadata that must survive restart | Project decision | Cache cleared on deploy; DB is durable |

**Deprecated/outdated:**
- None relevant — all patterns in this phase use stable Laravel 12 APIs.

## Open Questions

1. **Does the Blizzard commodities endpoint reliably return `Last-Modified`?**
   - What we know: STATE.md flags this as a known concern: "Verify `Last-Modified` header behavior on live Blizzard commodities endpoint before finalizing deduplication implementation."
   - What's unclear: Whether the header is always present, sometimes present, or never present on the commodities endpoint.
   - Recommendation: The fallback hash gate covers the absent-header case. In tests, both paths (header present, header absent) must be exercised. The Http::fake() stub in `fakeBlizzardHttp()` does not currently include a `Last-Modified` response header — the stub must be updated to include it for header-gate tests. A separate stub variant (no `Last-Modified` header) is needed for hash-gate tests.

2. **Should `PriceFetchAction` return a DTO or a named array?**
   - What we know: Current return is `array`. Both DTO and named array work. The action has one caller (the job).
   - What's unclear: Whether project conventions prefer typed DTOs.
   - Recommendation: Use a named array with PHPDoc `@return array{listings: ..., lastModified: ?string, rawBody: string}` to stay consistent with existing action return conventions. A DTO (final readonly class) is also acceptable — Claude's discretion.

3. **Should `IngestionMetadata` use a dedicated model or raw `DB::table()` calls?**
   - What we know: BlizzardTokenService uses `Cache::remember()` (not a model). PriceSnapshot uses a full Eloquent model.
   - What's unclear: Project convention for single-row config tables.
   - Recommendation: Use an Eloquent model (`IngestionMetadata`). It enables `firstOrCreate()`, `->increment()`, and `->update()` cleanly. It is also easier to assert against in Pest tests (`expect(IngestionMetadata::first()->last_fetched_at)->not->toBeNull()`).

## Sources

### Primary (HIGH confidence)

- Laravel 12 source / installed package (12.53.0) — `Illuminate\Http\Client\Response::header()` returns string
- Existing codebase (`PriceFetchAction.php`, `FetchCommodityPricesJob.php`, `BlizzardTokenService.php`) — verified integration points, existing patterns
- PHP 8.4 built-in `md5()` and `hash()` — no version risk, standard library

### Secondary (MEDIUM confidence)

- CONTEXT.md `## Code Insights` section — user-confirmed that `$response->header('Last-Modified')` is the correct accessor
- STATE.md `## Blockers/Concerns` — confirms Last-Modified header reliability is a known open question

### Tertiary (LOW confidence)

- None — all claims verified against installed codebase or official Laravel 12 docs.

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — all libraries already installed, versions confirmed
- Architecture: HIGH — patterns derived from existing codebase conventions (BlizzardTokenService, FetchCommodityPricesJob, Phase 5 test patterns)
- Pitfalls: HIGH — derived from actual code (empty string vs null for header), existing test helper patterns

**Research date:** 2026-03-01
**Valid until:** 2026-04-01 (Laravel 12 stable, no fast-moving dependencies)
