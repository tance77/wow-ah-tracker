# Phase 7: Dashboard and Price Charts - Research

**Researched:** 2026-03-01
**Domain:** ApexCharts + Livewire Volt + Eloquent time-range queries + WoW gold formatter
**Confidence:** HIGH

---

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions

**Summary card layout**
- Responsive grid: 3 columns on desktop, 2 on tablet, 1 on mobile
- Each card shows: item name, current median price (gold format), colored trend arrow, percentage change
- Trend direction determined by comparing current median to previous snapshot's median
- Clicking a card opens a full-width chart section below the grid (not modal, not inline expand)

**Chart interaction**
- Full-width chart section below the card grid — one item's chart visible at a time
- Timeframe toggle is a button group: 24h | 7d | 30d — active button highlighted in gold
- Chart displays two lines: median price and min price
- Hovering data points shows tooltip with timestamp, median, and min price in gold/silver/copper format
- Timeframe toggle updates chart reactively via Livewire without full page reload

**Price formatting**
- Format: "Xg Xs Xc" with colored text — gold for gold, silver for silver, copper for copper
- Zero portions hidden (e.g., "5g" not "5g 0s 0c")
- Trend arrows: green up arrow for increase, red down arrow for decrease, gray dash for flat
- Percentage change displayed alongside arrow (e.g., "↑ +3.2%")
- Gold format used consistently on cards, chart tooltips, and chart Y-axis

**Empty and loading states**
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

### Deferred Ideas (OUT OF SCOPE)
None — discussion stayed within phase scope
</user_constraints>

---

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| DASH-01 | User sees summary cards for all watched items with current price and trend direction | Eloquent `#[Computed]` property loads `watchedItems` with `latestSnapshot` eager-loaded; trend computed from last two snapshots; copper→gold formatter applied |
| DASH-02 | User can view price history line chart for each watched item | ApexCharts v5 line chart initialized in bare `<script>` block; series data passed via Livewire dispatch; `updateOptions()` for reactive updates |
| DASH-03 | User can toggle chart timeframe between 24h, 7d, and 30d | Livewire `$timeframe` public property drives `->where('polled_at', '>=', now()->subHours($hours))` query; chart re-renders via `$this->dispatch('chart-data-updated', ...)` |
| DASH-06 | Dashboard only shows the logged-in user's watched items | All queries scoped through `auth()->user()->watchedItems()` — established pattern from Phase 3 |
</phase_requirements>

---

## Summary

Phase 7 replaces the placeholder dashboard with a full Livewire Volt component that renders summary cards and an interactive ApexCharts line chart. The two core technologies are ApexCharts v5 (npm install, imported via Vite) and Livewire Volt (already installed at v1.7/Livewire 4). The pattern is: Livewire owns state (selected item, timeframe), PHP computes chart data, PHP dispatches a browser event, bare `<script>` block listens with `$wire.$on()` and calls `chart.updateOptions()`.

The `asantibanez/livewire-charts` wrapper package does NOT support Livewire 4 (confirmed: it requires `^3.0`). Direct ApexCharts integration via a bare `<script>` block in a Volt single-file component is the correct approach and is well-documented for this stack. The script block has automatic access to `$wire` without any `@script` wrapper (that is only needed for class-based multi-file components).

The gold/silver/copper formatter is a pure PHP method on the Livewire component (or a blade component helper), plus a mirrored JavaScript function used inside the chart tooltip/Y-axis formatter. Prices are stored as integers in copper; divide by 10000 for gold, 100 for silver, remainder for copper. All Eloquent queries scope through `auth()->user()->watchedItems()` to guarantee DASH-06 isolation.

**Primary recommendation:** Install `apexcharts` via npm, build a single Livewire Volt component at `resources/views/livewire/pages/dashboard.blade.php`, use Livewire `dispatch()` to push chart data arrays to the browser, and call `chart.updateOptions()` inside the `$wire.$on()` listener.

