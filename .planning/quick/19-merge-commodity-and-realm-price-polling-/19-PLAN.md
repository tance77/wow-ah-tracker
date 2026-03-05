# Quick Task 19: Merge commodity and realm price polling into single hourly run

## Problem
Commodity job at :00 and realm job at :30 both iterate ALL catalog items. Items only in one market get 0-price snapshots from the other, showing broken data.

## Fix
1. Both jobs run at :00 (same time)
2. Skip writing snapshots for items with no listings (instead of writing 0s)
3. Update test that expected 0-metric snapshots
