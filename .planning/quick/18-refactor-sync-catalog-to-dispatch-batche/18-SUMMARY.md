# Quick Task 18: Refactor sync-catalog to dispatch batched jobs

## Changes

### New: `app/Jobs/SyncCatalogBatchJob.php`
- Queued job that processes a chunk of item IDs (fetch item data + media from Blizzard API, upsert to DB)
- Uses `Batchable` trait for `Bus::batch()` integration
- Processes in sub-chunks of 20 with Http::pool (matching original batch size)
- Includes rate-limit retry logic
- Contains category resolution logic (moved from command)

### Modified: `app/Console/Commands/SyncCatalogCommand.php`
- Removed inline item processing (processBatch method, progress bar, retry loop)
- Removed `--limit` option (no longer needed)
- Now dispatches `SyncCatalogBatchJob` via `Bus::batch()` with 200 items per job (~under 1 minute each)
- `then` callback: cleans up cache file + runs quality tier assignment
- `catch` callback: logs failures
- `--dry-run` shows how many jobs would be dispatched without dispatching
- Added `runQualityTierAssignment()` static method for use from batch callback

## How it works

```
blizzard:sync-catalog
  ├── Fetch commodity IDs from API (or resume from cache)
  ├── Filter out existing DB items
  ├── Chunk remaining into groups of 200
  └── Bus::batch([SyncCatalogBatchJob, ...])
        ├── Each job: fetch 200 items from Blizzard API + upsert
        ├── then(): clean cache + assign quality tiers
        └── catch(): log errors
```

Each job runs independently in the queue, well under the 15-minute Laravel Cloud limit.
