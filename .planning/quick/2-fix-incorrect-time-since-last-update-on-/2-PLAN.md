---
phase: quick-2
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - resources/views/livewire/pages/item-detail.blade.php
autonomous: true
requirements:
  - QUICK-2
must_haves:
  truths:
    - "Time since last update on item page reflects the actual latest snapshot from the database"
    - "polledAt value used in diffForHumans() is the most recent polled_at across all snapshots for the watched item"
  artifacts:
    - path: "resources/views/livewire/pages/item-detail.blade.php"
      provides: "Item detail page with correct last-update timestamp"
      contains: "priceSnapshots()->latest('polled_at')->first()"
  key_links:
    - from: "resources/views/livewire/pages/item-detail.blade.php"
      to: "WatchedItem::priceSnapshots() relationship"
      via: "method call with ordering"
      pattern: "priceSnapshots\\(\\)->latest"
---

<objective>
Fix the "time since last update" display on the item detail page so it reflects the actual latest snapshot from the database rather than whatever happens to be first in a potentially unordered cached collection.

Purpose: The item page shows incorrect (often stale) "last updated" time because line 47 uses `$this->watchedItem->priceSnapshots->first()` (property access on cached/eager-loaded collection with no ordering guarantee) instead of a fresh ordered query.

Output: One-line fix in item-detail.blade.php that queries the DB for the latest snapshot by polled_at, matching how other parts of the app (e.g. dashboard dataFreshness()) handle freshness queries.
</objective>

<execution_context>
@/Users/lancethompson/.claude/get-shit-done/workflows/execute-plan.md
@/Users/lancethompson/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@.planning/STATE.md
</context>

<tasks>

<task type="auto">
  <name>Task 1: Fix latest snapshot query to use ordered DB query</name>
  <files>resources/views/livewire/pages/item-detail.blade.php</files>
  <action>
    On line 47, change:

    ```php
    $latest = $this->watchedItem->priceSnapshots->first();
    ```

    To:

    ```php
    $latest = $this->watchedItem->priceSnapshots()->latest('polled_at')->first();
    ```

    This replaces property access (cached/eager-loaded collection, no ordering guarantee) with a method call that issues a fresh DB query ordered by polled_at descending, ensuring `$latest` is always the most recent snapshot. The `polledAt` key returned at line 106 (`$latest?->polled_at`) then contains the correct timestamp, which the template at line 239 uses via `->diffForHumans()`.

    No other changes needed — the downstream usage of `$latest` for `$currentMedian`, `$currentMin`, `$currentVolume`, and `polledAt` is all correct once `$latest` itself is the true latest record.
  </action>
  <verify>
    <automated>php artisan test --filter=ItemDetail 2>/dev/null || echo "No ItemDetail tests — manual verification needed"</automated>
  </verify>
  <done>Line 47 uses `priceSnapshots()->latest('polled_at')->first()` (method call) instead of `priceSnapshots->first()` (property access). The "Last updated" display on the item page shows the timestamp of the most recent snapshot.</done>
</task>

</tasks>

<verification>
- Line 47 of resources/views/livewire/pages/item-detail.blade.php reads: `$latest = $this->watchedItem->priceSnapshots()->latest('polled_at')->first();`
- No other lines in the file reference `priceSnapshots->first()` (property access without ordering)
- The fix matches the pattern used by dashboard's `dataFreshness()` method
</verification>

<success_criteria>
The item detail page "Last updated" / "Time since last update" display correctly reflects the most recent snapshot's polled_at timestamp, pulled fresh from the database with proper descending ordering.
</success_criteria>

<output>
After completion, create `.planning/quick/2-fix-incorrect-time-since-last-update-on-/2-SUMMARY.md`
</output>
