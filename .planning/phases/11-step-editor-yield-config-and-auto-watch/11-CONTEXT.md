# Phase 11: Step Editor, Yield Config, and Auto-Watch - Context

**Gathered:** 2026-03-04
**Status:** Ready for planning

<domain>
## Phase Boundary

Users can build multi-step conversion chains on the shuffle detail page, configure fixed or variable yield ratios per step, reorder steps, and have all items auto-watched for price polling. The batch calculator and profit summary are Phase 12.

</domain>

<decisions>
## Implementation Decisions

### Item selection UX
- Search-as-you-type for both input and output items, reusing the same CatalogItem search pattern from the watchlist page
- When adding a new step, the output of the previous step auto-fills as the new step's input (editable)
- "Add Step" button below the step list to append a new step (not an always-visible blank row)
- Each step saves individually (inline save), consistent with the inline rename pattern on the detail page

### Step list layout & reordering
- Vertical card layout with arrow/connector between cards showing chain flow (input → output per card)
- WoW item icons (from CatalogItem `icon_url`) displayed alongside item names on each step card
- Up/down arrow buttons for reordering steps (no drag-and-drop / JS library)
- Each step has a delete button — removing a step auto-renumbers sort_order, no confirmation prompt
- Chain connections are by sort_order position, not linked input/output references

### Yield input design
- Single number field by default (fixed yield), with a toggle or "Set range" link to expand to min/max fields
- When min = max, yield is fixed; when different, yield is a range
- Label format: "Yield: X" (fixed) or "Yield: X-Y" (range) — simple and scannable
- Basic validation: min ≥ 1, max ≥ min; inline error shown, prevents saving invalid yields
- **New `input_qty` column needed** — add unsigned integer column to `shuffle_steps` so users can express ratios like "5 ore → 1 gem" (input_qty=5, output_qty_min=1). Default value: 1

### Auto-watch behavior
- Auto-watch happens silently when a step is saved — no toast or notification
- Subtle indicator on step cards showing items are being watched (small badge or checkmark)
- Orphan cleanup runs on step deletion too (same logic as shuffle delete) — remove auto-watched items not used by any remaining step in any shuffle
- Auto-watched items get null thresholds — price tracking only, no buy/sell signals until user manually configures
- New items not yet in CatalogItem table: just store the blizzard_item_id, let the next 15-minute poll cycle populate item data

### Claude's Discretion
- Exact card styling, spacing, and arrow connector design between step cards
- Search dropdown positioning and styling
- Loading states during save/delete operations
- How the "Set range" toggle is presented (link, checkbox, icon)
- Error state handling for failed saves

</decisions>

<specifics>
## Specific Ideas

- Step cards should feel consistent with the existing WoW dark theme — gold accents, dark backgrounds, same visual language as the rest of the app
- The chain flow visualization (cards + arrows) should make it immediately clear that steps form a sequential conversion pipeline
- Input quantity field makes WoW-specific ratios intuitive (e.g., "prospect 5 ore to get 1-3 gems")

</specifics>

<code_context>
## Existing Code Insights

### Reusable Assets
- `CatalogItem` search pattern from `watchlist.blade.php`: `CatalogItem::where('name', 'like', "%{$search}%")` with `icon_url`, `quality_tier`, `rarity` — reuse for step item selection
- `ShuffleStep` model: Already has `input_blizzard_item_id`, `output_blizzard_item_id`, `output_qty_min`, `output_qty_max`, `sort_order` with relationships to `CatalogItem`
- `Shuffle` model: Has `steps()` relationship ordered by `sort_order`, orphan cleanup logic in `deleting` boot event
- `FormatsAuctionData` concern: Gold/silver/copper formatting — reuse if showing any price info on step cards
- `shuffle-detail.blade.php`: Existing shell page with placeholder "Steps" section (lines 121-134) ready to be replaced with the step editor
- `x-modal` component: Available for any confirmation dialogs if needed

### Established Patterns
- Volt SFC pages with `#[Layout('layouts.app')]`
- `#[Computed]` attribute for derived data
- Inline editing with Alpine.js `x-data` / `x-model` / `@keydown.enter` (used for shuffle rename)
- `$wire` calls from Alpine to Livewire for server actions
- `firstOrCreate` pattern for watchlist items

### Integration Points
- `shuffle-detail.blade.php`: Replace step editor placeholder with full step editor UI
- `shuffle_steps` table: New migration to add `input_qty` unsigned integer column (default 1)
- `ShuffleStep` model: Add `input_qty` to `$fillable` and `$casts`
- `WatchedItem`: Auto-watch logic using `firstOrCreate` with `created_by_shuffle_id` provenance
- `Shuffle` model: Extend orphan cleanup to also trigger on individual step deletion

</code_context>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 11-step-editor-yield-config-and-auto-watch*
*Context gathered: 2026-03-04*
