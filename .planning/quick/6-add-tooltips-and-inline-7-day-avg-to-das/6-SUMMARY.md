---
phase: quick-6
plan: "01"
subsystem: frontend/dashboard
tags: [tooltips, ux, dashboard, signals]
key-files:
  modified:
    - resources/views/livewire/pages/dashboard.blade.php
decisions: []
metrics:
  duration: "~2 minutes"
  completed: "2026-03-04"
  tasks_completed: 1
  tasks_total: 1
  files_modified: 1
---

# Quick Task 6: Add Tooltips and Inline 7-Day Avg to Dashboard Summary

**One-liner:** Added title-attribute tooltips to all signal badges and trend arrows, plus inline 7-day average price display in both grid cards and list table column.

## What Was Done

### Task 1: Add tooltips to signal badges and trend arrows in both views

Updated `resources/views/livewire/pages/dashboard.blade.php` with the following changes across both grid and list views:

**Signal badge tooltips (4 badges total, 2 per view):**
- BUY badge: `title="Price is {magnitude}% below the 7-day average ({formatted rollingAvg})"`
- SELL badge: `title="Price is {magnitude}% above the 7-day average ({formatted rollingAvg})"`
- Collecting data badge: `title="Need at least 24 snapshots over 7 days to calculate signal"`

**Trend arrow tooltips (2 spans total, 1 per view):**
- When pct is available: `title="Price changed +/-{pct}% since last update"`
- When flat/no data: `title="No price change since last update"`

**Inline 7-day average display:**
- Grid view: `<div class="mt-1 text-xs text-gray-400">7d avg: <span>...</span></div>` shown below current price when `rollingAvg > 0`
- List view: New "7d Avg" column header added after "Price"; each row shows formatted rolling average or an em dash when unavailable

**Commit:** `1940891`

## Verification

- `php artisan view:cache` compiled successfully with no errors
- 4 `title="Price is` tooltip instances confirmed (2 BUY + 2 SELL across grid/list)
- 2 `Price changed` trend tooltip instances confirmed (1 per view)
- 2 `Need at least 24 snapshots` collecting-data tooltip instances confirmed
- 2 `7d avg`/`7d Avg` display instances confirmed (1 grid inline div + 1 list column header)

## Deviations from Plan

None - plan executed exactly as written.

## Self-Check: PASSED

- [x] `resources/views/livewire/pages/dashboard.blade.php` modified and committed
- [x] Commit `1940891` exists in git log
- [x] View cache compiles without errors
