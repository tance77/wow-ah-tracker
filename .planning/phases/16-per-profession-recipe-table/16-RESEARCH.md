# Phase 16: Per-Profession Recipe Table - Research

**Researched:** 2026-03-05
**Domain:** Livewire Volt SFC + Alpine.js client-side table (sorting, filtering, accordion expansion)
**Confidence:** HIGH

## Summary

This phase builds a recipe table inside the existing `crafting-detail.blade.php` Volt SFC shell. The data pipeline is already complete: `RecipeProfitAction` computes all required columns (reagent cost, T1/T2 profit, median profit, missing-price flag), and the eager-loading pattern is established in `crafting.blade.php`. The route (`/crafting/{profession}` with slug binding) and Profession model binding already work.

The implementation is entirely frontend rendering with Alpine.js for client-side sort, filter, and accordion expansion. Recipe counts per profession are under 200, so no pagination or server-side sorting is needed. All profit data should be computed once in a `#[Computed]` property and passed to Alpine via `@js()`, matching the `batchCalculator` pattern already used in shuffle-detail.

**Primary recommendation:** Compute all recipe profit data server-side in a `#[Computed]` property, pass as JSON to an Alpine `x-data` component that handles sort/filter/expand entirely client-side.

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions
- Expandable row (accordion style -- only one open at a time) for reagent breakdown
- Click row or chevron to expand inline sub-table of reagents
- Each reagent line shows: quantity, unit price, and subtotal (e.g. "2x Resonance Crystal @ 20g = 40g")
- Reagents only -- no sell prices in the expansion
- Client-side sorting via Alpine.js (recipe count per profession is <200)
- All data columns sortable: recipe name, reagent cost, T1 profit, T2 profit, median profit
- Default sort: median profit descending
- Click toggles asc/desc with up/down arrow indicator on active column
- Simple text filter box above the table for searching recipes by name
- Missing-price recipes: inline amber/yellow warning badge or icon next to recipe name; profit columns show em dash (--)
- Missing-price and non-commodity recipes sort to the bottom regardless of active sort column
- Staleness warning: single amber banner above the table when any price snapshot is > 1 hour old
- No per-row staleness indicators
- Non-commodity rows displayed inline, reagent cost still populated, profit columns show "Realm AH -- not tracked" spanning profit cells
- Non-commodity rows slightly dimmed
- Sort to bottom with missing-price rows
- No toggle to hide non-commodity rows

### Claude's Discretion
- Exact table styling, spacing, and typography
- Loading skeleton / spinner design
- Chevron icon style for expandable rows
- Banner styling and dismiss behavior for staleness warning
- Mobile responsive layout for table (horizontal scroll vs stacked cards)

### Deferred Ideas (OUT OF SCOPE)
None
</user_constraints>

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| TABLE-01 | Per-profession page shows all recipes in a sortable table (default: median profit desc) | Alpine.js `x-data` with `sortBy`/`sortDir` state; `#[Computed]` provides recipe data array; existing shell at `crafting-detail.blade.php` |
| TABLE-02 | Table columns: recipe name, reagent cost, T1 profit, T2 profit, median profit | `RecipeProfitAction` already returns all these fields; `formatGold()` trait available for display |
| TABLE-03 | Recipes with missing price data flagged with indicator | `RecipeProfitAction.has_missing_prices` boolean; amber badge next to name, em dash in profit columns |
| TABLE-04 | Stale price warning when any snapshot > 1 hour old | Check `polled_at` on eager-loaded price snapshots server-side; pass staleness flag to Alpine |
| TABLE-05 | Per-reagent cost breakdown on expand | Accordion via Alpine `expandedRow` state; reagent data from eager-loaded `reagents.catalogItem.priceSnapshots` |
| TABLE-06 | Non-commodity recipes show "realm AH -- not tracked" | `Recipe.is_commodity` boolean; conditional rendering in profit columns with colspan |
</phase_requirements>

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| Livewire Volt | 4.x (bundled) | SFC component for data hydration | Already used for all pages in project |
| Alpine.js | 3.x (bundled by Livewire) | Client-side sort, filter, accordion | Already used in shuffle-detail, modals, navigation |
| Tailwind CSS | 4.x | Table styling with WoW theme | Project standard, custom colors defined |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| `RecipeProfitAction` | existing | Compute profit per recipe | Called in `#[Computed]` for all profession recipes |
| `FormatsAuctionData` trait | existing | `formatGold()` copper-to-display | Used in Blade for server-rendered values or via Alpine JS helper |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Alpine.js client-side sort | Livewire server-side sort | Unnecessary HTTP round-trips for <200 rows; Alpine is faster |
| Custom table | DataTables/AG-Grid | Massive overkill for <200 rows; Alpine keeps it simple |

