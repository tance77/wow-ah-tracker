---
phase: quick-26
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - app/Actions/PriceFetchAction.php
  - app/Actions/RealmPriceFetchAction.php
  - app/Actions/ExtractListingsAction.php
  - app/Actions/ExtractRealmListingsAction.php
  - app/Jobs/FetchCommodityDataJob.php
  - app/Jobs/FetchRealmAuctionDataJob.php
  - app/Jobs/DispatchPriceBatchesJob.php
  - app/Jobs/DispatchRealmPriceBatchesJob.php
  - app/Jobs/AggregatePriceBatchJob.php
  - app/Jobs/AggregateRealmPriceBatchJob.php
  - tests/Feature/DataIngestion/FetchCommodityPricesJobTest.php
  - tests/Feature/BlizzardApi/PriceFetchActionTest.php
  - tests/Feature/DataIngestion/ExtractRealmListingsActionTest.php
autonomous: true
requirements: [QUICK-26]
must_haves:
  truths:
    - "Price pipeline works on Laravel Cloud where queue workers run on different containers"
    - "Files are accessed via Storage facade (storage keys) not absolute filesystem paths"
    - "Cleanup uses Storage::delete instead of @unlink"
    - "All existing tests pass after migration to storage keys"
  artifacts:
    - path: "app/Actions/PriceFetchAction.php"
      provides: "Returns storageKey instead of tempFilePath"
      contains: "storageKey"
    - path: "app/Actions/ExtractListingsAction.php"
      provides: "Reads via Storage::readStream instead of fopen"
      contains: "Storage::readStream"
    - path: "app/Jobs/DispatchPriceBatchesJob.php"
      provides: "Uses storageKey property, Storage::delete for cleanup"
      contains: "storageKey"
  key_links:
    - from: "app/Actions/PriceFetchAction.php"
      to: "app/Jobs/FetchCommodityDataJob.php"
      via: "storageKey in return array"
      pattern: "storageKey"
    - from: "app/Jobs/DispatchPriceBatchesJob.php"
      to: "app/Jobs/AggregatePriceBatchJob.php"
      via: "storageKey constructor param"
      pattern: "storageKey"
    - from: "app/Jobs/AggregatePriceBatchJob.php"
      to: "app/Actions/ExtractListingsAction.php"
      via: "passes storageKey to extract action"
      pattern: "storageKey"
---

<objective>
Fix fopen errors on Laravel Cloud by replacing absolute file paths with Storage facade keys throughout the price ingestion pipeline.

Purpose: On Laravel Cloud, queue workers run on different containers. The current flow stores files locally and passes absolute paths between jobs — the file only exists on the container that downloaded it. Using Storage-relative keys with the default disk allows configuring shared storage (s3) on Cloud.

Output: All 10 files in the price pipeline use Storage keys instead of absolute paths; all tests pass.
</objective>

<context>
@app/Actions/PriceFetchAction.php
@app/Actions/RealmPriceFetchAction.php
@app/Actions/ExtractListingsAction.php
@app/Actions/ExtractRealmListingsAction.php
@app/Jobs/FetchCommodityDataJob.php
@app/Jobs/FetchRealmAuctionDataJob.php
@app/Jobs/DispatchPriceBatchesJob.php
@app/Jobs/DispatchRealmPriceBatchesJob.php
@app/Jobs/AggregatePriceBatchJob.php
@app/Jobs/AggregateRealmPriceBatchJob.php
</context>

<tasks>

<task type="auto">
  <name>Task 1: Replace absolute paths with Storage keys in fetch actions and all jobs</name>
  <files>
    app/Actions/PriceFetchAction.php,
    app/Actions/RealmPriceFetchAction.php,
    app/Actions/ExtractListingsAction.php,
    app/Actions/ExtractRealmListingsAction.php,
    app/Jobs/FetchCommodityDataJob.php,
    app/Jobs/FetchRealmAuctionDataJob.php,
    app/Jobs/DispatchPriceBatchesJob.php,
    app/Jobs/DispatchRealmPriceBatchesJob.php,
    app/Jobs/AggregatePriceBatchJob.php,
    app/Jobs/AggregateRealmPriceBatchJob.php
  </files>
  <action>
    **PriceFetchAction.php and RealmPriceFetchAction.php:**
    - Change return array key from `tempFilePath` to `storageKey`
    - Instead of `Storage::disk('local')`, use `Storage::disk()` (default disk — allows Cloud users to set FILESYSTEM_DISK=s3)
    - After writing to storage, return the relative storage key (e.g., `temp/commodities_xxx.json`) — NOT the absolute path from `Storage::disk('local')->path()`
    - Keep the temp download + copy pattern (sink to tempnam, then Storage::put, then unlink temp) since we need the file content to compute md5
    - Update `@return` docblock: `array{storageKey: string, lastModified: ?string, responseHash: string}`
    - Update log message to show storage key instead of absolute path

    **FetchCommodityDataJob.php and FetchRealmAuctionDataJob.php:**
    - Add `use Illuminate\Support\Facades\Storage;`
    - Change all references from `$result['tempFilePath']` to `$result['storageKey']`
    - Replace `@unlink($result['tempFilePath'])` with `Storage::delete($result['storageKey'])` in gate-skip paths
    - Pass `$result['storageKey']` to DispatchPriceBatchesJob/DispatchRealmPriceBatchesJob dispatch call

    **DispatchPriceBatchesJob.php and DispatchRealmPriceBatchesJob.php:**
    - Add `use Illuminate\Support\Facades\Storage;`
    - Rename constructor property from `$filePath` to `$storageKey`
    - Replace `@unlink($this->filePath)` with `Storage::delete($this->storageKey)` in empty-catalog early return
    - Replace `@unlink($filePath)` with `Storage::delete($storageKey)` in Bus::batch then() and catch() callbacks (update the local variable name accordingly)
    - Pass `$this->storageKey` to AggregatePriceBatchJob/AggregateRealmPriceBatchJob constructor

    **AggregatePriceBatchJob.php and AggregateRealmPriceBatchJob.php:**
    - Rename constructor property from `$filePath` to `$storageKey`
    - Update `@param` docblock
    - Pass `$this->storageKey` to the extract action invocation

    **ExtractListingsAction.php and ExtractRealmListingsAction.php:**
    - Add `use Illuminate\Support\Facades\Storage;`
    - Change parameter name from `$filePath` to `$storageKey`
    - Replace `fopen($filePath, 'r')` with `Storage::readStream($storageKey)` — this returns a stream resource identical to fopen, so the rest of the streaming logic stays the same
    - Update `@param` docblock
  </action>
  <verify>
    <automated>cd /Users/lancethompson/Github/wow-ah-tracker && php artisan test --filter="PriceFetchAction|FetchCommodityPrices|ExtractRealmListings" 2>&1 | tail -20</automated>
  </verify>
  <done>All 10 production files use Storage keys. No fopen() or @unlink() on absolute paths remains in these files (except the temp download in fetch actions which is local-only and cleaned immediately).</done>
