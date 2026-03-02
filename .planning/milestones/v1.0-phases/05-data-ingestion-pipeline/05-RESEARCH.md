# Phase 5: Data Ingestion Pipeline - Research

**Researched:** 2026-03-01
**Domain:** Laravel Queues (ShouldBeUnique), Task Scheduler, Price Aggregation Algorithm, Pest Feature Testing
**Confidence:** HIGH (Laravel patterns verified via official docs; aggregation algorithm is pure PHP math with no external dependencies)

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| DATA-01 | Scheduled job fetches commodity prices from Blizzard API every 15 minutes | `Schedule::job(new FetchCommodityPricesJob)->everyFifteenMinutes()` in `routes/console.php`; database queue driver already configured |
| DATA-02 | Each snapshot stores min price, average price, median price, and total volume | `PriceAggregateAction` computes all four metrics from raw Blizzard `{unit_price, quantity}` listing pairs; `PriceSnapshot::create()` writes one row per watched item |
| DATA-03 | Prices stored as integers (copper) to avoid rounding errors | Blizzard `unit_price` is already an integer in copper; `price_snapshots` columns are `BIGINT UNSIGNED` (locked schema decision from Phase 1); no float conversion needed |
| DATA-06 | Job uses withoutOverlapping to prevent duplicate runs | `ShouldBeUnique` with `$uniqueFor = 840` (14-minute lock); prevents second dispatch while first is processing; cache-backed atomic lock |
</phase_requirements>

---

## Summary

Phase 5 wires together three pieces: (1) `PriceAggregateAction` — pure PHP math transforming raw Blizzard listing arrays into `{min_price, avg_price, median_price, total_volume}`, (2) `FetchCommodityPricesJob` — a queued job implementing `ShouldBeUnique` that orchestrates the fetch and aggregate actions and writes `price_snapshots` rows, and (3) the scheduler entry in `routes/console.php` that fires the job every 15 minutes.

The hardest part of this phase is the **median calculation**. The Blizzard commodities response does not return a flat list of individual items — it returns a frequency distribution: each listing is `{unit_price, quantity}`, where `quantity` is how many units are offered at that price. Computing the true median requires expanding the frequency distribution conceptually (via cumulative sum), not sorting a flat array. The phase success criteria make this explicit. No external library is needed: this is a small, deterministic computation over sorted arrays.

The `ShouldBeUnique` interface prevents a second instance of the job from being dispatched while the first is still on the queue or being processed. `$uniqueFor = 840` seconds (14 minutes) is the lock duration — just under the 15-minute fire interval, giving the lock time to release before the next scheduled dispatch. The queue driver is already set to `database` in `config/queue.php`, and the `jobs` migration exists, so no infrastructure changes are needed. The scheduler is wired in `routes/console.php` using `Schedule::job()` with `everyFifteenMinutes()`.

**Primary recommendation:** Implement `PriceAggregateAction` as an invokable class that sorts listings by `unit_price`, computes cumulative quantity, and finds the bucket containing the median unit. Implement `FetchCommodityPricesJob` as a queued job with `ShouldBeUnique`, `$uniqueFor = 840`, and a `handle()` method that queries WatchedItems, calls PriceFetchAction, iterates over each watched item's listings, calls PriceAggregateAction, and writes PriceSnapshot rows. Wire the scheduler in `routes/console.php`.

