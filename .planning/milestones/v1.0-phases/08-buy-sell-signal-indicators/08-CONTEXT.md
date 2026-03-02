# Phase 8: Buy/Sell Signal Indicators - Context

**Gathered:** 2026-03-01
**Status:** Ready for planning

<domain>
## Phase Boundary

The dashboard surfaces buy and sell opportunities by comparing each item's current median price to the user's configured thresholds against a 7-day rolling average. Clear visual indicators appear on item cards when a threshold is breached, and threshold reference lines appear on the price chart.

</domain>

<decisions>
## Implementation Decisions

### Rolling Average Window
- 7-day rolling average window using median_price from stored snapshots
- Require minimum 24 hours of data (~96 snapshots at 15-min intervals) before showing signals
- Items with insufficient data show "Collecting data" instead of a signal badge
- Use median_price only (not avg_price) — avoids manipulation by single low-quantity listings per success criteria

### Signal Badge Design
- Both: pill badge next to item name AND subtle card border color change
- Green "BUY -X%" pill when current price is below buy threshold relative to rolling average
- Red "SELL +X%" pill when current price is above sell threshold relative to rolling average
- Badge includes magnitude percentage (how far past threshold) for at-a-glance opportunity sizing
- Items at normal price show no signal badge — completely clean cards (matches success criteria)

### Threshold Lines on Chart
- Static dashed horizontal lines at buy and sell threshold levels (rolling average × threshold %)
- Add rolling average as a third line series on the chart alongside median and min
- Dashed green line for buy threshold, dashed red line for sell threshold
- Lines only appear when viewing an item with configured thresholds

### Signal Priority & Sorting
- Items with active signals sorted to the top of the dashboard card grid
- Within signaled items, both BUY and SELL treated equally — sorted by signal magnitude (strongest first)
- Remaining items stay alphabetically sorted below signaled items
- Dashboard header shows active signal count summary (e.g., "2 buy signals, 1 sell signal") near the "Updated X ago" text
- Subtle pulse animation when a signal first appears on page load

### Claude's Discretion
- Exact pulse animation implementation (CSS keyframes, duration, intensity)
- Chart threshold line label positioning and formatting
- Rolling average line color/style that complements existing gold median and blue min lines
- Loading state while rolling average is being computed

</decisions>

<specifics>
## Specific Ideas

- Badge format: "BUY -12%" or "SELL +8%" — percentage shows distance past threshold, not trend
- Card border glow should be subtle enough to not overwhelm the WoW dark theme but visible enough to spot at a glance
- Signal count in header keeps dashboard scannable when user has many watched items

</specifics>

<code_context>
## Existing Code Insights

### Reusable Assets
- `WatchedItem` model: already has `buy_threshold` and `sell_threshold` as integer percentage columns (1-100)
- `PriceSnapshot` model: stores `median_price`, `avg_price`, `min_price`, `total_volume`, `polled_at`
- Dashboard Volt component: card grid with trend arrows, gold formatting, chart panel already built
- `formatGold()` PHP method and `formatGoldJs()` JS function: copper-to-gold formatting exists
- `trendDirection()` and `trendPercent()` methods: existing pattern for per-item computed indicators

### Established Patterns
- Dashboard loads snapshots via eager-loading: `with(['priceSnapshots' => fn ($q) => $q->latest('polled_at')->limit(2)])`
- Chart data dispatched via Livewire events: `$this->dispatch('chart-data-updated', ...)`
- ApexCharts initialized in `@script` block with dark theme, gold/blue color scheme
- Card styling: `border-gray-700/50 bg-wow-dark` with `ring-2 ring-wow-gold` for selected state

### Integration Points
- Rolling average computation needs to be added to the dashboard component (or extracted to an action)
- Signal badges integrate into the existing `@foreach ($this->watchedItems as $item)` card loop
- Threshold lines integrate into the existing `chart-data-updated` event handler
- Signal sorting replaces the current `->orderBy('name')` in `watchedItems()` computed property
- Signal count summary adds to the existing header slot

</code_context>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 08-buy-sell-signal-indicators*
*Context gathered: 2026-03-01*
