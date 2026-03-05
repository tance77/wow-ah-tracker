---
phase: quick-15
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - app/Actions/RealmPriceFetchAction.php
  - app/Actions/ExtractRealmListingsAction.php
  - app/Jobs/FetchRealmAuctionDataJob.php
  - app/Jobs/DispatchRealmPriceBatchesJob.php
  - app/Jobs/AggregateRealmPriceBatchJob.php
  - database/migrations/2026_03_05_000001_add_realm_columns_to_ingestion_metadata.php
  - routes/console.php
  - tests/Feature/Actions/ExtractRealmListingsActionTest.php
autonomous: true
requirements: []
must_haves:
  truths:
    - "Realm auction data is fetched hourly from Blizzard connected-realm endpoint"
    - "BoE item prices (buyout field) are extracted and aggregated into PriceSnapshot rows"
    - "Bid-only auctions (buyout=0) are skipped"
    - "Realm ingestion uses separate gate columns to avoid interfering with commodity pipeline"
  artifacts:
    - path: "app/Actions/RealmPriceFetchAction.php"
      provides: "Downloads realm auction JSON to temp file"
    - path: "app/Actions/ExtractRealmListingsAction.php"
      provides: "Stream-parses realm auction JSON extracting item.id and buyout"
    - path: "app/Jobs/FetchRealmAuctionDataJob.php"
      provides: "Hourly job that fetches realm data and dispatches batches"
    - path: "routes/console.php"
      provides: "Schedule entry for realm auction polling"
  key_links:
    - from: "FetchRealmAuctionDataJob"
      to: "RealmPriceFetchAction"
      via: "dependency injection in handle()"
    - from: "FetchRealmAuctionDataJob"
      to: "DispatchRealmPriceBatchesJob"
      via: "dispatch after gate checks"
    - from: "AggregateRealmPriceBatchJob"
      to: "ExtractRealmListingsAction"
      via: "dependency injection in handle()"
    - from: "AggregateRealmPriceBatchJob"
      to: "PriceAggregateAction"
      via: "reuses existing action with unit_price=buyout, quantity=1"
---

<objective>
Add hourly realm auction price polling for BoE items that appear on the connected-realm auction house (not the commodity AH).

Purpose: BoE gear items never appear in the commodities endpoint. Without realm auction polling, these catalog items never get price updates.

Output: A parallel pipeline (RealmPriceFetchAction -> DispatchRealmPriceBatchesJob -> AggregateRealmPriceBatchJob) that mirrors the commodity pipeline but hits the connected-realm auctions endpoint and handles the different JSON structure (nested item objects, `buyout` instead of `unit_price`).
</objective>

<context>
@app/Actions/PriceFetchAction.php
@app/Actions/ExtractListingsAction.php
@app/Jobs/FetchCommodityDataJob.php
@app/Jobs/DispatchPriceBatchesJob.php
@app/Jobs/AggregatePriceBatchJob.php
@app/Actions/PriceAggregateAction.php
@app/Models/IngestionMetadata.php
@routes/console.php

<interfaces>
From app/Actions/PriceAggregateAction.php:
```php
// Accepts: array<int, array{unit_price: int, quantity: int}>
// Returns: array{min_price: int, avg_price: int, median_price: int, total_volume: int}
public function __invoke(array $listings): array
```

From app/Models/IngestionMetadata.php:
```php
// Singleton row with: last_modified_at, response_hash, last_fetched_at, consecutive_failures
public static function singleton(): self
```

From app/Models/CatalogItem.php:
```php
protected $fillable = ['blizzard_item_id', 'name', 'category', 'rarity', 'icon_url', 'quality_tier'];
```
</interfaces>
</context>

<tasks>