---

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| `Illuminate\Contracts\Queue\ShouldQueue` | Laravel 12 | Makes job run asynchronously on the queue | Required interface for all queued jobs |
| `Illuminate\Contracts\Queue\ShouldBeUnique` | Laravel 12 | Prevents second job dispatch while first is active | Directly satisfies DATA-06; uses cache atomic locks |
| `Illuminate\Foundation\Queue\Queueable` | Laravel 12 | Trait providing `onQueue()`, `onConnection()`, `delay()` | Standard for all queued jobs |
| `Illuminate\Support\Facades\Schedule` | Laravel 12 | Defines scheduled tasks in `routes/console.php` | Built-in Laravel scheduler; `everyFifteenMinutes()` is a named method |
| Pest 3.8 | Already installed | Feature tests with `Http::fake()`, `Queue::fake()`, direct `handle()` invocation | Established project test framework |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| `Illuminate\Support\Facades\Log` | Laravel 12 | Moderate logging: job start, item counts, errors | Same pattern as BlizzardTokenService and PriceFetchAction |
| `Illuminate\Support\Facades\DB` or Eloquent | Laravel 12 | Write `price_snapshots` rows and query `watched_items` | Use Eloquent `PriceSnapshot::create()` and `WatchedItem::pluck()` |
| `php artisan make:job FetchCommodityPricesJob` | Laravel 12 | Scaffolds job class with Queueable trait and ShouldQueue | Standard artisan generator |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| `ShouldBeUnique` | `withoutOverlapping()` scheduler modifier | `withoutOverlapping()` prevents concurrent execution but allows duplicate jobs to pile up in the queue; `ShouldBeUnique` prevents the second dispatch entirely — cleaner for a single periodic job |
| `ShouldBeUnique` | `ShouldBeUniqueUntilProcessing` | `ShouldBeUniqueUntilProcessing` releases the lock the moment a worker picks up the job, allowing a re-dispatch before the first finishes. Wrong for this use case — keep `ShouldBeUnique` which holds the lock until processing is complete |
| Database queue driver | Sync driver | Sync runs the job inline without a worker — `schedule:run` would block. Database driver requires a queue worker but supports `ShouldBeUnique` correctly |
| `Schedule::job()` | `Schedule::command()` with a custom artisan command | `Schedule::job()` is the direct approach when you already have a job class; custom command adds an unnecessary layer |

**Installation:** No new packages required. All dependencies are already in `composer.json`.

---

## Architecture Patterns

### Recommended Project Structure
```
app/
├── Actions/
│   ├── PriceFetchAction.php         # Exists (Phase 4) — fetch + filter from Blizzard
│   └── PriceAggregateAction.php     # New — compute min/avg/median/volume from listing array
├── Jobs/
│   └── FetchCommodityPricesJob.php  # New — orchestrator: fetch → aggregate → persist
routes/
└── console.php                      # Modified — add Schedule::job() entry
tests/
└── Feature/
    └── DataIngestion/
        ├── PriceAggregateActionTest.php   # New — pure math unit-style tests
        └── FetchCommodityPricesJobTest.php # New — full pipeline integration test
```

### Pattern 1: ShouldBeUnique Job with 14-Minute Lock

**What:** A queued job that can only have one instance dispatched at a time. If the job is already on the queue or being processed, subsequent dispatches are silently discarded.

**When to use:** Periodic background jobs where running two instances simultaneously would cause double-writes or wasted quota.

**Lock duration:** `$uniqueFor = 840` (14 minutes in seconds). This releases the lock before the next 15-minute scheduler tick, preventing permanent lock if the job crashes mid-run. The default without `$uniqueFor` would hold the lock indefinitely if the job fails.

```php
// Source: https://laravel.com/docs/12.x/queues#unique-jobs (verified)
<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class FetchCommodityPricesJob implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    /**
     * The number of seconds after which the job's unique lock will be released.
     * 840 seconds = 14 minutes — releases just before the 15-minute scheduler fires again.
     */
    public int $uniqueFor = 840;

    public function handle(
        PriceFetchAction $fetchAction,
        PriceAggregateAction $aggregateAction,
    ): void {
        // ...
    }
}
```

**Critical nuance:** `ShouldBeUnique` uses the default cache driver for its atomic lock. The project uses the `file` cache driver locally (and `database` queue driver). Both the `file` and `database` cache drivers support atomic locks, so no driver change is needed.

### Pattern 2: Scheduler Wiring in routes/console.php

**What:** Register the job with the Laravel scheduler using `Schedule::job()` and `everyFifteenMinutes()`.

**When to use:** Replace the existing `Artisan::command()` stub — the file already exists; add the `Schedule` import and `Schedule::job()` call.

```php
// Source: https://laravel.com/docs/12.x/scheduling#scheduling-queued-jobs (verified)
<?php

declare(strict_types=1);

use App\Jobs\FetchCommodityPricesJob;
use Illuminate\Support\Facades\Schedule;

Schedule::job(new FetchCommodityPricesJob)->everyFifteenMinutes();
```

**Verification:** `php artisan schedule:list` will show the job and its next run time.

### Pattern 3: Frequency-Distribution Median Algorithm

**What:** The Blizzard API response is a frequency distribution — each entry is `{unit_price, quantity}`. To compute the true median, you must use the cumulative quantity, not sort individual items.