---

## Standard Stack

### Core

| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| apexcharts | ^5.7.0 (latest stable) | Interactive line charts with datetime x-axis | Locked user decision; pure JS, no framework wrapper needed |
| livewire/livewire | ^4.0 (installed) | Reactive PHP component, owns timeframe/selected-item state | Already in project |
| livewire/volt | ^1.7.0 (installed) | Single-file Volt component syntax | Already in project; established pattern |
| Tailwind CSS | ^4.2.1 (installed) | Responsive grid, skeleton loaders, WoW dark theme | Already in project |
| Alpine.js | (bundled with Livewire 4) | Transition animations for chart panel open/close | Already used in nav |

### Supporting

| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| Carbon | (Laravel built-in) | Date arithmetic for 24h/7d/30d query boundaries | Use `now()->subHours(24)`, `now()->subDays(7)`, `now()->subDays(30)` |

### Alternatives Considered

| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Direct ApexCharts | `asantibanez/livewire-charts` | Wrapper does NOT support Livewire 4 — verified incompatible |
| Direct ApexCharts | `larawire-garage/larapex-livewire` | Updated Jan 2025 but Livewire 4 compatibility unverified; adds PHP layer of indirection with no benefit for this use case |
| Bare `<script>` in Volt | `@script` / `@endscript` directive | `@script` is only required for class-based multi-file components; bare `<script>` is correct for Volt single-file components |

**Installation:**
```bash
npm install apexcharts
```

---

## Architecture Patterns

### Recommended Project Structure

```
resources/views/
├── livewire/pages/
│   └── dashboard.blade.php          # Volt single-file: PHP class + Blade + <script>
└── components/
    └── (existing: primary-button, secondary-button, modal, nav-link)

app/
└── (no new models needed — WatchedItem + PriceSnapshot from Phase 1)
```

### Pattern 1: Volt Single-File Component with ApexCharts

**What:** All PHP logic, Blade template, and chart-initializing JavaScript live in one file. The PHP `dispatch()` call pushes chart data to the browser; the bare `<script>` block listens and calls `chart.updateOptions()`.

**When to use:** This is the only correct pattern for this project. `@script` is NOT needed — the Livewire docs confirm bare `<script>` tags work in Volt single-file components.