## Architecture Patterns

### Recommended Data Flow
```
Livewire #[Computed] (server)
  -> Profession->recipes eager loaded with prices
  -> RecipeProfitAction called per recipe
  -> Array of recipe data built with all display fields
  -> Staleness computed from polled_at timestamps
  -> Passed to Blade via @js()

Alpine x-data (client)
  -> Receives recipes array + staleness info
  -> sortBy / sortDir / searchQuery / expandedRow state
  -> Computed sorted+filtered list with bottom-sort for missing/non-commodity
  -> Renders table rows reactively
```

### Pattern 1: Server-Side Data Preparation
**What:** Single `#[Computed]` property that builds the full recipe dataset
**When to use:** Always -- compute once, pass to Alpine
**Example:**
```php
// crafting-detail.blade.php (Volt SFC)
#[Computed]
public function recipeData(): array
{
    $this->profession->load([
        'recipes.reagents.catalogItem.priceSnapshots' => fn ($q) => $q->latest('polled_at')->limit(1),
        'recipes.craftedItemSilver.priceSnapshots' => fn ($q) => $q->latest('polled_at')->limit(1),
        'recipes.craftedItemGold.priceSnapshots' => fn ($q) => $q->latest('polled_at')->limit(1),
    ]);

    $action = new RecipeProfitAction();
    $oldestPolledAt = null;

    $recipes = $this->profession->recipes->map(function ($recipe) use ($action, &$oldestPolledAt) {
        $profit = $action($recipe);

        // Track oldest polled_at for staleness banner
        foreach ($recipe->reagents as $reagent) {
            $polledAt = $reagent->catalogItem?->priceSnapshots->first()?->polled_at;
            if ($polledAt && ($oldestPolledAt === null || $polledAt->lt($oldestPolledAt))) {
                $oldestPolledAt = $polledAt;
            }
        }
        // Also check crafted item snapshots
        foreach (['craftedItemSilver', 'craftedItemGold'] as $rel) {
            $polledAt = $recipe->$rel?->priceSnapshots->first()?->polled_at;
            if ($polledAt && ($oldestPolledAt === null || $polledAt->lt($oldestPolledAt))) {
                $oldestPolledAt = $polledAt;
            }
        }

        // Build reagent breakdown for expansion
        $reagents = $recipe->reagents->map(fn ($r) => [
            'name' => $r->catalogItem?->display_name ?? 'Unknown',
            'quantity' => $r->quantity,
            'unit_price' => $r->catalogItem?->priceSnapshots->first()?->median_price,
            'subtotal' => $r->catalogItem?->priceSnapshots->first()
                ? $r->quantity * $r->catalogItem->priceSnapshots->first()->median_price
                : null,
        ])->all();

        return [
            'id' => $recipe->id,
            'name' => $recipe->name,
            'is_commodity' => $recipe->is_commodity,
            'reagent_cost' => $profit['reagent_cost'],
            'profit_silver' => $profit['profit_silver'],
            'profit_gold' => $profit['profit_gold'],
            'median_profit' => $profit['median_profit'],
            'has_missing_prices' => $profit['has_missing_prices'],
            'reagents' => $reagents,
        ];
    })->all();

    $staleMinutes = $oldestPolledAt ? (int) $oldestPolledAt->diffInMinutes(now()) : null;

    return [
        'recipes' => $recipes,
        'stale' => $staleMinutes !== null && $staleMinutes > 60,
        'stale_minutes' => $staleMinutes,
    ];
}
```