**Why this matters:** The success criterion explicitly states: "`PriceAggregateAction` correctly computes the median from the frequency distribution of `{quantity, unitPrice}` listing pairs — not a simple array sort." A naive `sort($prices)` will produce a wrong answer because quantity is not 1 per listing.

**Algorithm (verified via statistics literature — no library needed):**

```php
// Source: algorithm verified against https://en.wikipedia.org/wiki/Weighted_median
// and https://community.sisense.com/t5/knowledge-base/calculating-weighted-median-...
// Implementation is pure PHP — no external package required

/**
 * Compute the median unit_price from a frequency distribution.
 *
 * @param array<int, array{unit_price: int, quantity: int}> $listings
 */
private function computeMedian(array $listings): int
{
    if (empty($listings)) {
        return 0;
    }

    // Sort by unit_price ascending (required — Blizzard listings are not sorted)
    usort($listings, fn(array $a, array $b): int => $a['unit_price'] <=> $b['unit_price']);

    $totalVolume = (int) array_sum(array_column($listings, 'quantity'));

    // Median position: middle unit when all units are laid out in price order
    // For even total, use lower-median (floor) — consistent, avoids float arithmetic
    $medianPosition = (int) ceil($totalVolume / 2);

    $cumulative = 0;
    foreach ($listings as $listing) {
        $cumulative += $listing['quantity'];
        if ($cumulative >= $medianPosition) {
            return $listing['unit_price'];
        }
    }

    // Fallback — should not be reached if listings is non-empty
    return $listings[array_key_last($listings)]['unit_price'];
}
```

**Why `ceil($totalVolume / 2)` and not `floor`:** When total volume is odd, both give the true middle. When even, `ceil` picks the upper-middle unit. Either convention is acceptable, but pick one and verify it in tests. The success criterion checks for a non-zero integer — any consistent median position formula passes.

### Pattern 4: Full PriceAggregateAction

**What:** Invokable action class receiving the filtered listing array for a single item and returning the four metrics.

**Input:** `array<int, array{id: int, item: array{id: int}, unit_price: int, quantity: int, time_left: string}>` — the already-filtered output from `PriceFetchAction` for a single `blizzard_item_id`.

**Output:** `array{min_price: int, avg_price: int, median_price: int, total_volume: int}`

```php
<?php

declare(strict_types=1);

namespace App\Actions;

class PriceAggregateAction
{
    /**
     * Aggregate raw Blizzard commodity listings into price metrics.
     *
     * @param  array<int, array{unit_price: int, quantity: int}>  $listings
     * @return array{min_price: int, avg_price: int, median_price: int, total_volume: int}
     */
    public function __invoke(array $listings): array
    {
        if (empty($listings)) {
            return [
                'min_price'    => 0,
                'avg_price'    => 0,
                'median_price' => 0,
                'total_volume' => 0,
            ];
        }

        $totalVolume  = 0;
        $totalValue   = 0;
        $minPrice     = PHP_INT_MAX;

        foreach ($listings as $listing) {
            $price    = $listing['unit_price'];
            $quantity = $listing['quantity'];

            $totalVolume += $quantity;
            $totalValue  += $price * $quantity;

            if ($price < $minPrice) {
                $minPrice = $price;
            }
        }

        $avgPrice    = (int) round($totalValue / $totalVolume);
        $medianPrice = $this->computeMedian($listings);

        return [
            'min_price'    => $minPrice,
            'avg_price'    => $avgPrice,
            'median_price' => $medianPrice,
            'total_volume' => $totalVolume,
        ];
    }

    /**
     * @param  array<int, array{unit_price: int, quantity: int}>  $listings
     */
    private function computeMedian(array $listings): int
    {
        usort($listings, fn(array $a, array $b): int => $a['unit_price'] <=> $b['unit_price']);

        $totalVolume    = (int) array_sum(array_column($listings, 'quantity'));
        $medianPosition = (int) ceil($totalVolume / 2);
        $cumulative     = 0;

        foreach ($listings as $listing) {
            $cumulative += $listing['quantity'];
            if ($cumulative >= $medianPosition) {
                return $listing['unit_price'];
            }
        }

        return $listings[array_key_last($listings)]['unit_price'];
    }
}
```

### Pattern 5: FetchCommodityPricesJob handle() Orchestration

**What:** The job's `handle()` method queries all distinct watched `blizzard_item_id` values, calls `PriceFetchAction` with the full ID list, groups results by item ID, calls `PriceAggregateAction` for each item, and persists one `PriceSnapshot` row per watched item.

