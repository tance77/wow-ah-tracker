# Quick Task 29: Remove auto-watch from SyncRecipesCommand

## Summary

Removed auto-watch behavior from `blizzard:sync-recipes`. The command was adding every reagent and crafted item to user 1's watchlist (hundreds of items). Watchlist is now purely user-managed — items are only added manually or via shuffle auto-watch.

## Changes

| File | Change |
|------|--------|
| `app/Console/Commands/SyncRecipesCommand.php` | Removed WatchedItem import, auto-watch blocks, counter variables, summary output |
| `tests/Feature/BlizzardApi/SyncRecipesCommandTest.php` | Replaced auto-watch tests with "no auto-watch" assertion, removed WatchedItem checks from dry-run and idempotent tests |

## Commit

`5eca757` — fix(quick-29): remove auto-watch from SyncRecipesCommand
