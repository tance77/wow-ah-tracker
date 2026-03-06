---
phase: quick-32
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - app/Jobs/DispatchRealmPriceBatchesJob.php
  - app/Jobs/AggregateRealmPriceBatchJob.php
autonomous: true
requirements: [quick-32]
must_haves:
  truths:
    - "Realm auction file is read exactly once per polling cycle, not once per batch"
    - "Batch jobs receive pre-extracted listing data and perform no file I/O"
    - "Price snapshots are still written correctly for BoE items"
  artifacts:
    - path: "app/Jobs/DispatchRealmPriceBatchesJob.php"
      provides: "Single-pass extraction then batch dispatch with pre-extracted data"
    - path: "app/Jobs/AggregateRealmPriceBatchJob.php"
      provides: "Aggregation from in-memory listings, no file streaming"
  key_links:
    - from: "app/Jobs/DispatchRealmPriceBatchesJob.php"
      to: "app/Actions/ExtractRealmListingsAction.php"
      via: "Single extraction call for all catalog items"
      pattern: "ExtractRealmListingsAction.*__invoke"
    - from: "app/Jobs/AggregateRealmPriceBatchJob.php"
      to: "app/Actions/PriceAggregateAction.php"
      via: "Aggregation from pre-extracted listings"
      pattern: "PriceAggregateAction"
---

<objective>
Fix DispatchRealmPriceBatchesJob timeout by eliminating redundant file reads.

Purpose: Each AggregateRealmPriceBatchJob currently re-streams the entire realm auction JSON file (50-100MB+) to extract listings for just 50 items. With N batches, the file is read N times, causing timeouts on Laravel Cloud's default job timeout. The fix extracts all listings in a single pass in the dispatcher job, then passes pre-extracted data to batch jobs.

Output: Realm price polling completes reliably within timeout limits.
</objective>

<execution_context>
@/Users/lancethompson/.claude/get-shit-done/workflows/execute-plan.md
@/Users/lancethompson/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@app/Jobs/DispatchRealmPriceBatchesJob.php
@app/Jobs/AggregateRealmPriceBatchJob.php
@app/Actions/ExtractRealmListingsAction.php
@app/Actions/PriceAggregateAction.php
@app/Models/CatalogItem.php
</context>

<interfaces>
<!-- Key contracts the executor needs -->

From app/Actions/ExtractRealmListingsAction.php:
```php
// __invoke(string $storageKey, array $itemIds): array<int, array<array{unit_price: int, quantity: int}>>
// Streams file once, returns listings grouped by blizzard_item_id
```

From app/Actions/PriceAggregateAction.php:
```php
// __invoke(array $listings): array  — returns metrics array for PriceSnapshot::insert
```

From app/Jobs/AggregateRealmPriceBatchJob.php (current constructor):
```php
public function __construct(
    public readonly string $storageKey,        // Will be REMOVED
    public readonly array $itemMap,            // [catalog_item_id => blizzard_item_id]
    public readonly CarbonInterface $polledAt,
)
```
</interfaces>

<tasks>

<task type="auto">
  <name>Task 1: Single-pass extraction in dispatcher, data-only batch jobs</name>
  <files>app/Jobs/DispatchRealmPriceBatchesJob.php, app/Jobs/AggregateRealmPriceBatchJob.php</files>
  <action>
**DispatchRealmPriceBatchesJob.php** — Refactor handle() to:

1. Query only non-commodity catalog items if possible, but since CatalogItem has no is_commodity flag, keep CatalogItem::all() (matching commodity pipeline pattern).
2. Build the full blizzard_item_id list from all catalog items.
3. Call ExtractRealmListingsAction ONCE with the storageKey and ALL blizzard_item_ids to get all listings in a single file pass.
4. Delete the storage file immediately after extraction (move Storage::delete to right after extraction, before dispatching batches).
5. Chunk the itemMap into batches of 50 (same as now).
6. For each chunk, filter the pre-extracted listings to only include that chunk's blizzard_item_ids, and pass the filtered listings array to AggregateRealmPriceBatchJob instead of the storageKey.
7. Remove storageKey from the Bus::batch then/catch closures since file is already deleted.
8. Add `use App\Actions\ExtractRealmListingsAction;` import.
9. Inject ExtractRealmListingsAction via handle() method injection: `public function handle(ExtractRealmListingsAction $extractAction): void`

**AggregateRealmPriceBatchJob.php** — Refactor to receive pre-extracted data:

1. Change constructor: remove `$storageKey`, replace `$itemMap` with two parameters:
   - `public readonly array $itemMap` (keep — [catalog_item_id => blizzard_item_id])
   - `public readonly array $preExtractedListings` (NEW — [blizzard_item_id => listings array])
   - `public readonly CarbonInterface $polledAt` (keep)
2. Remove ExtractRealmListingsAction from handle() injection — no longer needed.
3. In handle(), instead of calling extractAction, just use $this->preExtractedListings directly:
   ```php
   foreach ($this->itemMap as $catalogItemId => $blizzardItemId) {
       $listings = $this->preExtractedListings[$blizzardItemId] ?? [];
       if (empty($listings)) { continue; }
       $metrics = ($aggregateAction)($listings);
       // ... same row building as before
   }
   ```
4. Remove `use App\Actions\ExtractRealmListingsAction;` import.
5. Keep Batchable trait and batch cancellation check.

This eliminates all file I/O from batch jobs. The dispatcher reads the file once and distributes pre-extracted data.
  </action>
  <verify>
    <automated>cd /Users/lancethompson/Github/wow-ah-tracker && php artisan test --filter=ExtractRealmListings 2>&1; echo "---"; php -l app/Jobs/DispatchRealmPriceBatchesJob.php && php -l app/Jobs/AggregateRealmPriceBatchJob.php && echo "Syntax OK"</automated>
  </verify>
  <done>
    - DispatchRealmPriceBatchesJob reads auction file exactly once via ExtractRealmListingsAction
    - Storage file is deleted immediately after extraction, before batch dispatch
    - AggregateRealmPriceBatchJob receives pre-extracted listings array, does zero file I/O
    - No functional change to price snapshot output
    - Existing tests pass, syntax validates
  </done>
</task>

</tasks>

<verification>
- `php -l app/Jobs/DispatchRealmPriceBatchesJob.php` — no syntax errors
- `php -l app/Jobs/AggregateRealmPriceBatchJob.php` — no syntax errors
- `php artisan test` — all existing tests pass
- AggregateRealmPriceBatchJob no longer references storageKey or ExtractRealmListingsAction
- DispatchRealmPriceBatchesJob calls ExtractRealmListingsAction once in handle()
</verification>

<success_criteria>
Realm auction file is streamed exactly once per polling cycle. Batch jobs receive in-memory data and complete without file I/O, eliminating the timeout caused by N redundant file reads.
</success_criteria>

<output>
After completion, create `.planning/quick/32-fix-dispatchrealmpricebatchesjob-timeout/32-SUMMARY.md`
</output>