**Key detail:** `PriceFetchAction` returns all filtered listings (for all watched items combined). The job must group by `item.id` before passing to `PriceAggregateAction`.

```php
public function handle(
    PriceFetchAction $fetchAction,
    PriceAggregateAction $aggregateAction,
): void {
    // 1. Collect all unique blizzard_item_ids being watched
    $watchedItems = WatchedItem::all();  // or query distinct item IDs

    if ($watchedItems->isEmpty()) {
        Log::info('FetchCommodityPricesJob: no watched items, skipping');
        return;
    }

    $itemIds = $watchedItems->pluck('blizzard_item_id')->unique()->values()->all();

    Log::info('FetchCommodityPricesJob: fetching prices', ['item_count' => count($itemIds)]);

    // 2. Fetch all listings for watched items from Blizzard
    $listings = ($fetchAction)($itemIds);

    // 3. Group listings by item ID
    $grouped = [];
    foreach ($listings as $listing) {
        $id = $listing['item']['id'];
        $grouped[$id][] = ['unit_price' => $listing['unit_price'], 'quantity' => $listing['quantity']];
    }

    $polledAt = now();

    // 4. Aggregate and persist per watched item
    foreach ($watchedItems as $watchedItem) {
        $itemListings = $grouped[$watchedItem->blizzard_item_id] ?? [];
        $metrics = ($aggregateAction)($itemListings);

        PriceSnapshot::create([
            'watched_item_id' => $watchedItem->id,
            'polled_at'       => $polledAt,
            ...$metrics,
        ]);
    }

    Log::info('FetchCommodityPricesJob: snapshots written', ['count' => $watchedItems->count()]);
}
```

**Note on empty listings:** If a watched item has no listings on the Blizzard AH, `$itemListings` will be `[]` and `PriceAggregateAction` will return all-zero metrics. The success criterion requires "non-zero integers" — the test should use watched items that exist in the fixture. Items with zero listings are an edge case the job handles gracefully but the success criteria test avoids.

### Pattern 6: Testing the Job Directly (handle() invocation)

**What:** For integration tests, call the job's `handle()` method directly rather than dispatching it. This avoids `Queue::fake()` complexities with `ShouldBeUnique` and tests the actual behavior end-to-end.

**When to use:** The feature test for the full pipeline (05-04 plan). Combine `Http::fake()` (from Phase 4 pattern) with direct `handle()` invocation and database assertions.

```php
// Source: https://laravel.com/docs/12.x/queues#testing (verified — "Test actual job execution")
it('writes one price_snapshot row per watched item after fetch', function (): void {
    // Arrange: fake HTTP
    Http::fake([
        'oauth.battle.net/token' => Http::response(['access_token' => 'test-token', ...], 200),
        '*.api.blizzard.com/data/wow/auctions/commodities*' => Http::response(
            json_decode(file_get_contents(base_path('tests/Fixtures/blizzard_commodities.json')), true),
            200
        ),
    ]);
    Cache::forget('blizzard_token');

    // Arrange: create a watched item matching a fixture item ID
    $user = User::factory()->create();
    $watched = WatchedItem::factory()->create([
        'user_id' => $user->id,
        'blizzard_item_id' => 224025,  // exists in fixture
    ]);

    // Act: invoke job directly
    $job = new FetchCommodityPricesJob;
    $job->handle(app(PriceFetchAction::class), app(PriceAggregateAction::class));

    // Assert: one snapshot row exists
    $snapshot = PriceSnapshot::where('watched_item_id', $watched->id)->first();
    expect($snapshot)->not->toBeNull();
    expect($snapshot->min_price)->toBeGreaterThan(0);
    expect($snapshot->avg_price)->toBeGreaterThan(0);
    expect($snapshot->median_price)->toBeGreaterThan(0);
    expect($snapshot->total_volume)->toBeGreaterThan(0);
});
```

### Pattern 7: Testing ShouldBeUnique Dispatch Behavior

**What:** Verify that dispatching the job twice does not result in two jobs on the queue. `Queue::fake()` supports this for unique jobs.

```php
// Source: https://laravel.com/docs/12.x/queues#testing (verified)
it('does not dispatch a second job if first is still queued', function (): void {
    Queue::fake();

    FetchCommodityPricesJob::dispatch();
    FetchCommodityPricesJob::dispatch();

    Queue::assertPushedTimes(FetchCommodityPricesJob::class, times: 1);
});
```

