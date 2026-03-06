# Quick Task 27: Fix recipe list profit columns not rendering and default sort to highest profit

## Task 1: Fix profit columns not rendering

**Files:** `resources/views/livewire/pages/crafting-detail.blade.php`
**Root cause:** `<template x-if>` inside `<tr>` doesn't work — browsers hoist `<template>` out of table elements per HTML parsing rules. The `<td>` elements for Tier 1, Tier 2, and Median Profit never render.
**Fix:** Replace `<template x-if>` with `x-show` directly on `<td>` elements.
**Verify:** All crafting tests pass, profit columns render in browser.

## Sort behavior

Default sort is already `sortBy: 'median_profit'` + `sortDir: 'desc'` (highest profit first). No change needed.
