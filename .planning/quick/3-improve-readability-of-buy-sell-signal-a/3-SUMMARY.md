---
phase: quick-3
plan: 01
subsystem: frontend
tags: [ui, readability, signal-bar, blade]
dependency_graph:
  requires: []
  provides: [improved-signal-bar-layout]
  affects: [item-detail-page]
tech_stack:
  added: []
  patterns: [responsive-flex-layout, semantic-label-value-pairs]
key_files:
  modified:
    - resources/views/livewire/pages/item-detail.blade.php
decisions:
  - "Used flex-wrap with gap-x-4 gap-y-1 so values reflow naturally on narrow screens without breaking layout"
  - "Labels at /60 opacity, values at full brightness with font-semibold — visual hierarchy without color changes"
metrics:
  duration: "5 minutes"
  completed: "2026-03-04"
  tasks_completed: 1
  files_modified: 1
---

# Phase quick-3 Plan 01: Improve Readability of Buy/Sell Signal Alert Bar Summary

**One-liner:** Restructured signal bar from a single cramped opacity-reduced line into labeled Price/7d Avg/Threshold value pairs with bright semibold values and subdued labels, responsive across mobile and desktop.

## Tasks Completed

| Task | Name | Commit | Files |
|------|------|--------|-------|
| 1 | Redesign signal alert bar layout for readability | a06385f | resources/views/livewire/pages/item-detail.blade.php |

## What Was Built

The buy/sell signal alert bar on the item detail page previously showed all three values (price, 7-day average, threshold) jammed into a single `text-sm font-normal text-green-400/70` span: "Price X vs 7d avg Y (threshold Z%)". This was hard to parse, especially on mobile.

The new layout uses a two-column responsive flex structure:
- Left: the existing signal badge (signal-pulse-buy / signal-pulse-sell)
- Right: three labeled value pairs wrapped with `flex-wrap gap-x-4 gap-y-1`

Each value pair uses a label-value pattern where the label is subdued (`text-green-400/60` or `text-red-400/60`) and the value is bright and bold (`font-semibold text-green-300` or `text-red-300`). This creates a clear visual hierarchy so gold amounts stand out at a glance.

On mobile the badge stacks above the values (`flex-col`). On `sm:` and wider they sit side-by-side (`sm:flex-row sm:items-center sm:justify-between`).

## Deviations from Plan

None - plan executed exactly as written.

## Verification

- `php artisan view:cache` compiled successfully with no errors
- Both buy (green) and sell (red) signal variants updated consistently
- Insufficient data and "none" cases left unchanged

## Self-Check: PASSED

- File modified: resources/views/livewire/pages/item-detail.blade.php - FOUND
- Commit a06385f - FOUND
