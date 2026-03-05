# Phase 12: Batch Calculator and Profit Summary - Research

**Researched:** 2026-03-04
**Domain:** Alpine.js client-side calculation, Livewire Volt data passing, profit math, staleness detection
**Confidence:** HIGH

## Summary

Phase 12 adds a batch calculator to the shuffle detail page that computes cascading yields, per-step breakdowns, profit summary, and break-even price entirely client-side in Alpine.js using prices loaded once from PHP/Livewire. This eliminates server round-trips per keystroke while keeping prices fresh on page load.

The implementation has two distinct parts: (1) a new `#[Computed]` method on the Volt component that serializes all necessary price data (median price + polled_at timestamp per item) as JSON and passes it to Alpine via a Blade `@js()` call or `x-data` initialization, and (2) a new Alpine.js component embedded in a new `<!-- Batch Calculator -->` section below the step editor (after line ~617 in shuffle-detail.blade.php). The Shuffle model's `profitPerUnit()` is refactored to use proper multi-step cascade logic, matching the calculator.

All math is integer arithmetic in copper (WoW's base currency unit). The `formatGold()` method from `FormatsAuctionData` already handles display. The Alpine component mirrors that math in JavaScript, working in the same copper integer units.

**Primary recommendation:** Use a single `#[Computed] priceData(): array` method on the Volt component to pre-fetch all step prices in one query, encode as JSON with `@js()`, and pass into an Alpine `x-data` island. All calculation runs in Alpine — no `$wire` calls in the calculator hot path.

---

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions

#### Calculator placement and input UX
- Separate section below the step editor on the shuffle detail page — clean separation between editing steps and calculating profit
- Calculator section hidden entirely when shuffle has no steps — only appears once steps exist
- Default input quantity: 1 (shows per-unit economics, consistent with profitPerUnit badge)
- All calculation done client-side in Alpine.js using prices loaded once on page render — no server round-trips per keystroke
- Prices refresh on page load (or Livewire poll if present)

#### Yield calculation approach
- For variable yields (min/max range), show both min and max columns — pessimistic and optimistic profit range
- Cascading compounds ranges: step 1 min feeds step 2 min, step 1 max feeds step 2 max — full pessimistic/optimistic paths
- Cost model: only the first step's input has a real purchase cost (intermediate items are produced, not bought)
- Value model: only the final step's output is valued (sold on AH) — total value = final output qty x final output price x 0.95

#### Per-step breakdown display
- Compact table rows: Step | Input (icon + name + qty) | Output (icon + name + qty) | Yield ratio — one row per step
- Item icons shown alongside names in table rows for visual continuity with step editor cards above
- Profit summary section with five rows: Total Cost, Gross Value, AH Cut (5%), Net Profit, Break-even input price
- All monetary values in gold/silver/copper format using existing `formatGold()` method
- Net Profit row colored green when positive, red when negative — consistent with profitability badge pattern
- Other summary rows use neutral gray/white text

#### Staleness and edge states
- Staleness warning: amber banner at top of calculator section listing items with stale prices (>1 hour since last snapshot) and how long ago
- Missing prices (no snapshots for an item): show "--" for missing prices, profit summary shows "Cannot calculate — missing prices for: [items]" — calculator still shows yield quantities but no monetary values
- Refactor `profitPerUnit()` on Shuffle model to use proper multi-step cascade logic matching the calculator — badge and calculator always agree, badge shows conservative (min) profit

### Claude's Discretion
- Exact table styling, column widths, and responsive behavior
- How prices are passed from PHP/Livewire to Alpine (JSON prop, Blade data attributes, etc.)
- Loading state while prices are being fetched
- Break-even calculation formula details
- Whether to show "per unit" or "per batch" labels

### Deferred Ideas (OUT OF SCOPE)

None — discussion stayed within phase scope
</user_constraints>

---

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| INTG-02 | Shuffle calculator uses live median prices from latest price snapshots | `#[Computed] priceData()` fetches latest snapshot per item via `latest('polled_at')->limit(1)` — same eager-load pattern used by existing `profitPerUnit()` |
| INTG-03 | Price staleness warning shown when snapshot is older than 1 hour | `polled_at` is cast as datetime on PriceSnapshot; compare `now()->diffInMinutes($polledAt) > 60` server-side, pass flag in priceData JSON; Alpine renders amber banner |
| CALC-01 | User can enter input quantity and see cascading yields per step | Alpine `x-model` on number input drives reactive cascade — no $wire needed; cascade multiplies output qty through chain each step |
| CALC-02 | User can see per-step cost and value breakdown | Step table row shows input qty x input price (cost) and output qty x output price (value) computed from Alpine state |
| CALC-03 | User can see total profit summary (cost in, value out with 5% AH cut, net profit) | Five-row summary: totalCost, grossValue, ahCut, netProfit, breakEven — all derived properties on the Alpine component |
| CALC-04 | User can see break-even input price per shuffle | breakEven = netOutput / batchQty; displayed using JS formatGold mirror or pre-formatted string from PHP |
</phase_requirements>

---

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| Alpine.js | 3.x (already in stack) | Client-side reactivity for calculator without server round-trips | Already used for step editor yield inputs, inline editing — no new dependency |
| Livewire Volt | 3.x (already in stack) | `#[Computed]` to build priceData array; Blade renders JSON into Alpine | Project standard; all Volt SFC pages follow this pattern |
| FormatsAuctionData trait | (project code) | `formatGold(int $copper): string` for gold/silver/copper display | Already used on shuffle-detail and shuffles list pages |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| Carbon / Laravel `now()` | (framework) | Staleness calculation: `$snapshot->polled_at->diffInMinutes(now())` | Computing seconds-since-polled server-side to pass to Alpine |
| `@js()` Blade helper | (Laravel/Livewire) | Safely encodes PHP arrays/values as JSON for inline Alpine `x-data` | Preferred over manual `json_encode` — handles HTML escaping |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Passing prices via `@js()` | Livewire JS component property | `@js()` is simpler — no Livewire reactivity needed since prices only refresh on page load |
| Inline Alpine `x-data` | Separate Alpine `Alpine.data()` module | Inline keeps everything in the SFC; module would require a separate JS file |
| JS formatGold function | Pre-formatting all values server-side | Pre-formatting is simpler (reuse PHP `formatGold()`), but loses Alpine reactivity on quantity change; JS mirror is the right call |

**Installation:** No new packages — uses existing stack.

---

## Architecture Patterns

### Recommended Project Structure

No new files needed. Changes are:
```
app/Models/Shuffle.php          # Refactor profitPerUnit() — multi-step cascade
resources/views/livewire/pages/shuffle-detail.blade.php
  # Add: #[Computed] priceData(): array  (PHP section)
  # Add: <!-- Batch Calculator --> section (Blade template section)
tests/Feature/ShuffleBatchCalculatorTest.php   # New test file
```

### Pattern 1: Price Data Computed Property

**What:** A `#[Computed]` method that iterates the shuffle's steps, fetches the latest PriceSnapshot per unique item, and returns a keyed array of `[blizzardItemId => ['price' => int|null, 'polled_at' => string|null, 'item_name' => string]]`.

**When to use:** Whenever PHP data needs to seed an Alpine.js island on page render without a round-trip.

**Example:**
```php
// In the Volt component PHP section of shuffle-detail.blade.php
#[Computed]
public function priceData(): array
{
    // Collect all unique item IDs across all steps
    $itemIds = $this->steps->flatMap(fn ($step) => [
        $step->input_blizzard_item_id,
        $step->output_blizzard_item_id,
    ])->unique()->values();

    // One query: latest snapshot per item
    $snapshots = \App\Models\PriceSnapshot::query()
        ->whereHas('catalogItem', fn ($q) => $q->whereIn('blizzard_item_id', $itemIds))
        ->with('catalogItem')
        ->orderBy('polled_at', 'desc')
        ->get()
        ->unique(fn ($s) => $s->catalogItem->blizzard_item_id);

    $result = [];
    foreach ($snapshots as $snapshot) {
        $itemId = $snapshot->catalogItem->blizzard_item_id;
        $ageMinutes = $snapshot->polled_at->diffInMinutes(now());
        $result[$itemId] = [
            'price'     => $snapshot->median_price,      // integer copper
            'polled_at' => $snapshot->polled_at->toIso8601String(),
            'age_minutes' => $ageMinutes,
            'stale'     => $ageMinutes > 60,
        ];
    }
    return $result;
}
```

### Pattern 2: Alpine Calculator Island

**What:** A single `x-data` block seeded from `@js($this->priceData)` and `@js($this->steps->...)` that owns `batchQty` and derives all display values as Alpine getters.

**When to use:** When a user input (quantity) drives many derived values reactively without server involvement.

**Example:**
```html
<!-- Batch Calculator section in Blade template -->
@if ($this->steps->isNotEmpty())
<div class="overflow-hidden bg-wow-dark shadow-sm sm:rounded-lg"
     x-data="batchCalculator(@js($this->calculatorSteps), @js($this->priceData))">

    <!-- Staleness warning banner -->
    <template x-if="staleItems.length > 0">
        <div class="border-b border-amber-700/50 bg-amber-900/20 px-6 py-3">
            <p class="text-xs text-amber-400">
                Stale prices:
                <template x-for="item in staleItems" :key="item.id">
                    <span x-text="item.name + ' (' + item.age + ')'"></span>
                </template>
            </p>
        </div>
    </template>

    <!-- Input quantity -->
    <div class="px-6 py-4">
        <label class="text-xs text-gray-400">Input Quantity</label>
        <input type="number" min="1" x-model.number="batchQty"
               class="w-24 rounded border border-gray-600 bg-wow-darker px-2 py-1 text-gray-100 focus:border-wow-gold focus:outline-none focus:ring-1 focus:ring-wow-gold" />
    </div>

    <!-- Step breakdown table -->
    <!-- Profit summary rows -->
</div>
@endif
```

**Alpine component definition (in a `<script>` block or inline):**
```javascript
// Either inline in x-data or in Alpine.data() registered in app.js
function batchCalculator(steps, prices) {
    return {
        batchQty: 1,

        get cascade() {
            // Build array of {step, inputQtyMin, inputQtyMax, outputQtyMin, outputQtyMax}
            let qtyMin = this.batchQty;
            let qtyMax = this.batchQty;
            return steps.map(step => {
                const ratio = step.output_qty_min / step.input_qty;
                const ratioMax = step.output_qty_max / step.input_qty;
                const outMin = Math.floor(qtyMin * ratio);
                const outMax = Math.floor(qtyMax * ratioMax);
                const row = { step, inputQtyMin: qtyMin, inputQtyMax: qtyMax, outputQtyMin: outMin, outputQtyMax: outMax };
                qtyMin = outMin;
                qtyMax = outMax;
                return row;
            });
        },

        get staleItems() {
            return steps.flatMap(s => [s.input_id, s.output_id])
                .filter((v, i, a) => a.indexOf(v) === i)
                .filter(id => prices[id]?.stale)
                .map(id => ({ id, name: prices[id]?.name ?? id, age: prices[id]?.age_minutes + 'm ago' }));
        },

        get canCalculate() {
            const first = steps[0];
            const last = steps[steps.length - 1];
            return first && last
                && prices[first.input_id]?.price != null
                && prices[last.output_id]?.price != null;
        },

        get totalCostMin() {
            if (!this.canCalculate) return null;
            const row = this.cascade[0];
            return row.inputQtyMin * prices[steps[0].input_id].price;
        },

        get grossValueMin() {
            if (!this.canCalculate) return null;
            const row = this.cascade[this.cascade.length - 1];
            return row.outputQtyMin * prices[steps[steps.length - 1].output_id].price;
        },

        get netProfitMin() {
            if (!this.canCalculate) return null;
            return Math.round(this.grossValueMin * 0.95) - this.totalCostMin;
        },

        get breakEven() {
            if (!this.canCalculate || this.cascade[0].inputQtyMin === 0) return null;
            // Max input price to still break even:
            // breakEven = Math.floor(grossValueMin * 0.95 / batchQty)
            return Math.floor(Math.round(this.grossValueMin * 0.95) / this.batchQty);
        },

        formatGold(copper) {
            if (copper === null) return '--';
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
    };
}
```

### Pattern 3: Refactored profitPerUnit() — Multi-Step Cascade

**What:** Replace the current naive first-step-input / last-step-output calculation with a proper cascade that multiplies yield ratios through all steps.

**Example:**
```php
public function profitPerUnit(): ?int
{
    $steps = $this->steps()->with([
        'inputCatalogItem.priceSnapshots' => fn ($q) => $q->latest('polled_at')->limit(1),
        'outputCatalogItem.priceSnapshots' => fn ($q) => $q->latest('polled_at')->limit(1),
    ])->get();

    if ($steps->isEmpty()) {
        return null;
    }

    // First step's input price (the thing we buy)
    $inputPrice = $steps->first()->inputCatalogItem?->priceSnapshots->first()?->median_price;
    if ($inputPrice === null) {
        return null;
    }

    // Last step's output price (the thing we sell)
    $outputPrice = $steps->last()->outputCatalogItem?->priceSnapshots->first()?->median_price;
    if ($outputPrice === null) {
        return null;
    }

    // Cascade min yields through all steps for conservative estimate
    $outputQty = 1;  // starts with 1 unit input
    foreach ($steps as $step) {
        $ratio = $step->output_qty_min / max(1, $step->input_qty);
        $outputQty = (int) floor($outputQty * $ratio);
    }

    $grossOutput = $outputPrice * $outputQty;
    $netOutput = (int) round($grossOutput * 0.95); // 5% AH cut

    return $netOutput - $inputPrice;
}
```

### Anti-Patterns to Avoid

- **Calling `$wire` methods on every keystroke:** The quantity input must use Alpine-only reactivity. Never `wire:model` the quantity input — that triggers a server round-trip on every character.
- **Mixing `unset($this->priceData)` on step changes:** The priceData computed property will naturally recompute on next Livewire render. No need to explicitly unset it unless a step add/delete happens (Livewire already re-renders).
- **Float arithmetic for profit math:** All values are integers in copper. Use `Math.floor()` / `Math.round()` in JS the same way PHP uses `intdiv()` / `round()`. Never use floating point division as the final value.
- **Showing monetary values for steps where price is null:** If an intermediate item has no snapshot, gracefully show "--" and suppress the profit summary. The calculator must handle partial price data.

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Gold formatting | Custom JS currency formatter | Mirror existing `formatGold()` logic in Alpine (trivial, 10 lines) | Logic is already proven in PHP; direct port needed since JS can't call PHP inline |
| Staleness tracking | Custom timestamp comparison library | Carbon's `diffInMinutes(now())` on server + pass age_minutes to Alpine | Server already has the polled_at datetime cast; client just reads the pre-computed value |
| Price data fetching | Client-side AJAX endpoint | `#[Computed] priceData()` loaded once on render | No dedicated API endpoint needed — Livewire re-renders on page load and whenever steps change |
| Cascade yield math | Complex recursive tree | Simple iterative loop — output of step N becomes input of step N+1 | Single-output-per-step constraint makes this linear, not a tree |

**Key insight:** The project already has everything needed. This phase is mostly wiring existing pieces together: `priceData()` computed + Alpine math + Blade template. No new models, no new routes, no new migrations.

---

## Common Pitfalls

### Pitfall 1: priceData() runs an N+1 query
**What goes wrong:** Iterating steps and calling `.priceSnapshots()->latest()->first()` inside a loop causes one query per step.
**Why it happens:** The naive approach mirrors `profitPerUnit()` which already does this correctly but only for a badge — acceptable there, not for a computed property called on every render.
**How to avoid:** Collect all unique item IDs first, then one `PriceSnapshot` query with `whereHas` + `orderBy('polled_at', 'desc')` + `unique()`. Or join through `catalog_items` and get distinct latest per item in one query.
**Warning signs:** Laravel Debugbar showing 2N queries where N = step count.

### Pitfall 2: Livewire re-renders wipe Alpine state (batchQty resets)
**What goes wrong:** User types `500` in the quantity input, then saves a yield edit on a step card. Livewire morphs the DOM; Alpine state is reset to `batchQty: 1`.
**Why it happens:** Livewire's DOM morphing replaces the Alpine `x-data` element if `wire:key` is missing or the element moves.
**How to avoid:** Wrap the calculator section in a stable container. Consider initializing `batchQty` from `localStorage` on Alpine init: `init() { this.batchQty = parseInt(localStorage.getItem('calc-qty') || '1'); }` with a watcher that persists it. Or use `wire:ignore` on the calculator section to prevent Livewire from morphing it.
**Warning signs:** Quantity input resets to 1 unexpectedly after step edits.

### Pitfall 3: Break-even formula confusion
**What goes wrong:** Break-even is displayed per batch (confusing) or calculated incorrectly as `(profit + cost) / outputQty` instead of per-input-unit.
**Why it happens:** The spec says "maximum they can pay per input unit" — this is different from net profit.
**How to avoid:** Formula: `breakEven = floor(netOutputValue / batchQty)` where `netOutputValue = floor(finalOutputQty * finalOutputPrice * 0.95)`. This is the maximum per-unit input cost where profit = 0.
**Warning signs:** Break-even equals or exceeds current input price when the shuffle is clearly unprofitable.

### Pitfall 4: cascade() getter called multiple times
**What goes wrong:** Alpine evaluates `get cascade()` once for the table rows and again for the profit summary, causing double computation. For 2-3 step shuffles this is fine; for complex chains it can flicker.
**Why it happens:** Alpine getters are not memoized by default.
**How to avoid:** Store cascade result in a reactive data property updated via a watcher on `batchQty`, or accept the minor redundancy (2-3 step shuffles are short computation).

### Pitfall 5: Missing items in priceData for items added after page load
**What goes wrong:** User adds a new step while on the page. The new step's items have no entry in the priceData JSON (loaded at render time). Calculator shows "--" even if prices exist.
**Why it happens:** `priceData` is computed on render; adding a step triggers Livewire re-render, but if `priceData` is not properly invalidated, stale data is used.
**How to avoid:** Ensure `unset($this->priceData)` is called in `addStep()`, `deleteStep()`, `moveStepUp()`, `moveStepDown()` — the same pattern used for `unset($this->steps)` on those actions. Livewire will recompute `priceData` on next render.

---

## Code Examples

### Passing PHP array to Alpine via @js()
```html
{{-- In Blade template --}}
<div x-data="batchCalculator(@js($this->calculatorSteps), @js($this->priceData))">
```
`@js()` is equivalent to `json_encode()` with proper HTML entity escaping. It is safe for inline use. (Source: Laravel Blade docs — `@js` directive introduced in Laravel 9)

### Building calculatorSteps from Livewire steps
```php
// In the Volt component — a second computed property for Alpine-friendly step data
#[Computed]
public function calculatorSteps(): array
{
    return $this->steps->map(fn ($step) => [
        'id'              => $step->id,
        'input_id'        => $step->input_blizzard_item_id,
        'output_id'       => $step->output_blizzard_item_id,
        'input_qty'       => $step->input_qty,
        'output_qty_min'  => $step->output_qty_min,
        'output_qty_max'  => $step->output_qty_max,
        'input_name'      => $step->inputCatalogItem?->display_name ?? "Item #{$step->input_blizzard_item_id}",
        'output_name'     => $step->outputCatalogItem?->display_name ?? "Item #{$step->output_blizzard_item_id}",
        'input_icon'      => $step->inputCatalogItem?->icon_url,
        'output_icon'     => $step->outputCatalogItem?->icon_url,
    ])->toArray();
}
```

### Staleness check in priceData
```php
'stale' => $snapshot->polled_at->diffInMinutes(now()) > 60,
'age_label' => $snapshot->polled_at->diffForHumans(),  // "2 hours ago"
```

### Alpine cascade for min/max paths
```javascript
// Cascade compounds ranges: min feeds min, max feeds max
get cascade() {
    let qtyMin = this.batchQty;
    let qtyMax = this.batchQty;
    return steps.map(step => {
        const outMin = Math.floor(qtyMin * step.output_qty_min / step.input_qty);
        const outMax = Math.floor(qtyMax * step.output_qty_max / step.input_qty);
        const row = {
            step,
            inputQtyMin: qtyMin,
            inputQtyMax: qtyMax,
            outputQtyMin: outMin,
            outputQtyMax: outMax,
        };
        qtyMin = outMin;
        qtyMax = outMax;
        return row;
    });
},
```

### Break-even formula
```javascript
get breakEven() {
    if (!this.canCalculate || this.batchQty < 1) return null;
    const lastRow = this.cascade[this.cascade.length - 1];
    const finalOutputQty = lastRow.outputQtyMin;  // conservative (min) path
    const finalOutputPrice = prices[steps[steps.length - 1].output_id].price;
    const netOutputValue = Math.round(finalOutputQty * finalOutputPrice * 0.95);
    return Math.floor(netOutputValue / this.batchQty);
},
```

### Profit summary five-row pattern
```html
<tbody class="divide-y divide-gray-700/30 text-sm">
    <tr>
        <td class="py-2 text-gray-400">Total Cost</td>
        <td class="py-2 text-right text-gray-200" x-text="formatGold(totalCostMin)"></td>
        <td class="py-2 text-right text-gray-200" x-text="formatGold(totalCostMax)"></td>
    </tr>
    <tr>
        <td class="py-2 text-gray-400">Gross Value</td>
        <td class="py-2 text-right text-gray-200" x-text="formatGold(grossValueMin)"></td>
        <td class="py-2 text-right text-gray-200" x-text="formatGold(grossValueMax)"></td>
    </tr>
    <tr>
        <td class="py-2 text-gray-400">AH Cut (5%)</td>
        <td class="py-2 text-right text-gray-500" x-text="grossValueMin !== null ? formatGold(Math.round(grossValueMin * 0.05)) : '--'"></td>
        <td class="py-2 text-right text-gray-500" x-text="grossValueMax !== null ? formatGold(Math.round(grossValueMax * 0.05)) : '--'"></td>
    </tr>
    <tr class="border-t border-gray-600">
        <td class="py-2 font-medium text-gray-300">Net Profit</td>
        <td class="py-2 text-right font-medium"
            :class="netProfitMin !== null && netProfitMin >= 0 ? 'text-green-400' : 'text-red-400'"
            x-text="formatGold(netProfitMin)"></td>
        <td class="py-2 text-right font-medium"
            :class="netProfitMax !== null && netProfitMax >= 0 ? 'text-green-400' : 'text-red-400'"
            x-text="formatGold(netProfitMax)"></td>
    </tr>
    <tr>
        <td class="py-2 text-gray-400">Break-even price</td>
        <td class="py-2 text-right text-wow-gold" x-text="formatGold(breakEven)"></td>
        <td class="py-2 text-right text-gray-500">—</td>
    </tr>
</tbody>
```

---

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| `profitPerUnit()` naive: first input price, last output price, only last step yield | Proper cascade: multiply yield ratios across all steps | Phase 12 | Badge and calculator now agree; old badge understated profitability for multi-step chains |
| Price data via per-keystroke wire calls | Alpine with JSON-encoded price map from server on render | Phase 12 | Eliminates latency on every quantity change |

**Deprecated/outdated:**
- Naive `profitPerUnit()`: The Phase 10 decision explicitly noted this would be replaced in Phase 12. The new version must cascade all step yields, not just use the last step's output_qty_min.

---

## Open Questions

1. **Should `priceData` use a subquery for latest-per-item or application-side `unique()`?**
   - What we know: For 2-5 items, application-side `->get()->unique()` is fine; for large tables, a GROUP BY subquery is more efficient.
   - What's unclear: Price snapshot table size in production — likely small (commodity items only).
   - Recommendation: Application-side `unique()` for simplicity; add a DB index on `(catalog_item_id, polled_at)` if query gets slow (already likely indexed from Phase 9).

2. **Should batchQty survive Livewire re-renders via `wire:ignore`?**
   - What we know: Every step save triggers a Livewire re-render; Alpine state can be wiped.
   - What's unclear: Whether Livewire morphing will actually destroy the Alpine island or preserve it (depends on whether the DOM element is replaced or patched).
   - Recommendation: Use `wire:ignore` on the calculator container div as a safety measure. This prevents Livewire from morphing the calculator section at all — prices still update via the `@js()` seed on full page load.

3. **Min/max column header labels: "Pessimistic / Optimistic" vs "Min Yield / Max Yield"?**
   - What we know: CONTEXT.md says min/max columns — exact labels are discretion.
   - What's unclear: Which is clearer to a WoW player.
   - Recommendation: Use "Min" / "Max" as compact labels — WoW players familiar with RNG understand this.

---

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | Pest PHP (Laravel feature tests) |
| Config file | `phpunit.xml` |
| Quick run command | `php artisan test --filter=ShuffleBatchCalculator` |
| Full suite command | `php artisan test` |

### Phase Requirements → Test Map

| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| INTG-02 | Calculator uses live median prices from latest snapshots | unit (model) | `php artisan test --filter=ShuffleBatchCalculator` | No — Wave 0 |
| INTG-03 | Staleness flag set when snapshot older than 1 hour | unit (model) | `php artisan test --filter=ShuffleBatchCalculator` | No — Wave 0 |
| CALC-01 | Cascading yields update client-side without server round-trips | feature (Volt) | `php artisan test --filter=ShuffleBatchCalculator` | No — Wave 0 |
| CALC-02 | Per-step cost/value breakdown rendered in calculator | feature (Volt) | `php artisan test --filter=ShuffleBatchCalculator` | No — Wave 0 |
| CALC-03 | Profit summary rows computed correctly (cost, gross, cut, net) | unit (model) | `php artisan test --filter=ShuffleBatchCalculator` | No — Wave 0 |
| CALC-04 | Break-even input price computed correctly | unit (model) | `php artisan test --filter=ShuffleBatchCalculator` | No — Wave 0 |

**Note on Alpine tests:** CALC-01 and CALC-02 involve Alpine.js client-side rendering. Pest/Livewire Volt tests can assert the Blade HTML contains the correct `x-data` seeds (priceData JSON) and calculator section is present/absent based on step count. Full Alpine reactivity requires browser testing (not in scope — manual verification or Dusk).

The `profitPerUnit()` refactor (CALC-03/04) is unit-testable: create shuffles with known steps and snapshot prices, assert `profitPerUnit()` returns the correct cascaded copper value.

### Sampling Rate
- **Per task commit:** `php artisan test --filter=ShuffleBatchCalculator`
- **Per wave merge:** `php artisan test`
- **Phase gate:** Full suite green before `/gsd:verify-work`

### Wave 0 Gaps
- [ ] `tests/Feature/ShuffleBatchCalculatorTest.php` — covers INTG-02, INTG-03, CALC-01, CALC-02, CALC-03, CALC-04
  - Test: `priceData()` returns correct median_price and stale=false for fresh snapshots
  - Test: `priceData()` returns stale=true for snapshots > 1 hour old
  - Test: `profitPerUnit()` correctly cascades multi-step yield ratios
  - Test: `profitPerUnit()` returns null when first input or last output price is missing
  - Test: `calculatorSteps()` returns correct array shape with item names and icons
  - Test: Calculator section hidden when shuffle has no steps (Volt component render test)
  - Test: Calculator section visible when shuffle has steps

---

## Sources

### Primary (HIGH confidence)
- Project codebase directly inspected:
  - `app/Models/Shuffle.php` — existing `profitPerUnit()` logic and model structure
  - `app/Models/ShuffleStep.php` — field names: `input_qty`, `output_qty_min`, `output_qty_max`, `input_blizzard_item_id`, `output_blizzard_item_id`
  - `app/Models/CatalogItem.php` — `priceSnapshots()` relationship, `display_name` accessor
  - `app/Models/PriceSnapshot.php` — `median_price` (int), `polled_at` (datetime cast)
  - `app/Concerns/FormatsAuctionData.php` — `formatGold(int $copper): string` implementation
  - `resources/views/livewire/pages/shuffle-detail.blade.php` — existing Alpine patterns (`x-data`, `$wire`, `@js`, step card structure)
  - `resources/views/livewire/pages/shuffles.blade.php` — profitability badge rendering pattern
  - `tests/Feature/ShuffleStepEditorTest.php` — test conventions (Pest, Volt::actingAs, RefreshDatabase)
  - `phpunit.xml` — test framework configuration (Pest via PHPUnit, SQLite in-memory)

### Secondary (MEDIUM confidence)
- CONTEXT.md decisions locked by user during discussion phase
- REQUIREMENTS.md traceability table mapping INTG-02/03, CALC-01–04 to Phase 12

### Tertiary (LOW confidence)
- `wire:ignore` behavior with Alpine.js state preservation — based on Livewire 3.x documentation patterns; verify if batchQty actually resets during step saves before implementing localStorage fallback.

---

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — all libraries already in the project, confirmed by code inspection
- Architecture: HIGH — patterns derived directly from existing shuffle-detail.blade.php code
- Pitfalls: MEDIUM-HIGH — N+1 pitfall is well-known; Alpine/Livewire re-render interaction is LOW until confirmed by manual testing
- Math formulas: HIGH — straightforward integer arithmetic, verified against existing profitPerUnit() logic

**Research date:** 2026-03-04
**Valid until:** 2026-04-04 (stable stack, no fast-moving dependencies)
