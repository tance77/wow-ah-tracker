# Quick Task 17: Fix blizzard:sync-catalog timeout on Laravel Cloud

## Task 1: Add --limit option to SyncCatalogCommand

**Files:** `app/Console/Commands/SyncCatalogCommand.php`

**Action:**
1. Add `{--limit=0 : Max items to process per run (0 = unlimited)}` to command signature
2. After filtering existing items (line 197), apply `->take($limit)` when limit > 0
3. When limit is active, always preserve the cache file (even on 0 failures) so the next run skips the API download
4. Display a message showing how many items remain after the limited run

**Verify:** Read the modified file and confirm the logic is correct.

**Done:** Command accepts `--limit=500` and processes at most 500 new items per run, preserving cache for resume.