**Example structure:**
```php
<?php
declare(strict_types=1);

use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Carbon\Carbon;

new #[Layout('layouts.app')] class extends Component
{
    public ?int $selectedItemId = null;
    public string $timeframe = '7d';   // '24h' | '7d' | '30d'

    #[Computed]
    public function watchedItems(): \Illuminate\Support\Collection
    {
        return auth()->user()->watchedItems()
            ->with(['priceSnapshots' => fn ($q) => $q->latest('polled_at')->limit(2)])
            ->orderBy('name')
            ->get();
    }

    public function selectItem(int $id): void
    {
        $this->selectedItemId = ($this->selectedItemId === $id) ? null : $id;
        if ($this->selectedItemId !== null) {
            $this->loadChart();
        }
    }

    public function setTimeframe(string $frame): void
    {
        $this->timeframe = $frame;
        $this->loadChart();
    }

    private function loadChart(): void
    {
        $cutoff = match ($this->timeframe) {
            '24h'  => now()->subHours(24),
            '30d'  => now()->subDays(30),
            default => now()->subDays(7),
        };

        $snapshots = auth()->user()
            ->watchedItems()
            ->findOrFail($this->selectedItemId)
            ->priceSnapshots()
            ->where('polled_at', '>=', $cutoff)
            ->orderBy('polled_at')
            ->get(['polled_at', 'median_price', 'min_price']);

        $this->dispatch('chart-data-updated', [
            'median' => $snapshots->map(fn ($s) => [
                'x' => $s->polled_at->getPreciseTimestamp(3), // JS milliseconds
                'y' => $s->median_price,  // copper integer
            ])->values()->toArray(),
            'min' => $snapshots->map(fn ($s) => [
                'x' => $s->polled_at->getPreciseTimestamp(3),
                'y' => $s->min_price,
            ])->values()->toArray(),
        ]);
    }
}; ?>

{{-- Blade template: grid of cards, chart panel, timeframe buttons --}}

<script>
    let chart = null;

    function formatGold(copper) {
        if (copper === null || copper === undefined) return '—';
        const g = Math.floor(copper / 10000);
        const s = Math.floor((copper % 10000) / 100);
        const c = copper % 100;
        let parts = [];
        if (g > 0) parts.push(`<span class="text-wow-gold">${g.toLocaleString()}g</span>`);
        if (s > 0) parts.push(`<span class="text-gray-300">${s}s</span>`);
        if (c > 0 || parts.length === 0) parts.push(`<span class="text-amber-700">${c}c</span>`);
        return parts.join(' ');
    }

    $wire.$on('chart-data-updated', (payload) => {
        const options = {
            series: [
                { name: 'Median', data: payload.median },
                { name: 'Min',    data: payload.min },
            ],
            chart: {
                type: 'line',
                height: 300,
                background: '#1a1a2e',  // --color-wow-dark
                toolbar: { show: false },
                animations: { enabled: true },
            },
            theme: { mode: 'dark' },
            colors: ['#f7a325', '#60a5fa'],  // wow-gold, blue-400
            stroke: { curve: 'smooth', width: 2 },
            markers: { size: 0 },
            xaxis: {
                type: 'datetime',
                labels: { style: { colors: '#9ca3af' } },
            },
            yaxis: {
                labels: {
                    style: { colors: '#9ca3af' },
                    formatter: (val) => {
                        const g = Math.floor(val / 10000);
                        return g.toLocaleString() + 'g';
                    },
                },
            },
            tooltip: {
                theme: 'dark',
                custom: ({ series, seriesIndex, dataPointIndex, w }) => {
                    const medianVal = series[0][dataPointIndex];
                    const minVal = series[1][dataPointIndex];
                    return `<div class="px-3 py-2 text-sm">
                        <div><strong>Median:</strong> ${formatGold(medianVal)}</div>
                        <div><strong>Min:</strong> ${formatGold(minVal)}</div>
                    </div>`;
                },
            },
            grid: { borderColor: '#374151' },
        };

        if (chart === null) {
            chart = new ApexCharts(document.querySelector('#price-chart'), options);
            chart.render();
        } else {
            chart.updateOptions(options);
        }
    });
</script>
```

### Pattern 2: Trend Computation in PHP

**What:** Compare the most recent two snapshots to determine direction; compute percentage change.

**When to use:** Always compute trend server-side in the Livewire computed property, not in JavaScript.

```php
// Inside a helper or on each WatchedItem eagerly loaded with 2 snapshots
public function trendDirection(WatchedItem $item): string
{
    $snapshots = $item->priceSnapshots; // limit(2) eager loaded
    if ($snapshots->count() < 2) {
        return 'flat';
    }
    $current  = $snapshots->first()->median_price;
    $previous = $snapshots->last()->median_price;
    if ($current > $previous) return 'up';
    if ($current < $previous) return 'down';
    return 'flat';
}

public function trendPercent(WatchedItem $item): ?float
{
    $snapshots = $item->priceSnapshots;
    if ($snapshots->count() < 2) return null;
    $current  = $snapshots->first()->median_price;
    $previous = $snapshots->last()->median_price;
    if ($previous === 0) return null;
    return round((($current - $previous) / $previous) * 100, 1);
}
```

### Pattern 3: Copper-to-Gold Formatter (PHP)

**What:** Convert integer copper value to "Xg Xs Xc" string for Blade display.

