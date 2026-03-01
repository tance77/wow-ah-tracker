---
phase: 05-data-ingestion-pipeline
plan: "01"
subsystem: data-ingestion
tags: [price-aggregation, queued-jobs, scheduler, tdd]
dependency_graph:
  requires:
    - app/Actions/PriceFetchAction.php
    - app/Models/WatchedItem.php
    - app/Models/PriceSnapshot.php
  provides:
    - app/Actions/PriceAggregateAction.php
    - app/Jobs/FetchCommodityPricesJob.php
    - routes/console.php (scheduler wiring)
  affects:
    - Phase 06: price history data accumulates from this pipeline
tech_stack:
  added: []
  patterns:
    - Invokable Action class (pure math, no DB)
    - ShouldBeUnique with uniqueFor for job deduplication
    - Frequency-distribution median via cumulative quantity traversal
    - Schedule::job() for cron wiring
key_files:
  created:
    - app/Actions/PriceAggregateAction.php
    - app/Jobs/FetchCommodityPricesJob.php
    - tests/Unit/PriceAggregateActionTest.php
  modified:
    - routes/console.php
decisions:
  - "Frequency-distribution median via cumulative quantity traversal, not naive array sort — listings with high quantity dominate the median as intended"
  - "uniqueFor = 840 (14 minutes) — ensures lock releases before next 15-minute scheduler tick, preventing duplicate runs"
  - "One PriceSnapshot per WatchedItem (not per unique blizzard_item_id) — multiple users watching same item each get independent snapshots"
  - "polledAt captured once before the foreach loop — all snapshots in one job run share the same timestamp for consistency"
metrics:
  duration: "2 min"
  completed_date: "2026-03-01"
  tasks_completed: 2
  files_created: 3
  files_modified: 1
---

# Phase 05 Plan 01: Data Ingestion Pipeline — Aggregate Action + Job + Scheduler Summary

**One-liner:** Frequency-distribution median aggregation via cumulative quantity traversal wired into a ShouldBeUnique queued job that fires every 15 minutes.

## What Was Built

### PriceAggregateAction (pure math, no DB)

`app/Actions/PriceAggregateAction.php` is an invokable action that takes pre-filtered listings for a single item and returns four integer metrics:

- `min_price`: lowest unit_price across all listings
- `avg_price`: quantity-weighted average, rounded then cast to int
- `median_price`: frequency-distribution median via cumulative quantity traversal
- `total_volume`: sum of all listing quantities

The median algorithm sorts listings by unit_price ascending, then accumulates quantities until the cumulative sum reaches `ceil(totalVolume / 2)`. This means a single large-quantity bucket (e.g., 500 units at 100g) correctly dominates over many smaller buckets at higher prices.

### FetchCommodityPricesJob (orchestration)

`app/Jobs/FetchCommodityPricesJob.php` implements the complete fetch-aggregate-persist pipeline:

1. Load all WatchedItem rows (early return if none)
2. Collect unique blizzard_item_ids for API efficiency
3. Call PriceFetchAction to fetch Blizzard commodity listings
4. Group flat listings array by item ID
5. For EACH WatchedItem (not unique ID), compute metrics and write PriceSnapshot
6. All snapshots share the same `polledAt` timestamp

Implements `ShouldBeUnique` with `$uniqueFor = 840` (14-minute lock) to prevent duplicate runs if the job takes longer than expected.

### Scheduler Wiring

`routes/console.php` now contains:
```php
Schedule::job(new FetchCommodityPricesJob)->everyFifteenMinutes();
```

`php artisan schedule:list` confirms: `*/15 * * * * App\Jobs\FetchCommodityPricesJob`

## Tasks Completed

| Task | Description | Commit |
|------|-------------|--------|
| 1 (RED) | PriceAggregateAction failing tests | 0c231aa |
| 1 (GREEN) | PriceAggregateAction implementation | 16239ef |
| 2 | FetchCommodityPricesJob + scheduler | fe400d9 |

## Test Results

9 unit tests pass for PriceAggregateAction:
- Empty listings → all zeros
- Single listing → price is min/avg/median
- Min price across multiple listings
- Weighted average (rounded to int)
- Total volume sum
- Large quantity bucket dominates median
- Correct bucket selection when first bucket doesn't dominate
- All outputs are int type (not float)

## Deviations from Plan

None — plan executed exactly as written.

## Self-Check

- [x] `app/Actions/PriceAggregateAction.php` exists
- [x] `app/Jobs/FetchCommodityPricesJob.php` exists with `ShouldBeUnique` and `$uniqueFor = 840`
- [x] `routes/console.php` contains `Schedule::job(new FetchCommodityPricesJob)->everyFifteenMinutes()`
- [x] `php artisan schedule:list` shows the job at `*/15 * * * *`
- [x] 9 unit tests pass for PriceAggregateAction
- [x] All commit hashes verified: 0c231aa, 16239ef, fe400d9
