# Phase 8: Buy/Sell Signal Indicators - Research

**Researched:** 2026-03-01
**Domain:** Eloquent rolling average queries + Livewire Volt computed properties + ApexCharts annotations + CSS keyframe animations + Tailwind signal badge styling
**Confidence:** HIGH

---

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions

**Rolling Average Window**
- 7-day rolling average window using median_price from stored snapshots
- Require minimum 24 hours of data (~96 snapshots at 15-min intervals) before showing signals
- Items with insufficient data show "Collecting data" instead of a signal badge
- Use median_price only (not avg_price) — avoids manipulation by single low-quantity listings per success criteria

**Signal Badge Design**
- Both: pill badge next to item name AND subtle card border color change
- Green "BUY -X%" pill when current price is below buy threshold relative to rolling average
- Red "SELL +X%" pill when current price is above sell threshold relative to rolling average
- Badge includes magnitude percentage (how far past threshold) for at-a-glance opportunity sizing
- Items at normal price show no signal badge — completely clean cards (matches success criteria)

**Threshold Lines on Chart**
- Static dashed horizontal lines at buy and sell threshold levels (rolling average × threshold %)
- Add rolling average as a third line series on the chart alongside median and min
- Dashed green line for buy threshold, dashed red line for sell threshold
- Lines only appear when viewing an item with configured thresholds

**Signal Priority & Sorting**
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

### Deferred Ideas (OUT OF SCOPE)
None — discussion stayed within phase scope
</user_constraints>

---

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| DASH-04 | Visual buy signal shown when price is below user's buy threshold | Rolling average computed via Eloquent aggregate on 7-day median_price window; signal = current_median < rolling_avg * (1 - buy_threshold/100); PHP method computes per-item, returns typed signal DTO/array; Blade renders green pill badge and card border |
| DASH-05 | Visual sell signal shown when price is above user's sell threshold | Same rolling average; signal = current_median > rolling_avg * (1 + sell_threshold/100); PHP method returns typed signal; Blade renders red pill badge and card border |
</phase_requirements>

---

## Summary

Phase 8 builds on the existing Phase 7 dashboard (Livewire Volt SFC at `resources/views/livewire/pages/dashboard.blade.php`) with no new packages required. The entire implementation is pure PHP + Blade + CSS within the existing stack. The two core technical challenges are: (1) computing a 7-day rolling average of median_price per watched item efficiently using Eloquent, and (2) rendering ApexCharts horizontal annotation lines for buy/sell thresholds using the `annotations.yaxis` config key or `addYaxisAnnotation()` method.

The rolling average is a simple SQL `AVG(median_price)` scoped to the last 7 days for each item. The minimum-data guard (96 snapshots ≈ 24 hours at 15-minute intervals) is a COUNT check before computing signals. The signal badge is a Tailwind pill (`rounded-full px-2 py-0.5 text-xs font-semibold`) styled green or red, placed next to the item name in the card header. The card border color changes from `border-gray-700/50` to `border-green-500/60` (buy) or `border-red-500/60` (sell). The pulse animation on first appearance uses a CSS `@keyframes` on a Tailwind utility class applied conditionally.

For the chart, `annotations.yaxis` accepts an array of horizontal line configs each with `y`, `borderColor`, `strokeDashArray`, and `label` properties. These are passed in the `chart-data-updated` event payload alongside the rolling average series data. The rolling average becomes a third chart series alongside existing median and min series.

**Primary recommendation:** Add `rollingSignal(WatchedItem $item)` as a helper method on the dashboard component that returns `['signal' => 'buy'|'sell'|'none'|'insufficient_data', 'magnitude' => float]`. Compute rolling average via a single Eloquent query per item (all queries already scoped by watchedItem relationship). Pass threshold values and rolling average line data to the chart dispatch event alongside existing median/min data.

---

## Standard Stack

### Core

| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| livewire/livewire | ^4.0 (installed) | Reactive PHP component owns signal computation and badge rendering | Already in project — no new install |
| livewire/volt | ^1.7.0 (installed) | Single-file component syntax for dashboard | Already in project — existing file modified |
| apexcharts | ^5.7.0 (installed via npm) | Threshold dashed annotation lines on chart | Already installed — use `annotations.yaxis` API |
| Tailwind CSS | ^4.2.1 (installed) | Pill badges, colored borders, pulse animation utility | Already in project — no new install |

### Supporting

| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| Carbon | (Laravel built-in) | 7-day boundary for rolling average query | `now()->subDays(7)` |
| Eloquent `avg()` aggregate | (Laravel built-in) | Compute average median_price over 7-day window | `->whereBetween('polled_at', [...])->avg('median_price')` |