**Note:** This test requires `Queue::fake()` to respect `ShouldBeUnique`. Laravel 12's `Queue::fake()` does honor uniqueness constraints when the cache driver supports atomic locks (the default `array` driver used in test environment supports this). This is confirmed by the Laravel 12 docs testing section.

### Anti-Patterns to Avoid
- **Simple array sort for median:** `sort($prices); return $prices[count($prices) / 2];` ignores quantity entirely. A listing with `quantity=500` counts as 500 individual units at that price. Must use cumulative quantity traversal.
- **Float arithmetic for avg_price:** `$totalValue / $totalVolume` may produce a float. Cast to `int` with `(int) round(...)` — store copper integers only. Never store gold floats.
- **Querying DB inside PriceAggregateAction:** The action must stay pure. No DB reads inside the aggregate action — input comes from the job, not from queries.
- **Not grouping listings before aggregation:** `PriceFetchAction` returns listings for ALL watched items in a flat array. Calling `PriceAggregateAction` on the combined array would compute metrics across items, not per item.
- **Scheduling via Artisan command instead of Schedule::job():** Adding a custom `FetchPricesCommand` just to call `FetchCommodityPricesJob::dispatch()` is unnecessary indirection. `Schedule::job()` dispatches directly.
- **`$uniqueFor` omitted:** Without `$uniqueFor`, a crashed/failed job holds its lock indefinitely. Always set `$uniqueFor = 840` for a 14-minute lock window.

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Job uniqueness / overlap prevention | Custom lock files or cache flags | `ShouldBeUnique` with `$uniqueFor` | Laravel handles atomic cache locking, lock release on success/failure, and dispatch prevention natively |
| Scheduler cron management | Multiple `* * * * *` cron entries | Single cron + `routes/console.php` with `everyFifteenMinutes()` | Laravel scheduler is the standard; `schedule:list` shows all tasks and next run |
| Queue infrastructure | Redis, Beanstalkd | Database queue driver (already configured) | `jobs` migration already exists; `config/queue.php` already defaults to `database` |
| Weighted average calculation | Custom weighted mean | Standard PHP: `(int) round($totalValue / $totalVolume)` | Two-line calculation; no library needed |

**Key insight:** Every infrastructure concern for this phase (queue storage, scheduler, atomic locks) is already built into Laravel 12 and already configured in the project. The only new code is pure business logic: the aggregation math and the job orchestration.

---

## Common Pitfalls

### Pitfall 1: Wrong Median (Ignoring Quantity)
**What goes wrong:** Median computed by sorting `unit_price` values without regard to `quantity`, returning the midpoint of unique prices rather than the midpoint of total units.
**Why it happens:** Intuitive implementation: "sort prices, find middle." Works correctly only if every listing has `quantity=1`.
**How to avoid:** Use cumulative quantity traversal. Sort by `unit_price`, accumulate `quantity` in a running sum, return the `unit_price` of the bucket where the cumulative sum first reaches or exceeds `ceil(totalVolume / 2)`.
**Warning signs:** Median equals the middle price tier by count of listings, not by count of items. The test fixture should have listings with different quantities to expose this.

### Pitfall 2: Permanent Lock on Job Failure
**What goes wrong:** Job crashes halfway through (Blizzard 503, DB timeout). The `ShouldBeUnique` lock stays active for the default lock duration. Without `$uniqueFor`, the lock defaults to the job's `$timeout` property (or indefinitely for some drivers). No new job can dispatch.
**Why it happens:** `ShouldBeUnique` releases the lock on completion or after all retry attempts. A crash that doesn't trigger Laravel's job failure handling (SIGKILL, memory limit) may not release the lock.
**How to avoid:** Always set `$uniqueFor = 840` (14 minutes). The lock auto-expires 14 minutes after dispatch, before the next 15-minute scheduler tick. If needed, `php artisan schedule:clear-cache` clears stuck locks.
**Warning signs:** `schedule:list` shows the job scheduled but it never runs; `php artisan queue:work` processes nothing.