<task type="auto">
  <name>Task 1: Migration, RealmPriceFetchAction, and ExtractRealmListingsAction</name>
  <files>
    database/migrations/2026_03_05_000001_add_realm_columns_to_ingestion_metadata.php,
    app/Models/IngestionMetadata.php,
    app/Actions/RealmPriceFetchAction.php,
    app/Actions/ExtractRealmListingsAction.php,
    tests/Feature/Actions/ExtractRealmListingsActionTest.php
  </files>
  <action>
1. Create migration adding realm-specific gate columns to `ingestion_metadata`:
   - `realm_last_modified_at` (string, nullable) — mirrors `last_modified_at`
   - `realm_response_hash` (string, nullable) — mirrors `response_hash`
   - `realm_last_fetched_at` (timestamp, nullable) — mirrors `last_fetched_at`
   - `realm_consecutive_failures` (unsignedInteger, default 0)

2. Update `IngestionMetadata` model — add the four new columns to `$fillable` and add `realm_last_fetched_at` to `$casts` as datetime.

3. Create `RealmPriceFetchAction` — mirror `PriceFetchAction` exactly but:
   - Endpoint: `https://{region}.api.blizzard.com/data/wow/connected-realm/{connectedRealmId}/auctions`
   - Read `connectedRealmId` from `config('services.blizzard.connected_realm_id')`
   - Query params: `namespace=dynamic-{region}`
   - Temp file prefix: `wow_realm_prices_`
   - Storage path prefix: `temp/realm_auctions_`
   - Log messages: prefix with `RealmPriceFetchAction:`
   - Same streaming sink pattern, same retry/timeout, same hash computation
   - Return same shape: `array{tempFilePath: string, lastModified: ?string, responseHash: string}`

4. Create `ExtractRealmListingsAction` — stream-parses realm auction JSON.

   Realm auction objects look like:
   ```json
   {"id":123,"item":{"id":12345,"context":1,"bonus_list":[9379],"modifiers":[{"type":28,"value":2207}]},"buyout":50000000,"quantity":1,"time_left":"LONG"}
   ```

   The key difference from `ExtractListingsAction`: the item object has nested `{}` (bonus_list array, modifiers array with objects), so the commodity regex `/{[^{}]*{"id":(\d+)}[^{}]*}/` won't match.

   Implementation approach — use a brace-depth counter instead of regex:
   - Read file in 128KB chunks (same as commodity extractor)
   - Maintain a buffer. Scan for top-level auction objects by tracking brace depth (increment on `{`, decrement on `}`, object complete when depth returns to 0)
   - When a complete top-level object is captured, use `json_decode()` to parse it
   - Extract `$obj['item']['id']` and `$obj['buyout']`
   - Skip if `buyout` is 0 or missing (bid-only auctions)
   - If `item.id` is in the catalog set, add to grouped output as `['unit_price' => $buyout, 'quantity' => 1]`
   - This shape is compatible with `PriceAggregateAction`

   Method signature: `__invoke(string $filePath, array $itemIds): array` — same return type as `ExtractListingsAction`: `array<int, array<array{unit_price: int, quantity: int}>>`

5. Create `tests/Feature/Actions/ExtractRealmListingsActionTest.php` (Pest):
   - Test: extracts buyout prices for matching item IDs from realm auction JSON
   - Test: skips bid-only auctions (buyout = 0)
   - Test: skips items not in the catalog set
   - Test: handles items with bonus_list and modifiers in item object
   - Write a small JSON fixture inline (5-10 auction objects with varied structures) to a temp file for each test
  </action>
  <verify>
    <automated>cd /Users/lancethompson/Github/wow-ah-tracker && php artisan migrate --force && php artisan test --filter=ExtractRealmListingsAction</automated>
  </verify>
  <done>
    - Migration adds 4 realm columns to ingestion_metadata
    - IngestionMetadata model updated with new fillable/casts
    - RealmPriceFetchAction fetches from connected-realm auctions endpoint
    - ExtractRealmListingsAction correctly parses nested realm auction JSON using brace-depth scanning
    - All tests pass: buyout extraction, bid-only skipping, catalog filtering, nested object handling
  </done>
