---
phase: quick-3
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - resources/views/livewire/pages/item-detail.blade.php
autonomous: true
requirements: [QUICK-3]
must_haves:
  truths:
    - "Price, 7d average, and threshold are each on their own visual line or clearly separated"
    - "Gold amounts are full opacity and easy to read"
    - "Signal bar looks good on both desktop and mobile widths"
  artifacts:
    - path: "resources/views/livewire/pages/item-detail.blade.php"
      provides: "Redesigned signal status bar with readable breakdown"
      contains: "signal-pulse-buy"
  key_links: []
---

<objective>
Improve readability of the buy/sell signal alert bar on the item detail page by breaking the cramped "Price X vs 7d avg Y (threshold Z%)" single line into a structured layout where each value is clearly labeled and easy to scan.

Purpose: The current layout jams three gold-formatted values into one small, reduced-opacity line that is hard to parse quickly, especially on mobile.
Output: Updated signal status bar in item-detail.blade.php
</objective>

<execution_context>
@/Users/lancethompson/.claude/get-shit-done/workflows/execute-plan.md
@/Users/lancethompson/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@resources/views/livewire/pages/item-detail.blade.php (lines 248-279 — signal status bar)
</context>

<tasks>

<task type="auto">
  <name>Task 1: Redesign signal alert bar layout for readability</name>
  <files>resources/views/livewire/pages/item-detail.blade.php</files>
  <action>
Replace the signal status bar section (lines 256-273, the buy and sell @if/@elseif blocks) with an improved layout. Keep the outer container div (lines 250-255) and the insufficient_data/none cases unchanged.

For both buy and sell signal blocks, replace the current single-line flex layout with this structure:

1. Top row: Keep the signal badge (signal-pulse-buy / signal-pulse-sell) on the left with its current classes
2. Below or to the right (responsive): Show three labeled value pairs stacked vertically in a compact grid

New layout for the buy block (sell block follows identical pattern with red colors and sell_threshold):

```blade
<div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
    <span class="signal-pulse-buy">BUY SIGNAL -{{ $signal['magnitude'] }}%</span>
    <div class="flex flex-wrap gap-x-4 gap-y-1 text-sm">
        <span class="text-green-400/60">Price <span class="font-semibold text-green-300">{{ $this->formatGold($this->stats['currentMedian']) }}</span></span>
        <span class="text-green-400/60">7d Avg <span class="font-semibold text-green-300">{{ $this->formatGold($signal['rollingAvg']) }}</span></span>
        <span class="text-green-400/60">Threshold <span class="font-semibold text-green-300">{{ $watchedItem->buy_threshold }}%</span></span>
    </div>
</div>
```

Key changes:
- Labels ("Price", "7d Avg", "Threshold") are subdued (green-400/60 for buy, red-400/60 for sell)
- Values are bright and semibold (green-300 for buy, red-300 for sell) so they pop
- flex-wrap with gap-x-4 gap-y-1 lets values flow naturally on narrow screens
- On mobile: signal badge stacks above the values (flex-col). On sm+: side by side (sm:flex-row)
- Remove "vs" and parentheses — the labels make the meaning clear without connector words

For the sell block, use the same structure but with:
- signal-pulse-sell, SELL SIGNAL +{{ $signal['magnitude'] }}%
- text-red-400/60 for labels, text-red-300 for values
- $watchedItem->sell_threshold instead of buy_threshold
  </action>
  <verify>
    <automated>php artisan view:cache 2>&1 | tail -5</automated>
  </verify>
  <done>Signal alert bar shows Price, 7d Avg, and Threshold as three distinct labeled values with bright value text and subdued labels. Layout stacks cleanly on mobile and sits side-by-side on desktop. No Blade compilation errors.</done>
</task>

</tasks>

<verification>
- `php artisan view:cache` compiles without errors
- Visual check: signal bar values are clearly separated and readable
</verification>

<success_criteria>
- Three values (price, average, threshold) are visually distinct with clear labels
- Gold amounts are bright/semibold, not reduced opacity
- Layout is responsive: stacks on mobile, horizontal on desktop
- Both buy (green) and sell (red) signal variants are updated consistently
</success_criteria>

<output>
After completion, create `.planning/quick/3-improve-readability-of-buy-sell-signal-a/3-SUMMARY.md`
</output>
