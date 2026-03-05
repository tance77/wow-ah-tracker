# Quick Task 17: Fix blizzard:sync-catalog timeout on Laravel Cloud

## Changes

**File:** `app/Console/Commands/SyncCatalogCommand.php`

Added `--limit` option to cap how many new items are processed per run:

- `--limit=500` processes at most 500 new items, then exits cleanly
- Cache file is preserved when limit is active so subsequent runs skip the API download
- DB-based dedup ensures already-imported items are skipped on re-run
- Clear messaging shows how many items remain after a limited run

## Usage

```bash
# Process 500 items per run (safe for 15-min timeout)
php artisan blizzard:sync-catalog --limit=500

# Re-run to continue (automatically skips already-imported items)
php artisan blizzard:sync-catalog --limit=500
```

Schedule multiple runs or re-run manually until "Nothing to import" appears.
