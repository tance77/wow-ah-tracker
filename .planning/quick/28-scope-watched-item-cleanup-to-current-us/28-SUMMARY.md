# Quick Task 28: Scope watched item cleanup to current user in shuffle step deletion

## Summary

Fixed watched item cleanup to be user-scoped. Previously, deleting a shuffle step or shuffle would delete other users' auto-watched items for the same blizzard_item_id.

## Changes

| File | Change |
|------|--------|
| `app/Models/ShuffleStep.php` | Added `->where('user_id', $step->shuffle->user_id)` to orphan cleanup DELETE |
| `app/Models/Shuffle.php` | Added user_id scoping to orphan detection query + "still referenced" subqueries |
| `resources/views/livewire/pages/shuffle-detail.blade.php` | Added `->where('user_id', auth()->id())` to removeStep cleanup |

## Commit

`6b4444d` — fix(quick-28): scope watched item cleanup to current user in shuffle operations
