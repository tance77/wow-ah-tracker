---
phase: quick-4
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - resources/views/livewire/pages/item-detail.blade.php
autonomous: true
requirements: [QUICK-4]
must_haves:
  truths:
    - "Every stat card on the item page has a short description explaining what it means"
    - "Descriptions match the existing style used by Distance to Buy, Distance to Sell, and 7d Volatility"
  artifacts:
    - path: "resources/views/livewire/pages/item-detail.blade.php"
      provides: "All item page stat cards with descriptions"
      contains: "text-xs text-gray-500"
  key_links: []
---

<objective>
Add helpful descriptions to all remaining stat cards on the item detail page.

Purpose: Users see 12 stat cards but only 3 have descriptions. New or casual users may not understand what "Current Median" vs "Current Min" means, or what volume metrics indicate. Adding short descriptions to all cards makes the page self-documenting.

Output: Updated item-detail.blade.php with descriptions on all 9 remaining cards.
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
  <name>Task 1: Add descriptions to all remaining stat cards</name>
  <files>resources/views/livewire/pages/item-detail.blade.php</files>
  <action>
Add a description line to each of the 9 stat cards that currently lack one. Use the same pattern as the existing descriptions: a `div` with classes `mt-1 text-xs text-gray-500` placed after the value div.

Cards and their descriptions (placed after each card's value div):

**Price Row (lines ~286-305):**

1. **Current Median** (after line 288):
   `The middle price of all current auctions. Half are listed above, half below.`

2. **Current Min** (after line 292):
   `The cheapest listing on the auction house right now.`

3. **7-Day Average** (after line 296):
   `Average median price over the last 7 days. Used as the baseline for buy/sell signals.`

**Range Row (lines ~308-351):**

4. **7-Day Low / High** (after the value, inside the card div, ~line 317):
   `The cheapest min and highest median seen in the last 7 days.`

5. **30-Day Low / High** (after the value, inside the card div, ~line 326):
   `The cheapest min and highest median seen in the last 30 days.`

(Distance to Buy and Distance to Sell already have descriptions -- skip these.)

**Volume Row (lines ~354-373):**

6. **Current Volume** (after line 357):
   `Total number of auctions currently listed for this item.`

7. **7-Day Avg Volume** (after line 361):
   `Average number of listings per snapshot over the last 7 days.`

8. **24h Volume Change** (after line 367):
   `How much listing volume changed compared to the previous 24 hours.`

9. **30-Day Avg Volume** (after line 371):
   `Average number of listings per snapshot over the last 30 days. Compare to 7-day to spot trends.`

Each description should be a single `<div>` element:
```blade
<div class="mt-1 text-xs text-gray-500">Description text here.</div>
```

This matches the existing pattern used by the 7d Volatility card (line 303).
  </action>
  <verify>
    <automated>grep -c 'mt-1 text-xs text-gray-500' resources/views/livewire/pages/item-detail.blade.php | grep -q '1[2-5]' && echo "PASS: All cards have descriptions" || echo "FAIL: Expected 12+ description lines"</automated>
  </verify>
  <done>All 12 stat cards (3 existing + 9 new) have descriptive text in gray-500 style beneath their values. Descriptions are concise, helpful, and consistent in tone.</done>
</task>

</tasks>

<verification>
- All stat cards on the item page have a description line
- Descriptions use consistent styling: `mt-1 text-xs text-gray-500`
- No Blade syntax errors (page loads without error)
</verification>

<success_criteria>
- 9 new descriptions added to item-detail.blade.php
- Existing 3 descriptions (Distance to Buy, Distance to Sell, 7d Volatility) unchanged
- All descriptions are concise (1 sentence) and explain the stat in plain language
</success_criteria>

<output>
After completion, create `.planning/quick/4-add-descriptions-to-all-remaining-item-p/4-SUMMARY.md`
</output>
