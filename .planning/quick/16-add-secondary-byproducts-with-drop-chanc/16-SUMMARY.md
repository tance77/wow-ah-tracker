---
phase: quick-16
plan: 01
subsystem: shuffles
tags: [byproducts, calculator, livewire, alpine]
dependency_graph:
  requires: [ShuffleStep, Shuffle, CatalogItem, WatchedItem, shuffle-detail.blade.php]
  provides: [ShuffleStepByproduct model, byproduct CRUD UI, byproduct EV in calculator]
  affects: [shuffle profit badges, batch calculator output, watched items auto-watch]
tech_stack:
  added: []
  patterns: [hasMany byproducts, orphan cleanup on delete, Alpine.js getter composition]
key_files:
  created:
    - database/migrations/2026_03_06_100000_create_shuffle_step_byproducts_table.php
    - app/Models/ShuffleStepByproduct.php
    - database/factories/ShuffleStepByproductFactory.php
  modified:
    - app/Models/ShuffleStep.php
    - app/Models/Shuffle.php
    - resources/views/livewire/pages/shuffle-detail.blade.php
decisions:
  - Used decimal(5,2) for chance_percent to support fractional percentages like 0.50%
  - Byproduct EV calculated per-step based on input batches (cascadedQty / input_qty)
  - Renamed migration timestamp to 2026_03_06_100000 to avoid collision with existing 000001
metrics:
  duration: 4 min
  completed: "2026-03-05T21:36:42Z"
---

# Quick Task 16: Add Secondary Byproducts with Drop Chance

Byproduct model with per-step chance/qty, auto-watch integration, and Alpine.js calculator EV inclusion for accurate shuffle profit estimation.

## What Was Built

### Task 1: Migration, Model, Factory, and Relationships (a2d425e)

Created the `shuffle_step_byproducts` table with columns: shuffle_step_id (FK cascade delete), blizzard_item_id, item_name (denormalized), chance_percent (decimal 5,2), quantity (unsigned int, default 1). The `ShuffleStepByproduct` model has `step()` BelongsTo and `catalogItem()` BelongsTo (via blizzard_item_id). `ShuffleStep` gained a `byproducts()` HasMany relationship. Both `ShuffleStep::boot()` deleted handler and `Shuffle::boot()` deleting handler were updated to include byproduct blizzard_item_ids in orphan watched-item cleanup checks. `Shuffle::profitPerUnit()` now eager-loads byproducts with catalog items and price snapshots, then sums byproduct EV (price * chance/100 * qty * batches) into gross output before applying the 5% AH cut.

### Task 2: Byproduct UI and Calculator Integration (1df5c82)

Added Livewire component properties and methods for byproduct CRUD: `byproductSuggestions()` computed (same search pattern as input/output), `selectByproductItem()`, `addByproduct()` (creates record + auto-watches item), `removeByproduct()` (deletes record + orphan cleanup). Updated `steps()` eager load, `priceData()` item collection, and `calculatorSteps()` data shape to include byproducts. In the Blade template, each step card now shows existing byproducts as compact rows (item name, chance badge, qty badge, price) with a red X remove button, plus a "+ Byproduct" button that opens an inline form with item search dropdown, chance % input, and quantity input. The Alpine.js `batchCalculator` gained a `_byproductEV` getter that computes expected value per step for min/max scenarios, added to `grossValueMin` and `grossValueMax`. A conditional "incl. byproduct EV" line appears in the profit summary when byproducts contribute value.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] Migration filename collision**
- **Found during:** Task 1
- **Issue:** Plan specified `2026_03_06_000001` but that timestamp was already used by an existing migration
- **Fix:** Used `2026_03_06_100000` instead
- **Files modified:** database/migrations/2026_03_06_100000_create_shuffle_step_byproducts_table.php

## Commits

| Task | Commit | Description |
|------|--------|-------------|
| 1 | a2d425e | Migration, model, factory, relationships, profitPerUnit update |
| 2 | 1df5c82 | Byproduct UI in step editor and calculator integration |
