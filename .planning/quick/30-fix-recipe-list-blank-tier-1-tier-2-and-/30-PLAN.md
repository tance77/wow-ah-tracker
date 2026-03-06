---
phase: quick-30
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - resources/views/livewire/pages/crafting-detail.blade.php
autonomous: true
requirements: [quick-30]
must_haves:
  truths:
    - "Commodity recipes show Tier 1, Tier 2, and Median Profit values in the table"
    - "Non-commodity recipes show 'Realm AH - not tracked' spanning the profit columns"
    - "Table has consistent 5-column layout for all rows"
  artifacts:
    - path: "resources/views/livewire/pages/crafting-detail.blade.php"
      provides: "Recipe table with working profit columns"
  key_links:
    - from: "crafting-detail.blade.php profit <td> cells"
      to: "Alpine recipe.profit_silver / profit_gold / median_profit"
      via: "x-text bindings"
      pattern: "x-text.*formatGold.*profit"
---

<objective>
Fix recipe list Tier 1, Tier 2, and Median Profit columns showing blank.

Purpose: The previous fix (quick-27) replaced `<template x-if>` with `x-show` on `<td>` elements, but the columns are still blank. The root cause is that `x-show` on `<td>` elements creates conflicting DOM structure: commodity rows have 6 `<td>` elements (3 profit + 1 "Realm AH" with colspan=3, hidden), and non-commodity rows have 4 `<td>` elements (3 hidden + 1 colspan=3 visible). This DOM mismatch within the same `<template x-for>` loop causes inconsistent table rendering. The fix is to always render exactly 3 `<td>` elements for the profit columns, using Alpine ternary expressions to switch content based on `is_commodity`.

Output: Working recipe table with profit data visible for commodity items.
</objective>

<execution_context>
@/Users/lancethompson/.claude/get-shit-done/workflows/execute-plan.md
@/Users/lancethompson/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@resources/views/livewire/pages/crafting-detail.blade.php
@app/Actions/RecipeProfitAction.php
</context>

<tasks>

<task type="auto">
  <name>Task 1: Replace conditional td elements with always-rendered cells using inline ternary content</name>
  <files>resources/views/livewire/pages/crafting-detail.blade.php</files>
  <action>
In crafting-detail.blade.php, replace lines 270-281 (the 4 conditional `<td>` elements for profit columns) with exactly 3 unconditional `<td>` elements that always render. Remove the separate "Realm AH" `<td x-show="!recipe.is_commodity" colspan="3">` entirely.

Replace the current pattern:
```
<td x-show="recipe.is_commodity" ...>profit_silver</td>
<td x-show="recipe.is_commodity" ...>profit_gold</td>
<td x-show="recipe.is_commodity" ...>median_profit</td>
<td x-show="!recipe.is_commodity" colspan="3" ...>Realm AH</td>
```

With 3 always-present `<td>` elements. For each profit cell (profit_silver, profit_gold, median_profit):

1. First cell (Tier 1 / profit_silver): When `!recipe.is_commodity`, show the italic gray "Realm AH -- not tracked" text instead. When `is_commodity`, show the profit value with color coding as before (green for positive, red for negative, gray for missing prices showing em dash).

2. Second cell (Tier 2 / profit_gold): When `!recipe.is_commodity`, render empty (the "Realm AH" message is already in cell 1). When `is_commodity`, show profit_gold with same color logic.

3. Third cell (Median Profit / median_profit): Same as second -- empty for non-commodity, profit value for commodity.

For the commodity profit display, preserve the existing color logic:
- `:class` binding: `recipe.has_missing_prices ? 'text-gray-500' : (recipe.profit_silver > 0 ? 'text-green-400' : (recipe.profit_silver < 0 ? 'text-red-400' : 'text-gray-500'))`
- `x-text` binding: `recipe.has_missing_prices ? '\u2014' : formatGold(recipe.profit_silver)` (and similarly for profit_gold, median_profit)

For non-commodity display in the first cell, use:
- `<span x-show="!recipe.is_commodity" class="text-xs italic text-gray-500">Realm AH &mdash; not tracked</span>`

For commodity display in each cell, wrap in:
- `<span x-show="recipe.is_commodity" ...>` with the x-text and :class bindings

This ensures every `<tr>` always has exactly 5 `<td>` elements (name + reagent_cost + 3 profit columns), eliminating DOM structure inconsistency.

Do NOT change: the thead, the name column, the reagent_cost column, the expansion row, the Alpine data/methods, or any other part of the file.
  </action>
  <verify>
    <automated>cd /Users/lancethompson/Github/wow-ah-tracker && php artisan view:cache 2>&1 | head -5</automated>
  </verify>
  <done>All recipe table rows render exactly 5 td elements. Commodity rows show profit values with color coding. Non-commodity rows show "Realm AH -- not tracked" in the first profit cell.</done>
</task>

</tasks>

<verification>
- `php artisan view:cache` compiles without errors
- Inspect the blade template: no `x-show` or `x-if` on `<td>` elements in profit columns
- Every `<tr>` in the x-for loop has exactly 5 `<td>` children (name, reagent_cost, tier1, tier2, median)
</verification>

<success_criteria>
Tier 1, Tier 2, and Median Profit columns render visible values for commodity recipes. Non-commodity recipes show "Realm AH -- not tracked" without breaking table layout.
</success_criteria>

<output>
After completion, create `.planning/quick/30-fix-recipe-list-blank-tier-1-tier-2-and-/30-SUMMARY.md`
</output>