```php
public function formatGold(int $copper): string
{
    $g = intdiv($copper, 10000);
    $s = intdiv($copper % 10000, 100);
    $c = $copper % 100;
    $parts = [];
    if ($g > 0) $parts[] = number_format($g) . 'g';
    if ($s > 0) $parts[] = $s . 's';
    if ($c > 0 || empty($parts)) $parts[] = $c . 'c';
    return implode(' ', $parts);
}
```

### Pattern 4: Skeleton Loading Cards

**What:** While Livewire is doing its initial load, show pulsing placeholder cards using Tailwind's `animate-pulse`.

**When to use:** Display during `wire:loading` or on initial page render before computed property resolves.

```html
{{-- Skeleton card (shown while loading) --}}
<div wire:loading.class="hidden" class="contents">
    {{-- real cards go here --}}
</div>
<div wire:loading class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
    @foreach(range(1, 3) as $_)
        <div class="animate-pulse rounded-lg bg-wow-dark p-5 border border-gray-700/50">
            <div class="h-4 bg-gray-700 rounded w-2/3 mb-3"></div>
            <div class="h-6 bg-gray-700 rounded w-1/2 mb-2"></div>
            <div class="h-3 bg-gray-700 rounded w-1/3"></div>
        </div>
    @endforeach
</div>
```

### Pattern 5: Responsive Card Grid with Alpine Transition

**What:** Card click sets `$wire.selectedItemId`; chart section slides open below using Alpine `x-transition`.

```html
{{-- Chart panel --}}
<div x-data="{ open: @entangle('selectedItemId').live }"
     x-show="open !== null"
     x-transition:enter="transition ease-out duration-200"
     x-transition:enter-start="opacity-0 translate-y-2"
     x-transition:enter-end="opacity-100 translate-y-0"
     class="mt-6 bg-wow-dark rounded-lg p-6">
    <div id="price-chart"></div>
</div>
```

### Anti-Patterns to Avoid

- **Reinitializing ApexCharts on every timeframe change:** Always check if `chart === null` — use `updateOptions()` for subsequent changes, not a fresh `new ApexCharts(...).render()`. Reinitializing causes visual flash.
- **Using `@this.on()` (Livewire 2 syntax):** In Livewire 4, use `$wire.$on('event-name', callback)` inside a bare `<script>` block.
- **Wrapping Volt scripts in `@script`:** Unnecessary in Volt single-file components; `@script` is for class-based multi-file components only.
- **Querying `WatchedItem::query()` directly:** Always `auth()->user()->watchedItems()` to ensure DASH-06 isolation.
- **Loading all snapshots for all items for the cards:** Only eager-load `limit(2)` snapshots for trend; full snapshot history loads lazily when a card is clicked.
- **Passing copper values as floats to JavaScript:** Always pass as integers (copper). JavaScript `Math.floor()` handles the division — never convert in PHP before dispatch.

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Line chart with datetime x-axis | Custom SVG chart | ApexCharts v5 | Tooltip, zoom, pan, responsive resize, dark mode — enormous scope |
| Skeleton loading animation | Custom CSS animation | Tailwind `animate-pulse` | One class; browser-optimized keyframe |
| Chart reactive update | Destroy + re-render chart | `chart.updateOptions(options)` | Avoids flicker and is 10x faster |
| Date boundary math | Manual string manipulation | `Carbon::now()->subHours(24)` etc. | Carbon handles DST, timezone edge cases |

**Key insight:** ApexCharts' `updateOptions()` merges new config into existing instance state; always prefer it over destroying and recreating the chart instance.

---

## Common Pitfalls

### Pitfall 1: Chart Not Updating When Timeframe Changes

**What goes wrong:** `$wire.$on('chart-data-updated', ...)` fires, but chart shows stale data or re-renders from scratch every time.
**Why it happens:** `chart` variable is `null` after Livewire component re-renders (morphs DOM), destroying the `<script>` scope.
**How to avoid:** Store the chart instance on the DOM element itself: `document.querySelector('#price-chart')._chart` or a module-level closure. Check `if (chart !== null)` before calling `updateOptions`.
**Warning signs:** Chart flickers on every timeframe toggle.

