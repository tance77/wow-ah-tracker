# Quick Task 19: Merge commodity and realm price polling into single hourly run

## Changes

- `AggregatePriceBatchJob` and `AggregateRealmPriceBatchJob`: skip items with empty listings instead of writing 0-price snapshots
- `routes/console.php`: both jobs now run `->hourly()` at :00 instead of staggering
- Updated test to expect no snapshot (instead of 0-metric snapshot) for unlisted items
- All 37 data ingestion tests pass