### Pitfall 3: ShouldBeUnique Requires Cache Driver with Atomic Lock Support
**What goes wrong:** `ShouldBeUnique` silently fails to enforce uniqueness if the cache driver does not support atomic locks.
**Why it happens:** Not all cache drivers support `lock()`. The `file` driver used locally DOES support atomic locks (verified). The `array` driver used in tests also supports locks. No problem exists in this project, but worth knowing.
**How to avoid:** Keep `CACHE_DRIVER=file` in `.env` and `CACHE_DRIVER=array` in `.env.testing` (or the test environment default). Both support `ShouldBeUnique`.

### Pitfall 4: Queue Worker Not Running During Development
**What goes wrong:** `schedule:run` dispatches the job to the database queue, but no worker is processing jobs, so nothing runs.
**Why it happens:** `Schedule::job()` with a database queue requires `php artisan queue:work` to be running. `schedule:run` only dispatches, not executes.
**How to avoid:** For manual testing, also run `php artisan queue:work` in a separate terminal. Alternatively, for one-off tests, temporarily change the queue connection to `sync` in `.env`. The success criterion says "running `php artisan schedule:run` triggers a fetch" — this implies a worker is running concurrently.
**Warning signs:** `schedule:run` output shows the job was dispatched but no database rows appear.

### Pitfall 5: avg_price Float Truncation vs Rounding
**What goes wrong:** `(int) ($totalValue / $totalVolume)` truncates toward zero. A true average of 150,001.7 copper becomes 150,001 instead of 150,002.
**Why it happens:** PHP integer cast truncates, not rounds.
**How to avoid:** `(int) round($totalValue / $totalVolume)` — round first, then cast. The difference is at most 1 copper — acceptable, but consistent rounding is better than truncation.

### Pitfall 6: PriceSnapshot Per User vs Per Blizzard Item
**What goes wrong:** Writing one snapshot per unique `blizzard_item_id` instead of one per `watched_item_id`.
**Why it happens:** Multiple users can watch the same Blizzard item. The schema has `watched_item_id` as the FK — each user's `WatchedItem` row gets its own snapshot.
**How to avoid:** Iterate over `WatchedItem::all()` (not unique item IDs) when writing snapshots. The Blizzard fetch and aggregation only need unique item IDs for efficiency, but the persistence step writes one row per WatchedItem.

---

## Code Examples

Verified patterns from official sources:

### Scheduler Registration (routes/console.php)
```php
// Source: https://laravel.com/docs/12.x/scheduling#scheduling-queued-jobs (verified)
<?php

declare(strict_types=1);

use App\Jobs\FetchCommodityPricesJob;
use Illuminate\Support\Facades\Schedule;

Schedule::job(new FetchCommodityPricesJob)->everyFifteenMinutes();
```

### Verify Scheduler with artisan
```bash
# List all scheduled tasks and next run times
php artisan schedule:list

# Trigger immediately (for manual verification)
php artisan schedule:run

# Run scheduler continuously during development (fires every minute)
php artisan schedule:work
```

### Median Algorithm — Test Cases for Verification
```php
// Fixture A: odd total volume
// Listings: [{price: 100, qty: 1}, {price: 200, qty: 1}, {price: 300, qty: 1}]
// Total volume: 3, medianPosition: ceil(3/2) = 2
// Cumulative: 100→1, 200→2 (>=2) → median = 200 ✓

// Fixture B: even total volume
// Listings: [{price: 100, qty: 2}, {price: 200, qty: 2}]
// Total volume: 4, medianPosition: ceil(4/2) = 2
// Cumulative: 100→2 (>=2) → median = 100 ✓ (lower-median convention)

// Fixture C: large quantity in one bucket (the important case)
// Listings: [{price: 100, qty: 500}, {price: 200, qty: 10}, {price: 300, qty: 5}]
// Total volume: 515, medianPosition: ceil(515/2) = 258
// Cumulative: 100→500 (>=258) → median = 100 ✓
// Naive sort would give price 200 (middle of 3 price points) — WRONG
```