### Pattern 2: Alpine.js Sort + Filter + Accordion
**What:** Client-side data manipulation with Alpine reactive state
**When to use:** For the table interactivity
**Example:**
```javascript
// Inside x-data
{
    recipes: [],       // Set from @js
    sortBy: 'median_profit',
    sortDir: 'desc',
    searchQuery: '',
    expandedRow: null, // Only one open at a time

    // Gold formatting (mirrors PHP formatGold)
    formatGold(copper) {
        if (copper === null) return '—';
        const neg = copper < 0;
        copper = Math.abs(copper);
        const g = Math.floor(copper / 10000);
        const s = Math.floor((copper % 10000) / 100);
        const c = copper % 100;
        let parts = [];
        if (g > 0) parts.push(g.toLocaleString() + 'g');
        if (s > 0) parts.push(s + 's');
        if (c > 0 || parts.length === 0) parts.push(c + 'c');
        return (neg ? '-' : '') + parts.join(' ');
    },

    get sortedRecipes() {
        let filtered = this.recipes.filter(r =>
            r.name.toLowerCase().includes(this.searchQuery.toLowerCase())
        );

        // Partition: normal, then missing/non-commodity at bottom
        const normal = filtered.filter(r => r.is_commodity && !r.has_missing_prices);
        const bottom = filtered.filter(r => !r.is_commodity || r.has_missing_prices);

        const dir = this.sortDir === 'asc' ? 1 : -1;
        const sorter = (a, b) => {
            let av = a[this.sortBy], bv = b[this.sortBy];
            if (this.sortBy === 'name') return dir * av.localeCompare(bv);
            if (av === null) return 1;
            if (bv === null) return -1;
            return dir * (av - bv);
        };

        normal.sort(sorter);
        bottom.sort(sorter);
        return [...normal, ...bottom];
    },

    toggleSort(col) {
        if (this.sortBy === col) {
            this.sortDir = this.sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            this.sortBy = col;
            this.sortDir = col === 'name' ? 'asc' : 'desc';
        }
    },

    toggleExpand(id) {
        this.expandedRow = this.expandedRow === id ? null : id;
    }
}
```

### Pattern 3: Staleness Banner
**What:** Single amber banner above table when any price is stale
**When to use:** Computed server-side, rendered conditionally
**Example:**
```html
<!-- Matches existing staleness pattern from shuffle-detail -->
<div x-show="stale" x-cloak
     class="mb-4 rounded-md border border-amber-700/50 bg-amber-900/20 px-4 py-3">
    <div class="flex items-start gap-2">
        <svg class="mt-0.5 h-4 w-4 shrink-0 text-amber-400" ...warning icon...></svg>
        <span class="text-xs text-amber-300">
            Price data may be stale -- last updated <span x-text="staleMinutes"></span> minutes ago
        </span>
    </div>
</div>
```

### Anti-Patterns to Avoid
- **Server round-trips for sorting:** Do NOT use Livewire wire:click for sort toggles. Alpine handles this instantly client-side.
- **Per-row staleness checks:** All recipes share the same polling cycle. One banner, not per-row indicators.
- **Separate API call for reagent breakdown:** All reagent data is already eager-loaded. Pass it in the initial JSON payload, no lazy loading needed.
- **Multiple expanded rows:** Only one accordion open at a time. Toggling a new row closes the previous.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Gold formatting in JS | New JS formatter | Port `formatGold()` to JS helper | Already exists in PHP, just mirror the logic |
| Profit calculation | New JS profit calc | `RecipeProfitAction` on server | All computation happens server-side, only display in JS |
| Sort indicator arrows | Custom SVG management | Simple inline SVG chevron up/down | Two small SVGs, no library needed |
| Table pagination | Paginator component | No pagination needed | <200 rows per profession |

## Common Pitfalls

### Pitfall 1: N+1 Query on Reagent Price Snapshots
**What goes wrong:** Each recipe's reagents trigger separate queries for catalog items and price snapshots
**Why it happens:** Missing eager loading on the profession's recipes relationship chain
**How to avoid:** Use the exact eager-load pattern from `crafting.blade.php`:
```php
$this->profession->load([
    'recipes.reagents.catalogItem.priceSnapshots' => fn ($q) => $q->latest('polled_at')->limit(1),
    'recipes.craftedItemSilver.priceSnapshots' => fn ($q) => $q->latest('polled_at')->limit(1),
    'recipes.craftedItemGold.priceSnapshots' => fn ($q) => $q->latest('polled_at')->limit(1),
]);
```
**Warning signs:** Slow page load, many SQL queries in debug bar

