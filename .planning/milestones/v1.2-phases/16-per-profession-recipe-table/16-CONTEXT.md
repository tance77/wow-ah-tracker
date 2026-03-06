# Phase 16: Per-Profession Recipe Table - Context

**Gathered:** 2026-03-05
**Status:** Ready for planning

<domain>
## Phase Boundary

Users can view all recipes for a single profession in a sortable table at `/crafting/{profession}`, see full profit breakdowns per recipe, expand rows to view per-reagent cost details, and identify recipes with missing or stale price data. Non-commodity gear recipes are displayed with a "realm AH — not tracked" message. The overview page and navigation are Phase 15 (complete).

</domain>

<decisions>
## Implementation Decisions

### Reagent breakdown
- Expandable row (accordion style — only one open at a time)
- Click row or chevron to expand inline sub-table of reagents
- Each reagent line shows: quantity, unit price, and subtotal (e.g. "2x Resonance Crystal @ 20g = 40g")
- Reagents only — no sell prices in the expansion (those are already in the table columns)

### Table sorting
- Client-side sorting via Alpine.js (recipe count per profession is <200, no server round-trips needed)
- All data columns sortable: recipe name, reagent cost, T1 profit, T2 profit, median profit
- Default sort: median profit descending
- Click toggles asc/desc with up/down arrow indicator on active column
- Simple text filter box above the table for searching recipes by name

### Warning display
- Missing-price recipes: inline amber/yellow warning badge or icon next to recipe name; profit columns show em dash (—)
- Missing-price and non-commodity recipes sort to the bottom regardless of active sort column
- Staleness warning: single amber banner above the table when any price snapshot is > 1 hour old ("Price data may be stale — last updated X minutes ago")
- No per-row staleness indicators — all recipes share the same polling cycle

### Non-commodity rows
- Displayed inline in the table (not hidden or separated)
- Reagent cost column still populated (shows material cost even without sell price)
- Profit columns show "Realm AH — not tracked" spanning the profit cells
- Row slightly dimmed to visually distinguish from commodity recipes
- Sort to bottom with missing-price rows
- No toggle to hide — they're clearly marked and at the bottom

### Claude's Discretion
- Exact table styling, spacing, and typography
- Loading skeleton / spinner design
- Chevron icon style for expandable rows
- Banner styling and dismiss behavior for staleness warning
- Mobile responsive layout for table (horizontal scroll vs stacked cards)

</decisions>

<specifics>
## Specific Ideas

No specific requirements — open to standard approaches matching existing WoW dark theme with gold/amber accents.

</specifics>

<code_context>
## Existing Code Insights

### Reusable Assets
- `RecipeProfitAction`: Returns `reagent_cost`, `sell_price_silver/gold`, `profit_silver/gold`, `median_profit`, `has_missing_prices` — all data needed for table columns
- `FormatsAuctionData` trait: `formatGold()` method for copper-to-gold display
- `crafting-detail.blade.php`: Shell Volt SFC already exists with Profession model binding — ready to build on
- `crafting.blade.php`: Existing overview page shows the eager-loading pattern for recipes with price snapshots

### Established Patterns
- Livewire Volt SFC with `#[Layout('layouts.app')]` and `#[Computed]` for reactive data
- Alpine.js for client-side interactivity (used in shuffle batch calculator)
- Card styling: `rounded-lg border border-gray-700/50 bg-wow-dark p-5 transition-colors`
- WoW theme: `bg-wow-dark`, `bg-wow-darker`, `text-wow-gold`, gold/amber accents
- Green for profit (`text-green-400`), red for loss (`text-red-400`)

### Integration Points
- Route already exists: `crafting.show` → `/crafting/{profession}` with slug model binding
- Profession `hasMany` Recipe, Recipe `hasMany` RecipeReagent
- Eager load pattern from crafting.blade.php: `recipes.reagents.catalogItem.priceSnapshots`, `recipes.craftedItemSilver.priceSnapshots`, `recipes.craftedItemGold.priceSnapshots`
- `Recipe.is_commodity` boolean distinguishes commodity vs gear recipes

</code_context>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 16-per-profession-recipe-table*
*Context gathered: 2026-03-05*
