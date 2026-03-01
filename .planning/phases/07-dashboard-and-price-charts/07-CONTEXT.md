# Phase 7: Dashboard and Price Charts - Context

**Gathered:** 2026-03-01
**Status:** Ready for planning

<domain>
## Phase Boundary

Replace the placeholder dashboard with a real-time overview of the logged-in user's watched items. Each item gets a summary card with current price and trend. Clicking a card reveals an interactive ApexCharts line chart with timeframe toggles (24h/7d/30d). All prices displayed in WoW gold format. Buy/sell signal indicators are Phase 8 — not in scope here.

</domain>

<decisions>
## Implementation Decisions

### Summary card layout
- Responsive grid: 3 columns on desktop, 2 on tablet, 1 on mobile
- Each card shows: item name, current median price (gold format), colored trend arrow, percentage change
- Trend direction determined by comparing current median to previous snapshot's median
- Clicking a card opens a full-width chart section below the grid (not modal, not inline expand)

### Chart interaction
- Full-width chart section below the card grid — one item's chart visible at a time
- Timeframe toggle is a button group: 24h | 7d | 30d — active button highlighted in gold
- Chart displays two lines: median price and min price
- Hovering data points shows tooltip with timestamp, median, and min price in gold/silver/copper format
- Timeframe toggle updates chart reactively via Livewire without full page reload

### Price formatting
- Format: "Xg Xs Xc" with colored text — gold for gold, silver for silver, copper for copper
- Zero portions hidden (e.g., "5g" not "5g 0s 0c")
- Trend arrows: green up arrow for increase, red down arrow for decrease, gray dash for flat
- Percentage change displayed alongside arrow (e.g., "↑ +3.2%")
- Gold format used consistently on cards, chart tooltips, and chart Y-axis

### Empty and loading states
- No watched items: centered "No items tracked yet" message with CTA button linking to Watchlist page
- Items with no snapshots: card appears in grid with muted "Awaiting first snapshot" message instead of price/trend
- Loading: skeleton placeholder cards while Livewire fetches data
- Data freshness: relative timestamp at top of dashboard ("Updated 12 min ago") using latest snapshot's polled_at

### Claude's Discretion
- Skeleton card design and animation
- Exact spacing, typography, and card border styling within WoW dark theme
- Chart color palette for median vs min lines
- Chart Y-axis scale and tick formatting
- How to handle the transition animation when chart section opens/closes
- Error state handling (failed data fetch)

</decisions>

<specifics>
## Specific Ideas

- Two chart lines (median + min) give snipe buyers visibility into cheapest-available vs market price
- Percentage change on cards gives magnitude context, not just direction
- Gold/silver/copper text should use colors that match the actual WoW coin colors for authenticity

</specifics>

<code_context>
## Existing Code Insights

### Reusable Assets
- `WatchedItem` model: has `priceSnapshots()` HasMany relationship, `buy_threshold`/`sell_threshold` columns (Phase 8 uses these)
- `PriceSnapshot` model: `min_price`, `avg_price`, `median_price`, `total_volume`, `polled_at` — all cast as integers (copper)
- `IngestionMetadata` model: tracks `last_modified_at` — can derive data freshness
- Existing Blade components: `nav-link`, `dropdown`, `modal`, `primary-button`, `secondary-button`, `text-input`
- WoW dark theme established: `bg-wow-dark`, `bg-wow-darker`, `text-wow-gold`, `text-wow-gold-light`, `border-gray-700/50`

### Established Patterns
- Livewire Volt components in `resources/views/livewire/pages/`
- `wire:navigate` for SPA-like navigation between pages
- Tailwind CSS v4 for styling, `@tailwindcss/forms` plugin available
- Alpine.js available (used in nav for dropdown/hamburger)
- Vite for asset bundling

### Integration Points
- Dashboard route already exists at `/dashboard` with `dashboard.blade.php` (placeholder to be replaced)
- Navigation already has "Dashboard" and "Watchlist" links wired up
- `auth()->user()->watchedItems()` relationship already used in current placeholder
- Composite index on `(watched_item_id, polled_at)` exists for efficient time-range queries
- ApexCharts needs to be installed via npm (not currently in package.json)

</code_context>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 07-dashboard-and-price-charts*
*Context gathered: 2026-03-01*