### Pitfall 2: formatGold JS/PHP Mismatch
**What goes wrong:** JavaScript gold formatter produces different output than PHP `formatGold()` for edge cases (zero, negative values, copper-only amounts)
**Why it happens:** Reimplementing the logic without matching all branches
**How to avoid:** Test both formatters with same inputs: 0, 100 (1s), 10000 (1g), -15050 (-1g 50s 50c), 50 (50c)
**Warning signs:** Values look different between overview page and detail table

### Pitfall 3: Sort-to-Bottom Logic Breaks on Column Toggle
**What goes wrong:** Missing-price and non-commodity rows interleave with normal rows when changing sort columns
**Why it happens:** Sorting the full array without partitioning first
**How to avoid:** Always partition into [normal, bottom] arrays, sort each independently, then concatenate
**Warning signs:** "Realm AH -- not tracked" rows appear in the middle of the table

### Pitfall 4: Accordion State Lost on Sort
**What goes wrong:** Expanding a row, then sorting causes the expanded row to jump position but the expansion panel stays visually detached
**Why it happens:** Expansion is tied to DOM position rather than recipe ID
**How to avoid:** Track `expandedRow` by recipe ID, not array index. Alpine's `x-for` with `:key="recipe.id"` handles this correctly.

### Pitfall 5: Non-Commodity Profit Column Spanning
**What goes wrong:** "Realm AH -- not tracked" text doesn't span across T1/T2/median columns properly
**Why it happens:** `colspan` on `<td>` requires the adjacent `<td>` elements to be omitted
**How to avoid:** Use `x-if` to conditionally render either the 3 profit columns OR a single spanned column based on `is_commodity`

## Code Examples

### Gold Formatting in JavaScript (mirroring PHP)
```javascript
// Must match app/Concerns/FormatsAuctionData.php formatGold()
formatGold(copper) {
    if (copper === null || copper === undefined) return '—';
    const neg = copper < 0;
    copper = Math.abs(copper);
    const g = Math.floor(copper / 10000);
    const s = Math.floor((copper % 10000) / 100);
    const c = copper % 100;
    let parts = [];
    if (g > 0) parts.push(g.toLocaleString() + 'g');
    if (s > 0) parts.push(s + 's');
    if (c > 0 || parts.length === 0) parts.push(c + 'c');
    return (neg ? '-' : '') + parts.join(' ');
}
```

### Table Row with Conditional Profit/Non-Commodity Display
```html
<template x-for="recipe in sortedRecipes" :key="recipe.id">
    <tbody>
        <tr @click="toggleExpand(recipe.id)"
            class="cursor-pointer border-b border-gray-700/40 transition-colors hover:bg-wow-darker/50"
            :class="{ 'opacity-50': !recipe.is_commodity }">
            <!-- Name + warning badge -->
            <td class="py-3 pr-3">
                <div class="flex items-center gap-2">
                    <!-- Chevron -->
                    <svg :class="{ 'rotate-90': expandedRow === recipe.id }"
                         class="h-4 w-4 text-gray-500 transition-transform" ...></svg>
                    <span class="text-gray-200" x-text="recipe.name"></span>
                    <template x-if="recipe.has_missing_prices">
                        <span class="rounded bg-amber-900/40 px-1.5 py-0.5 text-xs text-amber-400">
                            missing prices
                        </span>
                    </template>
                </div>
            </td>
            <!-- Reagent cost (always shown) -->
            <td class="py-3 pr-3 text-right text-sm text-gray-300"
                x-text="recipe.reagent_cost !== null ? formatGold(recipe.reagent_cost) : '—'"></td>
            <!-- Profit columns: conditional on is_commodity -->
            <template x-if="recipe.is_commodity">
                <!-- T1 profit -->
                <td class="py-3 pr-3 text-right text-sm"
                    :class="recipe.profit_silver > 0 ? 'text-green-400' : recipe.profit_silver < 0 ? 'text-red-400' : 'text-gray-500'"
                    x-text="recipe.profit_silver !== null ? formatGold(recipe.profit_silver) : '—'"></td>
            </template>
            <!-- ... T2, median similarly -->
            <template x-if="!recipe.is_commodity">
                <td colspan="3" class="py-3 text-center text-xs italic text-gray-500">
                    Realm AH — not tracked
                </td>
            </template>
        </tr>
        <!-- Expansion row -->
        <template x-if="expandedRow === recipe.id">
            <tr class="bg-wow-darker/30">
                <td colspan="5" class="px-8 py-3">
                    <!-- Reagent sub-table -->
                </td>
            </tr>
        </template>
    </tbody>
</template>
```