### Alternatives Considered

| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| PHP rolling average per item | DB raw `AVG()` subquery in the watchedItems query | Subquery is more efficient at scale but premature optimization; simple per-item `avg()` is readable and correct for current data volume |
| CSS `@keyframes` pulse | Alpine.js `x-show` with transition | Alpine transitions are for enter/leave; a repeating ring pulse needs CSS keyframes |
| `annotations.yaxis` in options | `chart.addYaxisAnnotation()` after render | `addYaxisAnnotation()` is additive (accumulates) across renders; passing in options via `updateOptions()` is cleaner for dynamic threshold changes |

**Installation:**
```bash
# No new packages required — all dependencies already installed
```

---

## Architecture Patterns

### Recommended Project Structure

No new files needed. All changes are to existing files:

```
resources/views/livewire/pages/
└── dashboard.blade.php     # Add: rollingSignal(), signalCount(), updated watchedItems()
                             # Add: sorted card rendering, badge HTML, pulse CSS
                             # Add: rolling average series + annotations in chart dispatch

tests/Feature/
└── DashboardTest.php        # Add: signal-specific test cases for DASH-04 / DASH-05
```

Optionally extracted (at Claude's discretion, not required):
```
app/Actions/
└── ComputeSignalAction.php  # Extract rolling average + signal logic if component grows large
```

### Pattern 1: Rolling Average Computation in PHP

**What:** Query the last 7 days of median_price snapshots for one item and compute the average using Eloquent's `avg()` aggregate. Guard against insufficient data with a `count()` check first.

**When to use:** Called per item inside the card rendering loop or in a computed property. The composite index on `(watched_item_id, polled_at)` (installed in Phase 1) makes this efficient.

**Example:**
```php
// Inside the Volt component class
public function rollingSignal(WatchedItem $item): array
{
    // Guard: require at least 96 snapshots (~24h of 15-min polling)
    $snapshotCount = $item->priceSnapshots()
        ->where('polled_at', '>=', now()->subDays(7))
        ->count();

    if ($snapshotCount < 96) {
        return ['signal' => 'insufficient_data', 'magnitude' => 0.0];
    }

    $rollingAvg = (int) $item->priceSnapshots()
        ->where('polled_at', '>=', now()->subDays(7))
        ->avg('median_price');

    if ($rollingAvg === 0) {
        return ['signal' => 'none', 'magnitude' => 0.0];
    }

    // Current price is the most recent snapshot's median_price
    $currentPrice = $item->priceSnapshots->first()?->median_price ?? 0;

    // Buy signal: current price is X% below rolling avg
    // buy_threshold = 10 means "buy when price drops 10% below average"
    $buyLevel = (int) round($rollingAvg * (1 - $item->buy_threshold / 100));

    // Sell signal: current price is X% above rolling avg
    $sellLevel = (int) round($rollingAvg * (1 + $item->sell_threshold / 100));

    if ($currentPrice <= $buyLevel) {
        // Magnitude: how far below buy threshold (positive number)
        $magnitude = round((($rollingAvg - $currentPrice) / $rollingAvg) * 100, 1);
        return ['signal' => 'buy', 'magnitude' => $magnitude];
    }

    if ($currentPrice >= $sellLevel) {
        // Magnitude: how far above sell threshold (positive number)
        $magnitude = round((($currentPrice - $rollingAvg) / $rollingAvg) * 100, 1);
        return ['signal' => 'sell', 'magnitude' => $magnitude];
    }

    return ['signal' => 'none', 'magnitude' => 0.0];
}
```

**Important:** The existing `watchedItems()` computed property eager-loads only 2 snapshots for trend. The `rollingSignal()` method fires additional queries. This is acceptable for the current scale but means N extra queries per item for 7-day count + avg. See N+1 section below.

### Pattern 2: Signal-Sorted watchedItems Query

**What:** Replace the current `->orderBy('name')` with in-memory collection sorting after computing signals, since signal status cannot be determined at the SQL level without a subquery.

**When to use:** The computed property `watchedItems()` needs to return items in signal-first order.

**Example:**
```php
#[Computed]
public function watchedItems(): Collection
{
    $items = auth()->user()->watchedItems()
        ->with(['priceSnapshots' => fn ($q) => $q->latest('polled_at')->limit(2)])
        ->orderBy('name')  // alphabetical base order
        ->get();

    // Compute signals eagerly for all items so sorting can use them
    // Cache on the item instance to avoid recomputation in Blade loop
    $items->each(function (WatchedItem $item) {
        $item->_signal = $this->rollingSignal($item);
    });

    return $items->sortBy(function (WatchedItem $item) {
        $sig = $item->_signal;
        // Signal items first (magnitude desc), then alphabetical (already ordered)
        if ($sig['signal'] === 'buy' || $sig['signal'] === 'sell') {
            return [0, -$sig['magnitude']];  // group 0, descending magnitude
        }
        return [1, 0];  // group 1 (non-signal items stay in their name order)
    })->values();
}
```

**Note:** Attaching `_signal` to the model instance avoids calling `rollingSignal()` twice (once for sorting, once for Blade rendering). PHP `sortBy()` on a Collection handles multi-key sort with an array return from the callback.

### Pattern 3: Signal Badge HTML (Blade)

**What:** Pill badge next to item name — shown only when signal is 'buy' or 'sell'. Card border color changes based on signal.

**Example:**
```blade
{{-- In the card loop, replace the static border class with dynamic --}}
<div
    wire:key="card-{{ $item->id }}"
    wire:click="selectItem({{ $item->id }})"
    class="cursor-pointer rounded-lg border bg-wow-dark p-5 transition-colors hover:border-wow-gold/50
        {{ $selectedItemId === $item->id ? 'ring-2 ring-wow-gold' : '' }}
        @php
            $sig = $item->_signal;
        @endphp
        {{ $sig['signal'] === 'buy' ? 'border-green-500/60' : ($sig['signal'] === 'sell' ? 'border-red-500/60' : 'border-gray-700/50') }}"
>
    <div class="mb-3 flex items-start justify-between">
        <div class="flex items-center gap-2">
            <h3 class="font-medium text-gray-100">{{ $item->name }}</h3>

            {{-- Signal badge --}}
            @if ($sig['signal'] === 'buy')
                <span class="signal-pulse rounded-full bg-green-500/20 px-2 py-0.5 text-xs font-semibold text-green-400 ring-1 ring-green-500/50">
                    BUY -{{ $sig['magnitude'] }}%
                </span>
            @elseif ($sig['signal'] === 'sell')
                <span class="signal-pulse rounded-full bg-red-500/20 px-2 py-0.5 text-xs font-semibold text-red-400 ring-1 ring-red-500/50">
                    SELL +{{ $sig['magnitude'] }}%
                </span>
            @elseif ($sig['signal'] === 'insufficient_data')
                <span class="rounded-full bg-gray-700/50 px-2 py-0.5 text-xs text-gray-500 italic">
                    Collecting data
                </span>
            @endif
        </div>
        {{-- existing trend arrow span --}}
    </div>
    {{-- ... rest of card --}}
</div>
```

### Pattern 4: ApexCharts Horizontal Annotation Lines

**What:** Pass buy/sell threshold levels as `annotations.yaxis` inside the `chart-data-updated` event payload. ApexCharts renders these as dashed horizontal lines.

**When to use:** Only add annotations when the selected item has configured thresholds AND sufficient data for a rolling average.

**ApexCharts annotations.yaxis API (verified against official docs):**
```javascript
annotations: {
    yaxis: [
        {
            y: buyThresholdValue,          // copper integer (absolute price level)
            borderColor: '#22c55e',        // green-500
            strokeDashArray: 6,
            label: {
                text: 'Buy',
                position: 'left',
                style: {
                    background: 'transparent',
                    color: '#22c55e',
                    fontSize: '11px',
                },
            },
        },
        {
            y: sellThresholdValue,         // copper integer (absolute price level)
            borderColor: '#ef4444',        // red-500
            strokeDashArray: 6,
            label: {
                text: 'Sell',
                position: 'left',
                style: {
                    background: 'transparent',
                    color: '#ef4444',
                    fontSize: '11px',
                },
            },
        },
    ],
},
```

**PHP computation of threshold levels for chart dispatch:**
```php
// In loadChart(), compute absolute copper price levels from rolling avg + thresholds
$rollingAvg = (int) auth()->user()
    ->watchedItems()
    ->findOrFail($this->selectedItemId)
    ->priceSnapshots()
    ->where('polled_at', '>=', now()->subDays(7))
    ->avg('median_price');

$item = auth()->user()->watchedItems()->findOrFail($this->selectedItemId);

$annotations = [];
if ($rollingAvg > 0 && $item->buy_threshold) {
    $annotations[] = [
        'level' => (int) round($rollingAvg * (1 - $item->buy_threshold / 100)),
        'type'  => 'buy',
    ];
}
if ($rollingAvg > 0 && $item->sell_threshold) {
    $annotations[] = [
        'level' => (int) round($rollingAvg * (1 + $item->sell_threshold / 100)),
        'type'  => 'sell',
    ];
}

$this->dispatch('chart-data-updated',
    median: $median,
    min: $min,
    rollingAvg: $rollingAvgSeries,  // array of {x, y} points
    annotations: $annotations,      // threshold level data for JS
);
```

**JS event handler update:**
```javascript
$wire.$on('chart-data-updated', ({ median, min, rollingAvg, annotations }) => {
    // Build annotations array for ApexCharts
    const yaxisAnnotations = (annotations || []).map(a => ({
        y: a.level,
        borderColor: a.type === 'buy' ? '#22c55e' : '#ef4444',
        strokeDashArray: 6,
        label: {
            text: a.type === 'buy' ? 'Buy' : 'Sell',
            position: 'left',
            style: {
                background: 'transparent',
                color: a.type === 'buy' ? '#22c55e' : '#ef4444',
                fontSize: '11px',
            },
        },
    }));

    const options = {
        series: [
            { name: 'Median',          data: median },
            { name: 'Min',             data: min },
            { name: 'Rolling Avg',     data: rollingAvg || [] },
        ],
        annotations: { yaxis: yaxisAnnotations },
        // ... rest of existing options unchanged
    };

    // existing chart === null check, render or updateOptions
});
```

### Pattern 5: Signal Count Summary in Header

**What:** Add a method that counts active buy/sell signals across all watched items. Display in header alongside "Updated X ago".

**Example:**
```php
public function signalSummary(): string
{
    $buyCount = 0;
    $sellCount = 0;

    foreach ($this->watchedItems as $item) {
        $sig = $item->_signal ?? $this->rollingSignal($item);
        if ($sig['signal'] === 'buy') $buyCount++;
        if ($sig['signal'] === 'sell') $sellCount++;
    }

    if ($buyCount === 0 && $sellCount === 0) {
        return '';
    }

    $parts = [];
    if ($buyCount > 0) $parts[] = "{$buyCount} buy signal" . ($buyCount > 1 ? 's' : '');
    if ($sellCount > 0) $parts[] = "{$sellCount} sell signal" . ($sellCount > 1 ? 's' : '');

    return implode(', ', $parts);
}
```

```blade
<x-slot name="header">
    <div class="flex items-center justify-between">
        <h2 class="text-xl font-semibold leading-tight text-wow-gold">
            {{ __('Dashboard') }}
        </h2>
        <div class="flex items-center gap-4">
            @if ($summary = $this->signalSummary())
                <span class="text-sm font-medium text-wow-gold">{{ $summary }}</span>
            @endif
            <span class="text-sm text-gray-400">Updated {{ $this->dataFreshness() }}</span>
        </div>
    </div>
</x-slot>
```

### Pattern 6: Pulse Animation for Signal Badges

**What:** A one-time ring-pulse animation on page load for signal badges. Pure CSS `@keyframes` added to the Tailwind stylesheet.

**When to use:** Applied to signal badges on initial render. The animation plays once (not continuous) to draw attention.

**Example (added to `resources/css/app.css`):**
```css
@keyframes signal-pulse {
    0%   { box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.4); }
    70%  { box-shadow: 0 0 0 8px rgba(34, 197, 94, 0); }
    100% { box-shadow: 0 0 0 0 rgba(34, 197, 94, 0); }
}

@keyframes signal-pulse-sell {
    0%   { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.4); }
    70%  { box-shadow: 0 0 0 8px rgba(239, 68, 68, 0); }
    100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
}

.signal-pulse-buy {
    animation: signal-pulse 1.2s ease-out 1;
}

.signal-pulse-sell {
    animation: signal-pulse-sell 1.2s ease-out 1;
}
```

**Alternative (simpler):** Use Tailwind's `animate-pulse` for continuous pulse — but the user decision specifies "subtle pulse when signal first appears on page load" which implies a one-shot animation, not continuous. CSS keyframes with `animation-iteration-count: 1` is correct.

### Anti-Patterns to Avoid

- **Calling `rollingSignal()` multiple times per item in Blade:** Compute signals once in `watchedItems()` and cache on `$item->_signal`. Each call fires 2 SQL queries (count + avg) — 20 items × 2 = 40 extra queries if not cached.
- **Using `chart.addYaxisAnnotation()` for threshold lines:** This method accumulates annotations across re-renders. On every `chart-data-updated` event, old annotations stack on top of new ones. Use `annotations.yaxis` inside `updateOptions()` instead — it replaces previous annotations entirely.
- **Sorting with a second pass that recomputes signals:** Sort the collection using the already-computed `_signal` data, not by re-calling `rollingSignal()`.
- **Passing rolling average line data as raw PHP avg() integer to JS:** The rolling average chart series needs `{x, y}` point arrays just like median and min — it is NOT a single scalar value. The scalar is used only for threshold level computation.
- **Computing rolling average series points (for chart) with per-point queries:** Use a single query fetching all 7-day snapshots ordered by polled_at. Compute the cumulative rolling average in a single PHP pass or just use the overall 7-day average as a flat horizontal reference line (simpler, matches user decision of "dashed horizontal lines at threshold levels").

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Horizontal threshold lines on chart | Custom SVG overlay | ApexCharts `annotations.yaxis` | Built-in, styled, labeled, responsive |
| Rolling average SQL | Manual loop over snapshots | Eloquent `->avg('median_price')` | Single SQL AVG() call; DB does the math |
| Pill badge styles | Custom CSS classes | Tailwind utilities (`rounded-full`, `ring-1`) | Consistent with existing Tailwind-only styling pattern |
| Signal sort | Custom sorting algorithm | Laravel `Collection::sortBy()` with callback | Multi-key sort supported via array return from callback |
| Pulse animation | Alpine.js transition hack | CSS `@keyframes` | Transitions are enter/leave; one-shot pulse needs keyframes |

**Key insight:** ApexCharts `annotations.yaxis` replaces any previous annotation array when passed via `updateOptions()` — this is the correct pattern for dynamically updating threshold lines.

---

## Common Pitfalls

### Pitfall 1: Annotation Accumulation with addYaxisAnnotation()

**What goes wrong:** Every time the user changes timeframe or selects a new item, threshold lines multiply — old ones don't clear.
**Why it happens:** `chart.addYaxisAnnotation()` is additive by design; it never removes previous annotations.
**How to avoid:** Pass `annotations: { yaxis: [...] }` directly inside the options object provided to `updateOptions()`. This fully replaces previous annotations on each render.
**Warning signs:** Multiple dashed lines near the same Y level accumulating on chart re-render.

### Pitfall 2: N+1 Queries for Signal Computation

**What goes wrong:** Dashboard with 15 tracked items fires 30+ extra queries (2 per item for count + avg).
**Why it happens:** `rollingSignal()` is called per item in the Blade loop without caching.
**How to avoid:** Compute all signals in `watchedItems()` and store on each model instance (`$item->_signal = ...`). The badge render and sorting both read from the cached value.
**Warning signs:** Laravel Debugbar shows many identical `SELECT COUNT(*)` and `SELECT AVG(median_price)` queries on dashboard load.

### Pitfall 3: Rolling Average as Third Chart Series vs. Scalar

**What goes wrong:** Rolling average line on chart is flat/wrong because developer passes the scalar avg() result as a series point.
**Why it happens:** The rolling average for badge/threshold computation is a scalar. The rolling average as a chart series requires `{x, y}` points at each polled_at timestamp within the chart timeframe.
**How to avoid:** In `loadChart()`, fetch the 7-day snapshots and compute either:
  - A true per-point rolling average (complex — compute in PHP loop), OR
  - Simply the flat 7-day average as a constant horizontal line series (two points: start and end of timeframe at the same Y value — simpler and sufficient per user decisions)
**Warning signs:** Rolling average series shows as a single dot or a vertical line.

**Recommended approach (flat reference line):** The user decision says "rolling average as a third line series." A flat horizontal line at the 7-day average value is the simplest correct interpretation and visually meaningful:
```php
// Two-point flat series: {start_of_timeframe, avg} → {now, avg}
$avgValue = (int) round($snapshots->avg('median_price'));
$rollingAvgSeries = [
    ['x' => $cutoff->timestamp * 1000, 'y' => $avgValue],
    ['x' => now()->timestamp * 1000,   'y' => $avgValue],
];
```

### Pitfall 4: Threshold Percentage Semantics

**What goes wrong:** Signal fires in the wrong direction or at the wrong level.
**Why it happens:** `buy_threshold = 10` means "alert when price is 10% BELOW average" not "10% above". Direction is easy to invert.
**How to avoid:**
  - BUY signal: `currentPrice <= rollingAvg * (1 - buy_threshold / 100)`
  - SELL signal: `currentPrice >= rollingAvg * (1 + sell_threshold / 100)`
  - Buy threshold level (for chart line): `rollingAvg * (1 - buy_threshold / 100)`
  - Sell threshold level (for chart line): `rollingAvg * (1 + sell_threshold / 100)`
**Warning signs:** Items always or never trigger signals; buy/sell badges appear reversed.

### Pitfall 5: @script Requirement for $wire Access

**What goes wrong:** `$wire` is undefined in the `<script>` block; `$wire.$on()` throws runtime error.
**Why it happens:** Phase 7 established that `@script/@endscript` IS required in this project's Volt components (not bare `<script>` tags as the Phase 7 research initially noted). The actual implementation in `dashboard.blade.php` uses `@script`.
**How to avoid:** Keep the existing `@script/@endscript` wrapper. Any additions to the JS block go inside the existing `@script` wrapper. Do NOT change to a bare `<script>` tag.
**Warning signs:** `$wire` is undefined in browser console.

### Pitfall 6: watchedItems() Computed Property and Livewire Caching

**What goes wrong:** Signal data is stale — shows old signal state after a new snapshot is ingested.
**Why it happens:** Livewire `#[Computed]` properties are per-request (no persistent cache by default). This is actually correct behavior — the signals re-compute fresh on each Livewire request.
**How to avoid:** No special handling needed. Each Livewire round-trip re-runs `watchedItems()` including signal computation. This is the expected pattern.
**Warning signs:** Would only be a problem if Computed cache was explicitly enabled — don't add `#[Computed(cache: true)]` to `watchedItems()` since signals would become stale.

### Pitfall 7: items with null buy_threshold or sell_threshold

**What goes wrong:** Division by null or null comparison causes TypeError.
**Why it happens:** WatchedItemFactory sets thresholds, but a real user may not configure thresholds. The `buy_threshold` and `sell_threshold` columns are `integer` with no database default visible in the model.
**How to avoid:** Guard in `rollingSignal()` — if `$item->buy_threshold === null` skip buy signal check. If neither threshold is set, return `['signal' => 'none', 'magnitude' => 0.0]` immediately.
**Warning signs:** TypeError: "Unsupported operand types: null / int" in PHP logs.

---

## Code Examples

Verified patterns from official sources and project code inspection:

### Rolling Average Query (Eloquent)

```php
// Source: Laravel Eloquent docs + existing project pattern
// Scoped through watchedItem relationship — inherits watched_item_id scoping
$rollingAvg = (int) round(
    $item->priceSnapshots()
        ->where('polled_at', '>=', now()->subDays(7))
        ->avg('median_price') ?? 0
);
```

### Minimum Data Guard

```php
// 96 snapshots = ~24h at 15-minute intervals
$snapshotCount = $item->priceSnapshots()
    ->where('polled_at', '>=', now()->subDays(7))
    ->count();

if ($snapshotCount < 96) {
    return ['signal' => 'insufficient_data', 'magnitude' => 0.0];
}
```

### ApexCharts Annotations (verified against official docs)

```javascript
// Source: https://apexcharts.com/docs/options/annotations/
// Pass inside updateOptions() — REPLACES previous annotations
const options = {
    annotations: {
        yaxis: [
            {
                y: 85000,  // copper value (absolute threshold level)
                borderColor: '#22c55e',
                strokeDashArray: 6,
                label: {
                    text: 'Buy',
                    position: 'left',
                    style: {
                        background: 'transparent',
                        color: '#22c55e',
                        fontSize: '11px',
                    },
                },
            },
            {
                y: 115000,
                borderColor: '#ef4444',
                strokeDashArray: 6,
                label: {
                    text: 'Sell',
                    position: 'left',
                    style: {
                        background: 'transparent',
                        color: '#ef4444',
                        fontSize: '11px',
                    },
                },
            },
        ],
    },
    // ... rest of series, chart config, etc.
};
chart.updateOptions(options);
```

### Collection Multi-Key Sort

```php
// Source: Laravel Collection docs — sortBy() with array return enables multi-key sort
$sorted = $items->sortBy(function (WatchedItem $item) {
    $sig = $item->_signal;
    $hasSignal = in_array($sig['signal'], ['buy', 'sell'], true);
    return [$hasSignal ? 0 : 1, -$sig['magnitude']];
})->values();
// Result: signaled items first (sorted by magnitude desc), then unsignaled items
// (already alphabetically ordered from the base query orderBy('name'))
```

### Pest Test Pattern for Signal Badges (extending DashboardTest.php)

```php
// Test: DASH-04 — buy signal badge shown when price is below buy threshold
it('shows BUY badge when current price is below buy threshold', function () {
    $user = User::factory()->create();
    $item = WatchedItem::factory()->create([
        'user_id' => $user->id,
        'name' => 'Signal Item',
        'buy_threshold' => 10,  // 10% below avg triggers BUY
        'sell_threshold' => 10,
    ]);

    // Create 96+ snapshots over 7 days at rolling avg ~100,000 copper
    foreach (range(1, 100) as $i) {
        PriceSnapshot::factory()->create([
            'watched_item_id' => $item->id,
            'median_price' => 100_000,
            'polled_at' => now()->subDays(7)->addMinutes(15 * $i),
        ]);
    }

    // Current price: 88,000 copper (12% below 100,000 avg — triggers 10% buy threshold)
    PriceSnapshot::factory()->create([
        'watched_item_id' => $item->id,
        'median_price' => 88_000,
        'polled_at' => now()->subMinutes(5),
    ]);

    Volt::actingAs($user)->test('pages.dashboard')
        ->assertSee('BUY');
});

// Test: DASH-04/DASH-05 — no badge at normal price
it('shows no signal badge when price is within thresholds', function () {
    $user = User::factory()->create();
    $item = WatchedItem::factory()->create([
        'user_id' => $user->id,
        'buy_threshold' => 10,
        'sell_threshold' => 10,
    ]);

    foreach (range(1, 100) as $i) {
        PriceSnapshot::factory()->create([
            'watched_item_id' => $item->id,
            'median_price' => 100_000,
            'polled_at' => now()->subDays(7)->addMinutes(15 * $i),
        ]);
    }

    // Current price at average — no signal
    PriceSnapshot::factory()->create([
        'watched_item_id' => $item->id,
        'median_price' => 100_000,
        'polled_at' => now()->subMinutes(5),
    ]);

    Volt::actingAs($user)->test('pages.dashboard')
        ->assertDontSee('BUY')
        ->assertDontSee('SELL');
});
```

---

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| `chart.addYaxisAnnotation()` for dynamic lines | `annotations.yaxis` inside `updateOptions()` | Always the correct approach for replaceable annotations | Avoids stacking old+new annotations on every chart update |
| `@script` confusion (Phase 7 research said bare tags work) | `@script/@endscript` IS required in this project | Phase 7 implementation revealed | The actual dashboard.blade.php uses `@script` — follow existing code, not the research note |
| `Collection::sortBy()` with string key | `Collection::sortBy()` with closure returning array | Always supported | Multi-key sort via array return is idiomatic Laravel |

**Deprecated/outdated:**
- Phase 7 research note "bare `<script>` works in Volt SFCs" — the actual implementation uses `@script`. Follow the working code, not the research document.

---

## Open Questions

1. **Rolling average line: flat constant vs. per-point time-series**
   - What we know: User decision says "rolling average as a third line series on the chart." A flat horizontal line (two-point constant) is the simplest implementation and visually distinguishes the average level clearly.
   - What's unclear: Whether the user expects a time-varying rolling average (computed per timestamp) or a flat average line. A truly time-varying 7-day rolling avg would require computing a per-point windowed average — significantly more complex.
   - Recommendation: Implement as a flat constant horizontal line (two-point series at the 7-day avg value). This matches "rolling average" conceptually (the period is 7 days), renders clearly on the chart, and is consistent with the dashed threshold lines sitting at constant levels relative to it. If the user wants a time-varying line, that is a scope change.

2. **Signal magnitude: relative to rolling avg vs. relative to threshold**
   - What we know: Context says "BUY -12%" — "percentage shows distance past threshold, not trend."
   - What's unclear: Does -12% mean 12% below the rolling average, or 12% past the threshold level (i.e., if threshold is 10% and current is 12% below avg, magnitude = 2% past threshold)?
   - Recommendation: Show how far below the rolling average (not how far past the threshold), since that's the more meaningful "opportunity size" metric. Badge reads "BUY -12%" when price is 12% below rolling avg. This also avoids needing to know the threshold setting inside the badge itself.

3. **Items with both thresholds null (user never configured)**
   - What we know: WatchedItemFactory defaults `buy_threshold` and `sell_threshold` to random values. Real users may skip threshold configuration.
   - What's unclear: The migration column definition — whether null is allowed.
   - Recommendation: Guard in `rollingSignal()` for null thresholds (skip that direction's check). Show no badge (not "Collecting data") if thresholds aren't set. Check the migration to confirm nullable before implementing.

---

## Validation Architecture

### Test Framework

| Property | Value |
|----------|-------|
| Framework | Pest 3.x with pestphp/pest-plugin-laravel |
| Config file | `tests/Pest.php` |
| Quick run command | `php artisan test --group=dashboard` |
| Full suite command | `php artisan test` |

### Phase Requirements → Test Map

| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| DASH-04 | BUY badge appears when price < buy threshold relative to rolling avg | Feature (Livewire Volt) | `php artisan test --filter="buy signal"` | ❌ Wave 0 — add to DashboardTest.php |
| DASH-04 | No badge shown at normal price (within thresholds) | Feature (Livewire Volt) | `php artisan test --filter="no signal badge"` | ❌ Wave 0 — add to DashboardTest.php |
| DASH-04 | "Collecting data" shown when insufficient data (< 96 snapshots) | Feature (Livewire Volt) | `php artisan test --filter="insufficient data"` | ❌ Wave 0 — add to DashboardTest.php |
| DASH-05 | SELL badge appears when price > sell threshold relative to rolling avg | Feature (Livewire Volt) | `php artisan test --filter="sell signal"` | ❌ Wave 0 — add to DashboardTest.php |
| DASH-05 | Signal sorting: signaled items appear before non-signaled items | Feature (Livewire Volt) | `php artisan test --filter="signal sorting"` | ❌ Wave 0 — add to DashboardTest.php |
| DASH-04/05 | Signal count summary appears in header when signals active | Feature (Livewire Volt) | `php artisan test --filter="signal summary"` | ❌ Wave 0 — add to DashboardTest.php |
| DASH-04/05 | chart-data-updated includes annotations when thresholds set | Feature (Livewire Volt) | `php artisan test --filter="chart annotations"` | ❌ Wave 0 — add to DashboardTest.php |

### Sampling Rate

- **Per task commit:** `php artisan test --group=dashboard`
- **Per wave merge:** `php artisan test`
- **Phase gate:** Full suite green before `/gsd:verify-work`

### Wave 0 Gaps

- [ ] Add signal test cases to `tests/Feature/DashboardTest.php` — covers DASH-04 and DASH-05
- [ ] No new test files or framework config needed — existing Pest + Livewire Volt test infrastructure is sufficient

*(No new framework installs required — pestphp/pest-plugin-laravel `Volt::test()` pattern is already established in DashboardTest.php)*

---

## Sources

### Primary (HIGH confidence)

- Existing project code at `resources/views/livewire/pages/dashboard.blade.php` — confirmed `@script/@endscript` pattern, `$wire.$on()`, `chart.updateOptions()`, existing chart options structure
- Existing project code at `app/Models/WatchedItem.php` — confirmed `buy_threshold`, `sell_threshold` integer columns, `priceSnapshots()` HasMany relationship
- Existing project code at `app/Models/PriceSnapshot.php` — confirmed `median_price` integer column, `polled_at` datetime column
- Existing project code at `tests/Feature/DashboardTest.php` — confirmed `Volt::actingAs()->test('pages.dashboard')` pattern, `createUserWithSnapshots()` helper, `assertSee()` / `assertDispatched()` assertions
- [ApexCharts annotations docs](https://apexcharts.com/docs/options/annotations/) — confirmed `annotations.yaxis[]` structure, `y`, `borderColor`, `strokeDashArray`, `label` properties
- [ApexCharts methods docs](https://apexcharts.com/docs/methods/) — confirmed `addYaxisAnnotation(options, pushToMemory)`, `clearAnnotations()`, `updateOptions()` signatures

### Secondary (MEDIUM confidence)

- [ApexCharts annotations guide](https://apexcharts.com/docs/annotations/) — confirmed `strokeDashArray` creates dashed lines with higher values = more space between dashes
- Laravel `Collection::sortBy()` with array-returning callback — established Laravel pattern for multi-key sort; confirmed in Laravel docs

### Tertiary (LOW confidence)

- Assertion that `php artisan test --group=dashboard` works for the existing dashboard test group — based on `uses()->group('dashboard')` in DashboardTest.php; not independently run

---

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — no new packages; all existing libraries with known APIs
- Architecture: HIGH — rolling average via Eloquent avg() is standard SQL pattern; ApexCharts annotations API verified against official docs; all patterns follow established project conventions
- Pitfalls: HIGH — annotation accumulation, N+1 signals, threshold direction — all concrete issues with verified solutions

**Research date:** 2026-03-01
**Valid until:** 2026-04-01 (Livewire 4 and ApexCharts v5 APIs are stable; no expected breaking changes)
