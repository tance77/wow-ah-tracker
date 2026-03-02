---
phase: 08-buy-sell-signal-indicators
plan: 01
subsystem: dashboard
tags: [signals, buy-sell, rolling-average, chart-annotations, css-animations]
dependency_graph:
  requires: [07-02]
  provides: [DASH-04, DASH-05]
  affects: [dashboard.blade.php, app.css]
tech_stack:
  added: []
  patterns:
    - Dynamic border colors via Blade conditional class interpolation
    - Rolling average flat series as a 2-point horizontal line in ApexCharts
    - ApexCharts yaxis annotations rebuilt on each updateOptions() call to avoid accumulation
    - Signal sorting via sortBy() with array callback [priority, -magnitude]
    - CSS one-shot animation via animation-iteration-count: 1 (ease-out keyframe)
key_files:
  created: []
  modified:
    - resources/css/app.css
    - resources/views/livewire/pages/dashboard.blade.php
decisions:
  - Signal sorting uses array callback returning [0|1, -magnitude] so active signals float first within the sorted collection
  - rollingAvg series is always 2-point [first, last] matching the chart timeframe — avoids re-querying after loadChart() has the snapshot range
  - annotations array is replaced (not appended) on every updateOptions() call — ApexCharts merges option objects, so passing a fresh array prevents line accumulation across timeframe changes
  - insufficient_data threshold set at 96 snapshots (one per 15 minutes x 96 = 24 hours minimum) — same as plan spec
  - signalSummary() reads from this->watchedItems (already computed with _signal) to avoid double querying
metrics:
  duration: 8 min
  completed_date: "2026-03-02"
  tasks_completed: 2
  files_modified: 2
---

# Phase 8 Plan 01: Buy/Sell Signal Indicators Summary

**One-liner:** Rolling 7-day average buy/sell signals with pill badges, colored card borders, signal-first sorting, header summary, and chart annotation lines with dashed purple 7d-avg reference series.

## What Was Built

Implemented the full buy/sell signal indicator system on the dashboard. The `rollingSignal()` method computes a 7-day rolling average and compares the current price against user-configured buy/sell thresholds to produce a signal array with type, magnitude, and rolling average. The `watchedItems()` computed property now eagerly attaches `_signal` to each item and sorts signal items to the top, ordered by descending magnitude. Cards show color-coded pill badges (BUY -X% in green, SELL +X% in red, "Collecting data" in gray for <96 snapshots) and dynamic border colors. The dashboard header shows a signal count summary. The chart was extended with a third dashed purple "7d Avg" series and buy/sell threshold annotation lines (green/red dashed horizontal lines) that replace on every chart update. CSS keyframe animations provide a one-shot pulse effect on signal badges.

## Tasks Completed

| Task | Name | Commit | Files |
|------|------|--------|-------|
| 1 | CSS keyframes, rollingSignal(), signalSummary(), watchedItems() sorting, badge/border rendering, header summary | b8953a4 | resources/css/app.css, dashboard.blade.php |
| 2 | loadChart() with rollingAvg series + annotations, JS chart handler update with 3 series + tooltip | f3c41b1 | dashboard.blade.php |

## Verification

- All 9 existing dashboard tests pass after both tasks
- `rollingSignal()` method exists and returns signal/magnitude/rollingAvg array
- `signalSummary()` method exists and returns formatted count string
- `watchedItems()` computes `_signal` per item and sorts signal-first by magnitude
- Card HTML includes BUY/SELL pill badges with pulse animation CSS classes
- Card borders dynamic: green-500/60 for buy, red-500/60 for sell, gray-700/50 for none
- "Collecting data" badge shown when insufficient_data
- Header shows signal count summary next to freshness text
- CSS keyframes for signal-pulse-buy and signal-pulse-sell in app.css
- `loadChart()` dispatches 4 named args: median, min, rollingAvg, annotations
- JS handler renders 3 series: Median (gold solid), Min (blue solid), 7d Avg (purple dashed)
- Annotations replaced (not accumulated) on each updateOptions()

## Deviations from Plan

None - plan executed exactly as written.

## Self-Check: PASSED

- resources/css/app.css: FOUND
- resources/views/livewire/pages/dashboard.blade.php: FOUND
- Commit b8953a4: FOUND (feat(08-01): signal pulse CSS keyframes...)
- Commit f3c41b1: FOUND (feat(08-01): chart threshold annotation lines...)
- All 9 dashboard tests: PASSED
