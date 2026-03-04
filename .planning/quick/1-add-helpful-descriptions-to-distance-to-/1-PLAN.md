---
phase: quick
plan: 1
type: execute
wave: 1
depends_on: []
files_modified:
  - resources/views/livewire/pages/item-detail.blade.php
autonomous: true
requirements: [QUICK-1]
must_haves:
  truths:
    - "User can see a description under Distance to Buy explaining what the number means"
    - "User can see a description under Distance to Sell explaining what the number means"
    - "User can see a description under 7d Volatility explaining what the number means"
  artifacts:
    - path: "resources/views/livewire/pages/item-detail.blade.php"
      provides: "Descriptive help text for all three metrics"
      contains: "how far the current price"
  key_links: []
---

<objective>
Add small descriptive help text below the Distance to Buy, Distance to Sell, and 7-Day Volatility stat cards on the item detail page so users understand what each metric means at a glance.

Purpose: Users are confused by these metrics — especially when Distance to Sell is negative or Distance to Buy is positive. Plain-language descriptions eliminate confusion.
Output: Updated item-detail.blade.php with help text under each of the three metrics.
</objective>

<execution_context>
@/Users/lancethompson/.claude/get-shit-done/workflows/execute-plan.md
@/Users/lancethompson/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@resources/views/livewire/pages/item-detail.blade.php
</context>

<tasks>

<task type="auto">
  <name>Task 1: Add descriptive help text to Distance to Buy, Distance to Sell, and 7d Volatility cards</name>
  <files>resources/views/livewire/pages/item-detail.blade.php</files>
  <action>
Add a small help-text line below the value display in each of these three stat cards. Use `text-xs text-gray-500 mt-1` styling to keep it subtle and consistent with the existing design language.

**7d Volatility card** (around line 298-303):
After the volatility value div, add:
```blade
<div class="mt-1 text-xs text-gray-500">How much the price fluctuates. Under 5% = stable, over 15% = volatile.</div>
```

**Distance to Buy card** (around line 328-332):
After the distanceToBuy value div, add:
```blade
@if ($stats['distanceToBuy'] !== null)
    <div class="mt-1 text-xs text-gray-500">
        {{ $stats['distanceToBuy'] > 0 ? 'Price is ' . $this->formatGold(abs($stats['distanceToBuy'])) . ' above your buy target. Wait for it to drop.' : 'Price is at or below your buy target!' }}
    </div>
@endif
```

**Distance to Sell card** (around line 334-338):
After the distanceToSell value div, add:
```blade
@if ($stats['distanceToSell'] !== null)
    <div class="mt-1 text-xs text-gray-500">
        {{ $stats['distanceToSell'] > 0 ? 'Price needs to rise ' . $this->formatGold(abs($stats['distanceToSell'])) . ' more to hit your sell target.' : 'Price is at or above your sell target!' }}
    </div>
@endif
```

Keep the existing value display, colors, and formatting completely unchanged. Only ADD the new description divs after each value div.
  </action>
  <verify>
    <automated>cd /Users/lancethompson/Github/wow-ah-tracker && php artisan view:cache 2>&1 | head -5 && grep -c "text-xs text-gray-500 mt-1\|mt-1 text-xs text-gray-500" resources/views/livewire/pages/item-detail.blade.php</automated>
  </verify>
  <done>All three stat cards (7d Volatility, Distance to Buy, Distance to Sell) have descriptive help text visible below their values. Views compile without errors. The help text uses plain language explaining what the metric means and what the current value indicates.</done>
</task>

</tasks>

<verification>
- `php artisan view:cache` succeeds (no Blade syntax errors)
- All three metrics have descriptive text below their values
- Text is styled subtly (text-xs text-gray-500) to not overwhelm the card layout
</verification>

<success_criteria>
- Distance to Buy shows contextual text like "Price is Xg above your buy target. Wait for it to drop."
- Distance to Sell shows contextual text like "Price needs to rise Xg more to hit your sell target."
- 7d Volatility shows a brief explanation of what the percentage means
- No existing functionality or styling is broken
</success_criteria>

<output>
After completion, create `.planning/quick/1-add-helpful-descriptions-to-distance-to-/1-SUMMARY.md`
</output>