### PriceAggregateAction Test (pure math, no DB or HTTP)
```php
// Source: project pattern — Pest feature test calling action directly
it('computes correct metrics from frequency distribution', function (): void {
    $action = new PriceAggregateAction;

    $listings = [
        ['unit_price' => 100000, 'quantity' => 200],
        ['unit_price' => 175000, 'quantity' => 50],
        ['unit_price' => 150000, 'quantity' => 100],
    ];

    $result = $action($listings);

    // min_price: cheapest listing
    expect($result['min_price'])->toBe(100000);

    // total_volume: 200 + 50 + 100 = 350
    expect($result['total_volume'])->toBe(350);

    // avg_price: (100000*200 + 175000*50 + 150000*100) / 350
    // = (20000000 + 8750000 + 15000000) / 350 = 43750000 / 350 = 125000
    expect($result['avg_price'])->toBe(125000);

    // median: sorted [{100000,200},{150000,100},{175000,50}]
    // total=350, medianPosition=ceil(350/2)=175
    // cumulative: 100000→200 (>=175) → median=100000
    expect($result['median_price'])->toBe(100000);
});
```

### Full Pipeline Integration Test Pattern
```php
it('writes price_snapshot rows after schedule:run', function (): void {
    Http::fake([...]);
    Cache::forget('blizzard_token');

    $watched = WatchedItem::factory()->create(['blizzard_item_id' => 224025]);

    $job = new FetchCommodityPricesJob;
    $job->handle(app(PriceFetchAction::class), app(PriceAggregateAction::class));

    expect(PriceSnapshot::where('watched_item_id', $watched->id)->count())->toBe(1);

    $snapshot = PriceSnapshot::where('watched_item_id', $watched->id)->first();
    expect($snapshot->min_price)->toBeInt()->toBeGreaterThan(0);
    expect($snapshot->avg_price)->toBeInt()->toBeGreaterThan(0);
    expect($snapshot->median_price)->toBeInt()->toBeGreaterThan(0);
    expect($snapshot->total_volume)->toBeInt()->toBeGreaterThan(0);
});
```

---

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| `Kernel.php` `schedule()` method | `routes/console.php` with `Schedule` facade | Laravel 11+ | `routes/console.php` is the new home; no `Kernel.php` in Laravel 12 |
| Custom artisan command wrapping job | `Schedule::job(new MyJob)` directly | Laravel 9+ | Direct job scheduling is cleaner; no wrapper command needed |
| `withoutOverlapping()` only | `ShouldBeUnique` interface | Laravel 8+ | `ShouldBeUnique` prevents dispatch entirely vs. preventing concurrent execution |
| Artisan closure scheduling | `routes/console.php` using `Schedule` facade | Laravel 11+ | `Artisan::command()` closures still work but `Schedule::` is the preferred API |

**Deprecated/outdated:**
- `app/Console/Kernel.php` with `protected function schedule(Schedule $schedule)`: This file no longer exists in Laravel 12 projects. Scheduling now lives in `routes/console.php`.
- `Artisan::command('name', function() {...})->everyFifteenMinutes()`: The closure scheduling API still works but `Schedule::job()` is preferred for queued jobs.

---

## Open Questions

1. **Queue worker process management in production**
   - What we know: The success criteria only requires `php artisan schedule:run` to trigger the fetch. This implies a queue worker must also be running for the database queue driver.
   - What's unclear: Whether the success criteria intend the `sync` queue driver (job runs inline during `schedule:run`) or the `database` driver (job dispatched, picked up by worker).
   - Recommendation: Use the `database` driver as configured (DATA-06 requires `ShouldBeUnique` which needs cache-based locking; `sync` driver ignores `ShouldBeUnique`). The 05-03 plan should document running `php artisan queue:work` alongside `schedule:run` for local verification.

2. **WatchedItem scope — all users vs. active users only**
   - What we know: The job needs to fetch prices for all watched items across all users. The schema has no concept of "active" or "paused" watching.
   - What's unclear: Should the job skip items where `blizzard_item_id` has no listings (produces all-zero snapshot)?
   - Recommendation: Write zero-metric snapshots for items with no listings. Keeps the implementation simple; the dashboard can display "no data" for zero-volume items. Do not add filtering at this phase.

3. **polled_at timestamp precision**
   - What we know: The schema uses `timestamp('polled_at')` — MySQL `TIMESTAMP` type (1-second precision).
   - What's unclear: Whether using `now()` once at job start (all items same timestamp) vs. per-item produces cleaner data.
   - Recommendation: Capture `$polledAt = now()` once at the start of `handle()` and use it for all rows in that batch. All snapshots in one run share the same `polled_at` — this enables clean time-series queries.

---

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | Pest 3.8 with pest-plugin-laravel 3.2 |
| Config file | `tests/Pest.php` (already configured with RefreshDatabase for Feature suite) |
| Quick run command | `php artisan test --filter DataIngestion` |
| Full suite command | `php artisan test` |

