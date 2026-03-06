# Quick Task 28: Scope watched item cleanup to current user in shuffle step deletion

## Task 1: Add user_id scoping to all watched item cleanup paths

**Files:** `app/Models/ShuffleStep.php`, `app/Models/Shuffle.php`, `resources/views/livewire/pages/shuffle-detail.blade.php`
**Root cause:** Auto-watched item deletion on step/shuffle removal was unscoped — `WatchedItem::where('blizzard_item_id', ...)` deleted ALL users' watched items, not just the current user's.
**Fix:** Add `->where('user_id', ...)` to all 3 cleanup paths.