</task>

<task type="auto">
  <name>Task 2: Update tests to use storageKey and Storage facade assertions</name>
  <files>
    tests/Feature/BlizzardApi/PriceFetchActionTest.php,
    tests/Feature/DataIngestion/FetchCommodityPricesJobTest.php,
    tests/Feature/DataIngestion/ExtractRealmListingsActionTest.php
  </files>
  <action>
    **PriceFetchActionTest.php:**
    - Update `toHaveKeys` assertion from `['tempFilePath', 'lastModified', 'responseHash']` to `['storageKey', 'lastModified', 'responseHash']`
    - Replace `file_exists($result['tempFilePath'])` checks with `Storage::exists($result['storageKey'])`
    - Replace `@unlink($result['tempFilePath'])` cleanup with `Storage::delete($result['storageKey'])`
    - Add `use Illuminate\Support\Facades\Storage;` at top
    - In the integration test "persists downloaded data...", pass `$result['storageKey']` to ExtractListingsAction (it now expects a storage key)

    **FetchCommodityPricesJobTest.php:**
    - In `runDispatchAndAggregate()` helper: change `$batchJob->filePath` to `$batchJob->storageKey`
    - In `runDispatchAndAggregate()` cleanup: replace `@unlink($batchJob->filePath)` with `Storage::delete($batchJob->storageKey)`
    - Add `use Illuminate\Support\Facades\Storage;` at top
    - In "dispatches DispatchPriceBatchesJob with correct data" test: change assertion from `file_exists($job->filePath)` to `Storage::exists($job->storageKey)` and `$job->filePath` to `$job->storageKey`
    - In "creates correct number of batch jobs" test: instead of manually creating a temp file and using absolute path, use `Storage::put('temp/test_commodities.json', file_get_contents($fixturePath))` and pass the storage key `'temp/test_commodities.json'` to `DispatchPriceBatchesJob` constructor. Remove the manual mkdir/copy/unlink. The constructor param is now `$storageKey` not `$filePath`.

    **ExtractRealmListingsActionTest.php:**
    - Add `use Illuminate\Support\Facades\Storage;`
    - Update `writeRealmFixture()` helper: instead of writing to tempnam, use `Storage::put('temp/realm_test_'.uniqid().'.json', $json)` and return the storage key
    - Update all `@unlink($filePath)` calls to `Storage::delete($filePath)` (the variable now holds a storage key)
    - The action now expects a storage key, so this change makes tests match the new signature
  </action>
  <verify>
    <automated>cd /Users/lancethompson/Github/wow-ah-tracker && php artisan test --filter="PriceFetchAction|FetchCommodityPrices|ExtractRealmListings|Deduplication" 2>&1 | tail -20</automated>
  </verify>
  <done>All tests pass with Storage facade. No references to tempFilePath or absolute file paths remain in test files for the price pipeline.</done>
</task>

</tasks>

<verification>
Run full test suite to confirm no regressions:
```bash
php artisan test
```

Grep for any remaining fopen/unlink patterns in the modified files:
```bash
grep -n "fopen\|@unlink\|tempFilePath\|->filePath" app/Actions/PriceFetchAction.php app/Actions/RealmPriceFetchAction.php app/Actions/ExtractListingsAction.php app/Actions/ExtractRealmListingsAction.php app/Jobs/FetchCommodityDataJob.php app/Jobs/FetchRealmAuctionDataJob.php app/Jobs/DispatchPriceBatchesJob.php app/Jobs/DispatchRealmPriceBatchesJob.php app/Jobs/AggregatePriceBatchJob.php app/Jobs/AggregateRealmPriceBatchJob.php
```
Only the tempnam download in PriceFetchAction/RealmPriceFetchAction should use fopen/@unlink (the local temp before copying to Storage).
</verification>

<success_criteria>
- All 10 production files pass storage keys through the chain instead of absolute paths
- ExtractListingsAction and ExtractRealmListingsAction use Storage::readStream() instead of fopen()
- All cleanup uses Storage::delete() instead of @unlink()
- Full test suite passes with zero failures
- No remaining references to tempFilePath or absolute paths in job constructors
</success_criteria>

<output>
After completion, create `.planning/quick/26-fix-fopen-error-on-cloud-use-storage-fac/26-SUMMARY.md`
</output>
