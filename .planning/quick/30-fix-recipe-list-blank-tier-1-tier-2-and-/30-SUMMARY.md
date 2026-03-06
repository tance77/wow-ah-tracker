---
phase: quick-30
plan: 01
subsystem: crafting-ui
tags: [bugfix, blade, alpine, table-layout]
dependency_graph:
  requires: [quick-27]
  provides: [consistent-recipe-table-layout]
  affects: [crafting-detail-page]
tech_stack:
  patterns: [alpine-x-show-on-spans-not-tds, always-render-table-cells]
key_files:
  modified:
    - resources/views/livewire/pages/crafting-detail.blade.php
decisions:
  - Use x-show on inner span elements instead of td elements to maintain consistent DOM structure
metrics:
  duration: "33 seconds"
  completed: "2026-03-05"
---

# Quick Task 30: Fix Recipe List Blank Tier 1, Tier 2, and Median Profit Columns

Always-rendered 3-cell profit column layout using inner Alpine x-show spans instead of conditional td elements that caused DOM mismatch.

## What Changed

### Task 1: Replace conditional td elements with always-rendered cells using inline ternary content (0a131f5)

**Problem:** The previous fix (quick-27) replaced `<template x-if>` with `x-show` on `<td>` elements, but columns were still blank. The root cause was that `x-show` on `<td>` creates conflicting DOM structure: commodity rows had 6 `<td>` elements (3 profit + 1 hidden colspan=3), and non-commodity rows had 4 `<td>` elements (3 hidden + 1 visible colspan=3). This inconsistency within the same `x-for` loop caused browsers to render the table incorrectly.

**Fix:** Replaced the 4 conditional `<td>` elements (3 with `x-show="recipe.is_commodity"` + 1 with `x-show="!recipe.is_commodity" colspan="3"`) with exactly 3 unconditional `<td>` elements. Each cell uses inner `<span x-show>` elements to toggle content:

- **Cell 1 (Tier 1):** Shows "Realm AH -- not tracked" for non-commodity, profit_silver with color coding for commodity
- **Cell 2 (Tier 2):** Empty for non-commodity, profit_gold with color coding for commodity
- **Cell 3 (Median Profit):** Empty for non-commodity, median_profit with color coding for commodity

Every `<tr>` now always has exactly 5 `<td>` children (name, reagent_cost, tier1, tier2, median), eliminating DOM structure inconsistency.

**Files modified:** `resources/views/livewire/pages/crafting-detail.blade.php`

## Deviations from Plan

None - plan executed exactly as written.

## Verification

- `php artisan view:cache` compiled successfully
- No `x-show` or `x-if` attributes on any `<td>` elements in profit columns
- All rows render exactly 5 `<td>` elements

## Self-Check: PASSED