</task>

<task type="auto">
  <name>Task 2: FetchRealmAuctionDataJob, batch jobs, and schedule</name>
  <files>
    app/Jobs/FetchRealmAuctionDataJob.php,
    app/Jobs/DispatchRealmPriceBatchesJob.php,
    app/Jobs/AggregateRealmPriceBatchJob.php,
    routes/console.php
  </files>
  <action>
1. Create `FetchRealmAuctionDataJob` — mirrors `FetchCommodityDataJob` exactly but:
   - Implements `ShouldQueue, ShouldBeUnique` (same as commodity)
   - `$uniqueFor = 3540` (same)
   - Injects `RealmPriceFetchAction` (not `PriceFetchAction`)
   - Gate checks use `realm_last_modified_at` and `realm_response_hash` from `IngestionMetadata::singleton()`
   - On fetch failure, increment `realm_consecutive_failures` (not `consecutive_failures`)
   - Dispatches `DispatchRealmPriceBatchesJob` (not `DispatchPriceBatchesJob`)
   - All log messages prefixed with `FetchRealmAuctionDataJob:`

2. Create `DispatchRealmPriceBatchesJob` — mirrors `DispatchPriceBatchesJob` exactly but:
   - Chunks catalog items into `AggregateRealmPriceBatchJob` batches
   - On batch completion, updates `realm_last_modified_at`, `realm_response_hash`, `realm_last_fetched_at`, and resets `realm_consecutive_failures` to 0
   - Log messages prefixed with `DispatchRealmPriceBatchesJob:`

3. Create `AggregateRealmPriceBatchJob` — mirrors `AggregatePriceBatchJob` exactly but:
   - Injects `ExtractRealmListingsAction` (not `ExtractListingsAction`)
   - Injects `PriceAggregateAction` (reused as-is — the listings already have `unit_price`/`quantity` shape)
   - Same PriceSnapshot insert logic

4. Update `routes/console.php`:
   - Add `use App\Jobs\FetchRealmAuctionDataJob;`
   - Add `Schedule::job(new FetchRealmAuctionDataJob)->hourly()->at('30');` — offset 30 minutes from commodity polling to spread API load
  </action>
  <verify>
    <automated>cd /Users/lancethompson/Github/wow-ah-tracker && php artisan test && php artisan schedule:list 2>&1 | grep -i realm</automated>
  </verify>
  <done>
    - FetchRealmAuctionDataJob dispatches realm price pipeline hourly
    - DispatchRealmPriceBatchesJob chunks items and dispatches batch jobs
    - AggregateRealmPriceBatchJob extracts realm listings and writes PriceSnapshot rows
    - Schedule shows FetchRealmAuctionDataJob at :30 past each hour
    - All existing tests still pass (commodity pipeline unchanged)
  </done>
</task>

</tasks>

<verification>
- `php artisan migrate` succeeds — realm columns added to ingestion_metadata
- `php artisan test` — all tests pass (existing + new ExtractRealmListingsAction tests)
- `php artisan schedule:list` — shows both FetchCommodityDataJob (hourly) and FetchRealmAuctionDataJob (hourly at :30)
- `IngestionMetadata::singleton()` returns model with both commodity and realm gate columns
- `RealmPriceFetchAction` targets the correct endpoint URL with connected_realm_id from config
- `ExtractRealmListingsAction` correctly handles nested item objects and skips bid-only auctions
</verification>

<success_criteria>
Realm auction prices for BoE items are polled hourly (offset from commodity polling) and written as PriceSnapshot rows using the same aggregation logic. The two pipelines are fully independent — neither can interfere with the other's gate checks or failure tracking.
</success_criteria>

<output>
After completion, create `.planning/quick/15-add-hourly-realm-auction-price-polling-f/15-SUMMARY.md`
</output>
