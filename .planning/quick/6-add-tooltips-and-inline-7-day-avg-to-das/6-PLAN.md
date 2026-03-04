---
phase: quick-6
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - resources/views/livewire/pages/dashboard.blade.php
autonomous: true
requirements: [QUICK-6]
must_haves:
  truths:
    - "Hovering a BUY/SELL signal badge shows tooltip explaining what it means (e.g. Price is 12% below 7-day avg)"
    - "Hovering a trend arrow shows tooltip explaining the price change (e.g. Price changed -2% since last update)"
    - "7-day average price is visible inline on each item in both grid and list views"
  artifacts:
    - path: "resources/views/livewire/pages/dashboard.blade.php"
      provides: "Tooltips on signals/trends and inline 7-day avg display"
  key_links: []
---

<objective>
Add hover tooltips to signal badges and trend arrows on the dashboard, and display the 7-day average price inline on each item card/row.

Purpose: Users currently see signals like "BUY -12%" but have no context about what price the signal compares against. Tooltips explain signal meaning, and showing the 7-day avg inline gives users the reference price at a glance.
Output: Updated dashboard template with tooltips and inline 7-day avg.
</objective>

<execution_context>
@/Users/lancethompson/.claude/get-shit-done/workflows/execute-plan.md
@/Users/lancethompson/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@resources/views/livewire/pages/dashboard.blade.php
@app/Concerns/FormatsAuctionData.php

Key data available per item:
- `$sig = $item->_signal` has keys: `signal` (buy|sell|none|insufficient_data), `magnitude` (float), `rollingAvg` (int, copper value)
- `$this->trendDirection($item)` returns up|down|flat
- `$this->trendPercent($item)` returns float|null (e.g. -2.1)
- `$this->formatGold(int $copper)` returns formatted string like "1,234g 56s 78c"
- Dashboard has two view modes: grid (cards) and list (table), each grouped by profession
</context>

<tasks>

<task type="auto">
  <name>Task 1: Add tooltips to signal badges and trend arrows in both views</name>
  <files>resources/views/livewire/pages/dashboard.blade.php</files>
  <action>
In the dashboard blade template, make the following changes to BOTH grid view and list view sections:

1. **Signal badge tooltips** — Add a `title` attribute to each signal badge `<span>`:
   - BUY badge: `title="Price is {{ $sig['magnitude'] }}% below the 7-day average ({{ $this->formatGold($sig['rollingAvg']) }})"`
   - SELL badge: `title="Price is {{ $sig['magnitude'] }}% above the 7-day average ({{ $this->formatGold($sig['rollingAvg']) }})"`
   - "Collecting data" badge: `title="Need at least 24 snapshots over 7 days to calculate signal"`

2. **Trend arrow tooltips** — Add a `title` attribute to the trend `<span>` that wraps the arrow SVG and percentage:
   - When `$pct !== null`: `title="Price changed {{ $pct > 0 ? '+' : '' }}{{ $pct }}% since last update"`
   - When `$pct === null` (flat, no data): `title="No price change since last update"`

3. **Inline 7-day average price** — Show the rolling average below the current price in both views:
   - In **grid view**: After the current price `<div class="text-lg font-semibold">` block (around line 234-244), add a new line showing the 7-day avg ONLY when `$sig['rollingAvg'] > 0`:
     ```blade
     @if ($sig['rollingAvg'] > 0)
         <div class="mt-1 text-xs text-gray-400">
             7d avg: <span class="text-gray-300">{{ $this->formatGold($sig['rollingAvg']) }}</span>
         </div>
     @endif
     ```
   - In **list view**: Add a new column header "7d Avg" after the "Price" column header. Add a corresponding `<td>` in each row that shows the formatted rolling average when available, or an em dash when not:
     ```blade
     <td class="px-4 py-3 text-sm text-gray-400">
         @if ($sig['rollingAvg'] > 0)
             {{ $this->formatGold($sig['rollingAvg']) }}
         @else
             <span class="italic text-gray-500">—</span>
         @endif
     </td>
     ```

There are exactly 2 locations for signal badges (grid ~line 190-202, list ~line 346-358), 2 locations for trend arrows (grid ~line 210-221, list ~line 331-342), 2 locations for price display (grid ~line 228-244, list ~line 309-322). Update all of them.
  </action>
  <verify>
    <automated>cd /Users/lancethompson/Github/wow-ah-tracker && php artisan view:cache 2>&1 | tail -5 && grep -c 'title="Price is' resources/views/livewire/pages/dashboard.blade.php && grep -c 'title="Price changed' resources/views/livewire/pages/dashboard.blade.php && grep -c '7d avg' resources/views/livewire/pages/dashboard.blade.php</automated>
  </verify>
  <done>
    - All BUY/SELL/collecting-data signal badges have descriptive title tooltips in both grid and list views (4 signal badges total)
    - All trend arrow spans have descriptive title tooltips in both views (2 trend spans total)
    - 7-day average price shown inline below current price in grid view, and as a separate column in list view
    - Views compile without errors
  </done>
</task>

</tasks>

<verification>
- `php artisan view:cache` compiles without errors
- Signal badge title attributes contain magnitude and formatted rolling avg
- Trend arrow title attributes contain percentage change description
- 7-day avg displays in grid view below price and in list view as its own column
- No display when rollingAvg is 0 (insufficient data)
</verification>

<success_criteria>
- Hovering any BUY/SELL badge shows a human-readable explanation including the 7-day average price
- Hovering any trend arrow shows the percentage change description
- The 7-day average price is visible at a glance without hovering, in both grid and list views
- Template compiles and renders without errors
</success_criteria>

<output>
After completion, create `.planning/quick/6-add-tooltips-and-inline-7-day-avg-to-das/6-SUMMARY.md`
</output>
