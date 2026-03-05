# Phase 12: Batch Calculator and Profit Summary - Context

**Gathered:** 2026-03-04
**Status:** Ready for planning

<domain>
## Phase Boundary

Users can enter an input quantity on the shuffle detail page and immediately see cascading yields, per-step cost/value breakdowns, total profit after 5% AH cut, and break-even input price — all calculated from live AH median prices. Staleness warnings shown for old snapshots. Also refactors the existing `profitPerUnit()` badge to use proper multi-step cascade logic.

</domain>

<decisions>
## Implementation Decisions

### Calculator placement & input UX
- Separate section below the step editor on the shuffle detail page — clean separation between editing steps and calculating profit
- Calculator section hidden entirely when shuffle has no steps — only appears once steps exist
- Default input quantity: 1 (shows per-unit economics, consistent with profitPerUnit badge)
- All calculation done client-side in Alpine.js using prices loaded once on page render — no server round-trips per keystroke
- Prices refresh on page load (or Livewire poll if present)

### Yield calculation approach
- For variable yields (min/max range), show both min and max columns — pessimistic and optimistic profit range
- Cascading compounds ranges: step 1 min feeds step 2 min, step 1 max feeds step 2 max — full pessimistic/optimistic paths
- Cost model: only the first step's input has a real purchase cost (intermediate items are produced, not bought)
- Value model: only the final step's output is valued (sold on AH) — total value = final output qty x final output price x 0.95

### Per-step breakdown display
- Compact table rows: Step | Input (icon + name + qty) | Output (icon + name + qty) | Yield ratio — one row per step
- Item icons shown alongside names in table rows for visual continuity with step editor cards above
- Profit summary section with five rows: Total Cost, Gross Value, AH Cut (5%), Net Profit, Break-even input price
- All monetary values in gold/silver/copper format using existing `formatGold()` method
- Net Profit row colored green when positive, red when negative — consistent with profitability badge pattern
- Other summary rows use neutral gray/white text

### Staleness & edge states
- Staleness warning: amber banner at top of calculator section listing items with stale prices (>1 hour since last snapshot) and how long ago
- Missing prices (no snapshots for an item): show "--" for missing prices, profit summary shows "Cannot calculate — missing prices for: [items]" — calculator still shows yield quantities but no monetary values
- Refactor `profitPerUnit()` on Shuffle model to use proper multi-step cascade logic matching the calculator — badge and calculator always agree, badge shows conservative (min) profit

### Claude's Discretion
- Exact table styling, column widths, and responsive behavior
- How prices are passed from PHP/Livewire to Alpine (JSON prop, Blade data attributes, etc.)
- Loading state while prices are being fetched
- Break-even calculation formula details
- Whether to show "per unit" or "per batch" labels

</decisions>

<specifics>
## Specific Ideas

- Calculator should feel data-dense and scannable, like WoW addon aesthetics (TSM, Auctioneer)
- The compact table + profit summary layout keeps everything in view without scrolling for typical 2-3 step shuffles
- Break-even is particularly useful: "What's the most I can pay per ore and still profit?"

</specifics>

<code_context>
## Existing Code Insights

### Reusable Assets
- `FormatsAuctionData` trait: `formatGold()` method for gold/silver/copper display — reuse in calculator
- `Shuffle::profitPerUnit()`: Existing naive profit calculation — will be refactored to proper multi-step cascade
- `PriceSnapshot` model: Has `median_price`, `polled_at` fields — used for staleness check and price lookup
- `CatalogItem` model: Links `blizzard_item_id` to item metadata (name, icon_url) and has `priceSnapshots()` relationship
- `ShuffleStep` model: Has `input_qty`, `output_qty_min`, `output_qty_max`, `inputCatalogItem()`, `outputCatalogItem()` relationships
- Step editor Alpine patterns: `x-data`, `x-model`, `$wire` calls — reuse for calculator input

### Established Patterns
- Volt SFC pages with `#[Layout('layouts.app')]`
- `#[Computed]` attribute for derived data on Livewire components
- Alpine.js for client-side interactivity (`x-data`, `x-model`, `@input`)
- WoW dark theme: `bg-wow-dark`, `bg-wow-darker`, `text-wow-gold`, gold/amber accents
- Green for positive profit, red for negative (badge pattern)
- `CatalogItem::priceSnapshots()` with `latest('polled_at')` for most recent price

### Integration Points
- `shuffle-detail.blade.php`: Add new calculator section below step editor (after line ~617 where step editor ends)
- `Shuffle` model: Refactor `profitPerUnit()` for proper multi-step cascade
- `shuffles.blade.php`: Profitability badge on list page will automatically use updated `profitPerUnit()`
- Prices passed to Alpine as JSON data for client-side calculation

</code_context>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 12-batch-calculator-and-profit-summary*
*Context gathered: 2026-03-04*