### Phase Requirements → Test Map

| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| DATA-01 | `schedule:run` triggers commodity fetch | feature (integration, direct handle() call) | `php artisan test --filter FetchCommodityPricesJobTest` | ❌ Wave 0 |
| DATA-02 | Snapshot row has non-zero min, avg, median, volume | feature (integration, DB assertion) | `php artisan test --filter FetchCommodityPricesJobTest` | ❌ Wave 0 |
| DATA-02 | Median computed from frequency distribution (not simple array sort) | feature (unit-style, pure math) | `php artisan test --filter PriceAggregateActionTest` | ❌ Wave 0 |
| DATA-03 | All prices are integers (copper), never floats | feature (unit-style) + DB assertion | `php artisan test --filter PriceAggregateActionTest` | ❌ Wave 0 |
| DATA-06 | Second job dispatch is rejected while first is active | feature (Queue::fake() + assertPushedTimes) | `php artisan test --filter FetchCommodityPricesJobTest` | ❌ Wave 0 |

### Sampling Rate
- **Per task commit:** `php artisan test --filter DataIngestion`
- **Per wave merge:** `php artisan test`
- **Phase gate:** Full suite green before `/gsd:verify-work`

### Wave 0 Gaps
- [ ] `tests/Feature/DataIngestion/PriceAggregateActionTest.php` — covers DATA-02 (median algorithm), DATA-03 (integer output)
- [ ] `tests/Feature/DataIngestion/FetchCommodityPricesJobTest.php` — covers DATA-01, DATA-02, DATA-06 (full pipeline + ShouldBeUnique dispatch)
- [ ] `app/Jobs/` directory (does not exist yet — `php artisan make:job` creates it)

Note: The existing `tests/Fixtures/blizzard_commodities.json` fixture from Phase 4 is reusable in Phase 5 tests. No new fixture file is needed.

---

## Sources

### Primary (HIGH confidence)
- [Laravel 12 Queues — Unique Jobs](https://laravel.com/docs/12.x/queues#unique-jobs) — `ShouldBeUnique`, `$uniqueFor`, `uniqueId()`, cache driver requirements, lock mechanics
- [Laravel 12 Queues — Testing](https://laravel.com/docs/12.x/queues#testing) — `Queue::fake()`, `Queue::assertPushedTimes()`, direct `handle()` invocation pattern
- [Laravel 12 Scheduling — Queued Jobs](https://laravel.com/docs/12.x/scheduling#scheduling-queued-jobs) — `Schedule::job()`, `everyFifteenMinutes()`, `routes/console.php` location
- [Laravel 12 Scheduling — Preventing Overlaps](https://laravel.com/docs/12.x/scheduling#preventing-task-overlaps) — `withoutOverlapping()` vs `ShouldBeUnique` distinction
- [Laravel 12 Scheduling — Running the Scheduler](https://laravel.com/docs/12.x/scheduling#running-the-scheduler) — `schedule:run`, `schedule:work`, `schedule:list`

### Secondary (MEDIUM confidence)
- [devinthewild.com — ShouldBeUnique vs WithoutOverlapping](https://devinthewild.com/article/should-be-unique-vs-without-overlapping-laravel-10) — clear behavioral distinction; verified against official docs
- [marius-ciclistu Medium — Avoid Indefinite Cache Locks](https://marius-ciclistu.medium.com/avoid-laravel-cache-locks-for-indefinite-period-for-shouldbeunique-job-and-withoutoverlapping-job-9f47443815a3) — `$uniqueFor` importance for crash recovery; consistent with official docs
- [Weighted Median Wikipedia](https://en.wikipedia.org/wiki/Weighted_median) — frequency distribution median algorithm; confirmed against Sisense community article

### Tertiary (LOW confidence)
- None — all critical claims verified against official Laravel docs or established mathematical sources.

---

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — all Laravel 12 queue and scheduler APIs verified against official docs
- Architecture: HIGH — patterns follow official docs, established project conventions, and verified math
- Aggregation algorithm: HIGH — well-established weighted median algorithm; verified against multiple sources; small deterministic calculation
- Pitfalls: HIGH — derived from official docs (lock behavior), project STATE.md (accumulated decisions), and verified community sources

**Research date:** 2026-03-01
**Valid until:** 2026-04-01 (stable Laravel 12 APIs; pure math never expires)
