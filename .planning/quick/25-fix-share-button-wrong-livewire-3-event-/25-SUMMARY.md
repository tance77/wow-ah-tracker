# Quick Task 25: Fix share button — wrong Livewire 3 event detail access pattern

## Summary

Fixed the share/clipboard copy button on both shuffle pages. The bug was using Livewire 2's array-indexed event detail format (`$event.detail[0].json`) instead of Livewire 3's named property format (`$event.detail.json`).

## Changes

| File | Change |
|------|--------|
| `resources/views/livewire/pages/shuffles.blade.php` | `$event.detail[0].json` -> `$event.detail.json`, `$event.detail[0].shuffleId` -> `$event.detail.shuffleId` |
| `resources/views/livewire/pages/shuffle-detail.blade.php` | `$event.detail[0].json` -> `$event.detail.json` |

## Verification

All 24 ShuffleCrud tests passing (72 assertions).

## Commit

`964017d` — fix(quick-25): use Livewire 3 event detail format for share clipboard copy
