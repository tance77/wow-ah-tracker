# Quick Task 29: Remove auto-watch from SyncRecipesCommand

## Task 1: Remove auto-watch code from SyncRecipesCommand

**Files:** `app/Console/Commands/SyncRecipesCommand.php`, `tests/Feature/BlizzardApi/SyncRecipesCommandTest.php`
**Root cause:** `blizzard:sync-recipes` auto-watched every reagent and crafted item for hardcoded `user_id => 1`, adding hundreds of unwanted items to the watchlist.
**Fix:** Remove all auto-watch logic and counters from the command. Update tests to verify no auto-watching occurs.
