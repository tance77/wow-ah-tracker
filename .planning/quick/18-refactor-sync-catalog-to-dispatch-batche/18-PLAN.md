# Quick Task 18: Refactor sync-catalog to dispatch batched jobs

## Task 1: Create SyncCatalogBatchJob

**Files:** `app/Jobs/SyncCatalogBatchJob.php`

**Action:**
- Extract `processBatch` logic from SyncCatalogCommand into a queued job
- Job takes: array of item IDs, region, fresh flag
- Job fetches its own token via BlizzardTokenService
- Processes items in sub-chunks of 20 (matching current Http::pool size) with 1s pause between
- Uses same category resolution and upsert logic
- Follows project pattern: `implements ShouldQueue`, `use Batchable, Queueable`

## Task 2: Refactor SyncCatalogCommand to dispatch jobs

**Files:** `app/Console/Commands/SyncCatalogCommand.php`

**Action:**
- Keep: ID fetching (commodities + realm), caching, filtering existing items
- Remove: inline processBatch loop, progress bar, retry queue, `--limit` option
- Add: chunk new IDs into groups of ~200 (fits within 1 min), dispatch as Bus::batch of SyncCatalogBatchJob
- Add `then` callback to run assignQualityTiers and clean up cache file
- Add `catch` callback to log failures
- Move category resolution helpers (CLASS_MAP, TRADESKILL_SUBCLASS_MAP, resolveCategory, resolveLocalizedName) to the job since that's where they're used now
- Keep `--tiers-only`, `--rarity-only`, `--dry-run`, `--fresh`, `--realm` options (dry-run just counts dispatched jobs without dispatching)