### Pitfall 2: Timestamp Format Mismatch

**What goes wrong:** ApexCharts shows "NaN" or wrong dates on x-axis.
**Why it happens:** PHP `Carbon::toISOString()` returns ISO 8601 string; ApexCharts prefers millisecond Unix timestamps when `xaxis.type: 'datetime'`.
**How to avoid:** Use `$snapshot->polled_at->getPreciseTimestamp(3)` (Carbon method returning milliseconds as integer). This is numerically safe in JS.
**Warning signs:** X-axis labels all read "Jan 1 1970" or "NaN".

### Pitfall 3: asantibanez/livewire-charts Incompatibility

**What goes wrong:** Composer install fails or chart blade components produce errors.
**Why it happens:** Package requires `livewire/livewire: ^3.0`; project uses `^4.0`.
**How to avoid:** Do NOT install this package. Use direct ApexCharts via npm.
**Warning signs:** Composer error about version constraint mismatch.

### Pitfall 4: Y-Axis Showing Raw Copper Values

**What goes wrong:** Y-axis shows "14532178" instead of "1,453g".
**Why it happens:** No `yaxis.labels.formatter` defined; ApexCharts defaults to raw numeric display.
**How to avoid:** Always define `yaxis.labels.formatter` to show `Math.floor(val / 10000).toLocaleString() + 'g'`.
**Warning signs:** Any number over 10000 appears on Y-axis without "g" suffix.

### Pitfall 5: N+1 Query on Dashboard Load

**What goes wrong:** 20 watched items = 40+ queries (2 snapshots each via lazy loading).
**Why it happens:** `$item->priceSnapshots` accessed in Blade loop without eager loading.
**How to avoid:** Use `->with(['priceSnapshots' => fn($q) => $q->latest('polled_at')->limit(2)])` in the computed property query. Note: Livewire will re-execute this query each request since computed properties are per-request only.
**Warning signs:** Laravel Debugbar shows N+1 queries on dashboard load.

### Pitfall 6: Trend Snapshot Ordering

**What goes wrong:** Trend shows wrong direction (e.g., "up" when price actually dropped).
**Why it happens:** `limit(2)` without explicit `orderBy('polled_at', 'desc')` — ordering not guaranteed.
**How to avoid:** Always `->orderBy('polled_at', 'desc')->limit(2)` in the eager load constraint. Then `$snapshots->first()` = latest, `$snapshots->last()` = previous.
**Warning signs:** Trend arrows intermittently wrong.

---

## Code Examples

Verified patterns from official sources and project conventions:

### ApexCharts npm Import (Vite)

```javascript
// In your <script> block inside the Volt component
import ApexCharts from 'apexcharts';
// OR if not using ES module import in the blade script block, use window reference:
// ApexCharts is available globally if you add it to app.js
```

For Volt `<script>` blocks, the cleanest approach is to import in `resources/js/app.js`:

```javascript
// resources/js/app.js
import ApexCharts from 'apexcharts';
window.ApexCharts = ApexCharts;
```

Then use `window.ApexCharts` or just `ApexCharts` (via global) in the Volt `<script>` block.

### Livewire Dispatch from PHP

```php
// Source: https://livewire.laravel.com/docs/4.x/events
$this->dispatch('chart-data-updated', median: $medianSeries, min: $minSeries);

// In JS, received as:
$wire.$on('chart-data-updated', ({ median, min }) => { ... });
```

### Eloquent Time-Range Query

```php
// Source: Carbon + Eloquent — established Laravel pattern
$cutoff = match ($this->timeframe) {
    '24h'  => now()->subHours(24),
    '30d'  => now()->subDays(30),
    default => now()->subDays(7),  // '7d'
};

$snapshots = auth()->user()
    ->watchedItems()
    ->findOrFail($this->selectedItemId)
    ->priceSnapshots()
    ->where('polled_at', '>=', $cutoff)
    ->orderBy('polled_at', 'asc')
    ->get(['polled_at', 'median_price', 'min_price']);
```

