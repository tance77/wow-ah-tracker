# Quick Task 25: Fix share button — wrong Livewire 3 event detail access pattern

## Task 1: Fix event detail access in both Blade templates

**Files:** `shuffles.blade.php`, `shuffle-detail.blade.php`
**Action:** Change `$event.detail[0].json` to `$event.detail.json` and `$event.detail[0].shuffleId` to `$event.detail.shuffleId`
**Verify:** All ShuffleCrud tests pass
**Done:** Share button copies JSON to clipboard correctly
