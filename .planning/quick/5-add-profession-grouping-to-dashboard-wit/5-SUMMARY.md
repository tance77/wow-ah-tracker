---
phase: quick-5
plan: 01
subsystem: dashboard, watchlist, database
tags: [profession, grouping, livewire, alpine, migration]
dependency_graph:
  requires: []
  provides: [profession-column, profession-grouping]
  affects: [dashboard, watchlist]
tech_stack:
  added: []
  patterns: [alpine-collapsible, livewire-computed-groupby]
key_files:
  created:
    - database/migrations/2026_03_04_000000_add_profession_to_watched_items.php
  modified:
    - app/Models/WatchedItem.php
    - resources/views/livewire/pages/watchlist.blade.php
    - resources/views/livewire/pages/dashboard.blade.php
decisions:
  - "Ungrouped items keyed as empty string from groupBy(profession ?? '') to naturally sort last via 'zzz' sentinel"
  - "Used x-transition (not x-collapse) for collapsible sections — no extra Alpine plugin required"
metrics:
  duration: "~10 minutes"
  completed: "2026-03-04"
  tasks_completed: 2
  files_modified: 4
---

# Quick Task 5: Add Profession Grouping to Dashboard Summary

**One-liner:** Nullable profession column on watched_items with per-item dropdown on watchlist and collapsible profession-grouped sections (Alpine.js) on dashboard for both grid and list views.

## What Was Built

### Task 1: Profession column, model constant, watchlist dropdown
- **Migration** `2026_03_04_000000_add_profession_to_watched_items.php` adds nullable `profession` string column with index after `sell_threshold`
- **WatchedItem model** gains `PROFESSIONS` constant (13 WoW crafting professions, alphabetical) and `profession` in `$fillable`
- **Watchlist page** gains `updateProfession(int $id, ?string $value)` Livewire method and a "Profession" dropdown column between Item Name and Buy Threshold — empty string is normalized to null

### Task 2: Dashboard profession-grouped collapsible sections
- **`groupedWatchedItems()` computed property** groups the already-sorted `watchedItems` collection by profession, placing named professions alphabetically first and Ungrouped (null) last
- **Grid view** and **list view** both replaced with `@foreach` loops over groups, each group rendering a collapsible Alpine.js section with rotating chevron, item count badge, and wow-gold header text
- Section bodies use `x-show="open"` with `x-transition` for smooth open/close animation
- Sort order (signal status > magnitude > name) is preserved within each group

## Commits

| Hash | Message |
|------|---------|
| e41a01c | feat(quick-5): add profession column, model constant, and watchlist dropdown |
| bf4656c | feat(quick-5): add collapsible profession-grouped sections to dashboard |

## Deviations from Plan

None — plan executed exactly as written.

## Self-Check: PASSED

- database/migrations/2026_03_04_000000_add_profession_to_watched_items.php: FOUND
- app/Models/WatchedItem.php: FOUND (PROFESSIONS constant + profession in fillable)
- resources/views/livewire/pages/watchlist.blade.php: FOUND (dropdown column + updateProfession method)
- resources/views/livewire/pages/dashboard.blade.php: FOUND (groupedWatchedItems + collapsible sections)
- Commit e41a01c: FOUND
- Commit bf4656c: FOUND