The composite index `(watched_item_id, polled_at)` installed in Phase 1 makes this query efficient.

### Relative Timestamp (Data Freshness)

```php
// "Updated 12 min ago" — use Carbon's diffForHumans()
$latestPoll = auth()->user()
    ->watchedItems()
    ->join('price_snapshots', 'watched_items.id', '=', 'price_snapshots.watched_item_id')
    ->max('price_snapshots.polled_at');

$freshness = $latestPoll
    ? \Carbon\Carbon::parse($latestPoll)->diffForHumans()
    : 'Never';
```

### ApexCharts Dark Theme with WoW Colors

```javascript
// Verified: ApexCharts docs + project CSS variables
const chartOptions = {
    chart: {
        type: 'line',
        height: 300,
        background: '#1a1a2e',    // --color-wow-dark
        toolbar: { show: false },
    },
    theme: { mode: 'dark' },
    colors: ['#f7a325', '#60a5fa'],  // wow-gold, Tailwind blue-400
    stroke: { curve: 'smooth', width: 2 },
    markers: { size: 0 },
    xaxis: {
        type: 'datetime',
        labels: {
            style: { colors: '#9ca3af' },   // gray-400
            datetimeUTC: false,
        },
    },
    yaxis: {
        labels: {
            style: { colors: '#9ca3af' },
            formatter: (val) => Math.floor(val / 10000).toLocaleString() + 'g',
        },
    },
    grid: { borderColor: '#374151' },   // gray-700
    tooltip: {
        theme: 'dark',
        x: { format: 'MMM dd HH:mm' },
    },
};
```

### Gold Formatter (PHP — for Blade cards)

```php
private function formatGold(int $copper): string
{
    $g = intdiv($copper, 10000);
    $s = intdiv($copper % 10000, 100);
    $c = $copper % 100;
    $parts = [];
    if ($g > 0) $parts[] = number_format($g) . 'g';
    if ($s > 0) $parts[] = $s . 's';
    if ($c > 0 || empty($parts)) $parts[] = $c . 'c';
    return implode(' ', $parts);
}
```

### Gold Formatter (JavaScript — for chart tooltip)

```javascript
function formatGold(copper) {
    if (copper === null || copper === undefined) return '—';
    const g = Math.floor(copper / 10000);
    const s = Math.floor((copper % 10000) / 100);
    const c = copper % 100;
    const parts = [];
    if (g > 0) parts.push(g.toLocaleString() + 'g');
    if (s > 0) parts.push(s + 's');
    if (c > 0 || parts.length === 0) parts.push(c + 'c');
    return parts.join(' ');
}
```

---

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| `@this.on()` (Livewire 2 JS) | `$wire.$on()` (Livewire 3+/4) | Livewire 3.0 | Must use new API; old syntax silently no-ops |
| `asantibanez/livewire-charts` Livewire 4 | Direct ApexCharts via npm | Ongoing — wrapper stuck at ^3.0 | No wrapper; integrate ApexCharts directly |
| Reinitializing chart on every update | `chart.updateOptions(newOpts)` | Always the pattern | Avoids flicker; preserves zoom state |
| ApexCharts v4 | ApexCharts v5 (current: 5.7.0) | v5 released ~2024 | No breaking changes from v4; internal build switched from Rollup to Vite |
| `@script` in Volt | Bare `<script>` tags | Livewire 4 docs clarification | `@script` is for class-based multi-file only; Volt SFCs use bare tags |

**Deprecated/outdated:**
- `document.addEventListener('livewire:load', ...)`: In Livewire 4, use `$wire.$on()` inside component scripts — no need for load event wrapping.
- `$this->emit()`: Replaced by `$this->dispatch()` in Livewire 3+.

---

## Open Questions