### Sortable Column Header
```html
<th @click="toggleSort('median_profit')" class="cursor-pointer select-none pb-2 text-right">
    <span class="inline-flex items-center gap-1">
        Median Profit
        <template x-if="sortBy === 'median_profit'">
            <svg class="h-3 w-3" :class="sortDir === 'asc' ? '' : 'rotate-180'" ...chevron...></svg>
        </template>
    </span>
</th>
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Server-side Livewire sort | Alpine client-side sort | Project decision | No HTTP round-trips for <200 rows |
| DataTable libraries | Vanilla Alpine x-data | Project decision | Zero dependencies, consistent with existing codebase |

## Open Questions

1. **Mobile layout: horizontal scroll vs stacked cards?**
   - What we know: User left this to Claude's discretion
   - Recommendation: Horizontal scroll with `overflow-x-auto` (simpler, matches batch calculator table pattern in shuffle-detail). Stacked cards would require significant duplicate markup.

2. **Dismiss behavior for staleness banner?**
   - What we know: User left this to Claude's discretion
   - Recommendation: No dismiss (always visible while stale). The banner is informational and small. Dismissing could hide important data quality info.

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | Pest (PHPUnit wrapper) |
| Config file | `phpunit.xml` |
| Quick run command | `php artisan test --filter=CraftingDetailTest` |
| Full suite command | `php artisan test` |

### Phase Requirements -> Test Map
| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| TABLE-01 | Sortable table at /crafting/{profession} with all recipes | Feature | `php artisan test --filter=CraftingDetailTest::it_shows_all_recipes_in_table` | No - Wave 0 |
| TABLE-02 | Table columns: name, reagent cost, T1 profit, T2 profit, median profit | Feature | `php artisan test --filter=CraftingDetailTest::it_displays_profit_columns` | No - Wave 0 |
| TABLE-03 | Missing price indicator | Feature | `php artisan test --filter=CraftingDetailTest::it_flags_missing_prices` | No - Wave 0 |
| TABLE-04 | Stale price warning banner | Feature | `php artisan test --filter=CraftingDetailTest::it_shows_staleness_warning` | No - Wave 0 |
| TABLE-05 | Per-reagent cost breakdown (expand) | Feature | `php artisan test --filter=CraftingDetailTest::it_includes_reagent_breakdown_data` | No - Wave 0 |
| TABLE-06 | Non-commodity shows "realm AH -- not tracked" | Feature | `php artisan test --filter=CraftingDetailTest::it_marks_non_commodity_recipes` | No - Wave 0 |

### Sampling Rate
- **Per task commit:** `php artisan test --filter=CraftingDetailTest`
- **Per wave merge:** `php artisan test`
- **Phase gate:** Full suite green before `/gsd:verify-work`

### Wave 0 Gaps
- [ ] `tests/Feature/CraftingDetailTest.php` -- covers TABLE-01 through TABLE-06
- [ ] Reuse `createRecipeWithProfit()` helper from `CraftingOverviewTest.php` (extract to shared helper or duplicate)
- Framework install: Not needed -- Pest already configured

## Sources

### Primary (HIGH confidence)
- Project codebase: `crafting.blade.php`, `crafting-detail.blade.php`, `RecipeProfitAction.php` -- existing patterns
- Project codebase: `shuffle-detail.blade.php` -- Alpine.js x-data + @js() + staleness banner pattern
- Project codebase: `FormatsAuctionData.php` -- formatGold() implementation
- Project codebase: `CraftingOverviewTest.php` -- test pattern with factories and helpers

### Secondary (MEDIUM confidence)
- Alpine.js documentation -- x-for, x-if, computed getters (verified by existing codebase usage)

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH -- everything already exists in the project
- Architecture: HIGH -- mirrors established patterns (shuffle-detail batch calculator)
- Pitfalls: HIGH -- based on actual codebase analysis, known edge cases
- Code examples: HIGH -- adapted directly from existing project code

**Research date:** 2026-03-05
**Valid until:** 2026-04-05 (stable -- no external dependency changes expected)
