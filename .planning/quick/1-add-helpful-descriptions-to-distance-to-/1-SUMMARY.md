---
phase: quick
plan: 1
subsystem: ui
tags: [ux, help-text, stat-cards, item-detail]
dependency_graph:
  requires: []
  provides: [descriptive-help-text-for-distance-metrics]
  affects: [item-detail-page]
tech_stack:
  added: []
  patterns: [conditional-blade-directives, contextual-ui-text]
key_files:
  created: []
  modified:
    - resources/views/livewire/pages/item-detail.blade.php
decisions:
  - "Used conditional text for Distance to Buy and Distance to Sell to show context-aware messages based on whether price is above/below target"
  - "Static text for 7d Volatility since the thresholds are fixed (under 5% = stable, over 15% = volatile)"
metrics:
  duration: "3 minutes"
  completed: "2026-03-04T21:57:14Z"
  tasks_completed: 1
  files_modified: 1
---

# Quick Task 1: Add Helpful Descriptions to Distance to Buy/Sell and 7d Volatility Summary

**One-liner:** Contextual help text under each metric card using conditional Blade expressions to explain current value meaning in plain language.

## What Was Built

Added small descriptive help text lines below the value display in three stat cards on the item detail page. The text is styled with `mt-1 text-xs text-gray-500` to remain subtle and consistent with the existing design language.

### Changes Made

**7d Volatility card** — Static explanatory text:
> "How much the price fluctuates. Under 5% = stable, over 15% = volatile."

**Distance to Buy card** — Contextual text based on current value:
- When positive (price above buy target): "Price is Xg above your buy target. Wait for it to drop."
- When zero or negative (at or below buy target): "Price is at or below your buy target!"

**Distance to Sell card** — Contextual text based on current value:
- When positive (price hasn't reached sell target): "Price needs to rise Xg more to hit your sell target."
- When zero or negative (at or above sell target): "Price is at or above your sell target!"

## Commits

| Task | Name | Commit | Files |
| ---- | ---- | ------ | ----- |
| 1 | Add descriptive help text to stat cards | 0e0eb91 | resources/views/livewire/pages/item-detail.blade.php |

## Deviations from Plan

None - plan executed exactly as written.

## Self-Check: PASSED

- [x] File modified: `resources/views/livewire/pages/item-detail.blade.php` — FOUND
- [x] Commit 0e0eb91 — FOUND
- [x] `php artisan view:cache` succeeded with no errors
- [x] `grep -c` returned 3 matching help text divs