1. **Carbon `getPreciseTimestamp(3)` availability**
   - What we know: Documented as a Carbon method returning Unix timestamp in given precision (3 = milliseconds).
   - What's unclear: Whether this is in Carbon 2.x vs 3.x bundled with Laravel 12.
   - Recommendation: Fallback is `$snapshot->polled_at->timestamp * 1000` (seconds * 1000 = ms). Use this as the safe alternative; verify against `now()->getPreciseTimestamp(3)` in Tinker.

2. **ApexCharts global import strategy**
   - What we know: Volt `<script>` blocks do not support ES module `import` syntax natively.
   - What's unclear: Whether `import ApexCharts from 'apexcharts'` works inside a Volt inline script block vs requiring registration in `app.js`.
   - Recommendation: Register in `resources/js/app.js` as `window.ApexCharts = ApexCharts` and reference globally. This is the established pattern for third-party JS libraries with Livewire/Volt. This also ensures Vite tree-shaking applies correctly.

3. **Chart panel Alpine transition with Livewire morph**
   - What we know: Alpine `x-show` with `x-transition` works alongside Livewire morphing.
   - What's unclear: Whether `@entangle('selectedItemId').live` correctly drives Alpine `x-show` for the chart panel open/close, or if a plain `wire:model` is cleaner.
   - Recommendation: Use `x-data="{ open: $wire.entangle('selectedItemId') }"` with `x-show="open !== null"`. Test the null vs integer cases since Alpine's truthiness check treats `0` as falsy.

---

## Sources

### Primary (HIGH confidence)
- [Livewire 4.x JavaScript docs](https://livewire.laravel.com/docs/4.x/javascript) — `$wire.$on()`, bare `<script>` in Volt, `$wire.$dispatch()`
- [Livewire 4.x Events docs](https://livewire.laravel.com/docs/4.x/events) — `$this->dispatch()` PHP API
- [ApexCharts Datetime docs](https://apexcharts.com/docs/datetime/) — timestamp format, xaxis type datetime
- [ApexCharts Methods docs](https://apexcharts.com/docs/methods/) — `updateSeries()` and `updateOptions()` signatures
- [ApexCharts Tooltip docs](https://apexcharts.com/docs/options/tooltip/) — `tooltip.custom`, `tooltip.y.formatter`
- [ApexCharts Theme docs](https://apexcharts.com/docs/options/theme/) — `theme.mode: 'dark'`
- [ApexCharts Line Chart docs](https://apexcharts.com/docs/chart-types/line-chart/) — basic multi-series structure

### Secondary (MEDIUM confidence)
- [asantibanez/livewire-charts Packagist](https://packagist.org/packages/asantibanez/livewire-charts) — confirmed requires `livewire/livewire: ^3.0` (not v4 compatible)
- [Chasing Code: Livewire + ApexCharts](https://chasingcode.dev/blog/laravel-livewire-dynamic-charts-apexcharts/) — event dispatch + `updateSeries()` pattern confirmed
- [GitHub Discussion #9253](https://github.com/livewire/livewire/discussions/9253) — `$wire.on()` + `updateSeries()` confirmed as community-validated pattern
- ApexCharts v5.7.0 confirmed as latest stable (released 2026-03-01)

### Tertiary (LOW confidence)
- Carbon `getPreciseTimestamp(3)` — confirmed as a Carbon method but version availability not explicitly cross-checked against Laravel 12's bundled Carbon version

---

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — ApexCharts v5 npm install is straightforward; Livewire Volt patterns verified against official docs
- Architecture: HIGH — `$wire.$on()` + `updateOptions()` pattern is documented and community-verified; wrapper incompatibility confirmed
- Pitfalls: HIGH — timestamp format, N+1 query, trend ordering are all concrete, verified issues with clear solutions

**Research date:** 2026-03-01
**Valid until:** 2026-04-01 (ApexCharts v5 is stable; Livewire 4 API stable)
