# Architecture Research

**Domain:** WoW AH Tracker — Shuffles / Conversion Chain Milestone
**Researched:** 2026-03-04
**Confidence:** HIGH (based on direct codebase inspection)

## Context: What Already Exists

This is a subsequent-milestone document. v1.0 is shipped. The existing architecture is:

- **Models:** `User`, `WatchedItem`, `CatalogItem`, `PriceSnapshot`, `IngestionMetadata`
- **Key relationships:** `User hasMany WatchedItem`, `WatchedItem belongsTo CatalogItem` (via `blizzard_item_id`), `CatalogItem hasMany PriceSnapshot`
- **Livewire pages (Volt SFCs):** `pages.dashboard`, `pages.watchlist`, `pages.item-detail`
- **Routes:** `/dashboard`, `/watchlist`, `/item/{watchedItem}`
- **Background jobs:** `FetchCommodityDataJob` → `DispatchPriceBatchesJob` → `AggregatePriceBatchJob`
- **Price storage:** BIGINT copper (not float), composite index `(catalog_item_id, polled_at)`
- **Navigation:** `livewire/layout/navigation.blade.php` — currently only Dashboard and Watchlist links

The research below covers **only what is new or changed** for the Shuffles feature.

---

## System Overview: Shuffles Integration

```
┌─────────────────────────────────────────────────────────────────┐
│                     Existing (unchanged)                         │
│  Dashboard       Watchlist       Item Detail     Auth/Profile    │
├─────────────────────────────────────────────────────────────────┤
│                     NEW: Shuffles Section                        │
│  ┌──────────────────────┐   ┌─────────────────────────────────┐  │
│  │  pages.shuffles      │   │  pages.shuffle-detail (or       │  │
│  │  (index/list)        │   │   embedded in shuffles index)   │  │
│  │  - List all shuffles │   │  - Edit chain steps             │  │
│  │  - Create new        │   │  - Batch calculator             │  │
│  │  - Delete            │   │  - Profit summary               │  │
│  └──────────┬───────────┘   └────────────┬────────────────────┘  │
│             │                             │                       │
│  ┌──────────▼─────────────────────────────▼────────────────────┐  │
│  │              NEW Eloquent Models                             │  │
│  │  Shuffle         ShuffleStep        (existing) WatchedItem  │  │
│  │  (has many)  ->  (belongs to)       auto-created on step add│  │
│  └──────────────────────────────────────────────────────────────┘  │
├─────────────────────────────────────────────────────────────────┤
│                     Data Layer (new tables)                      │
│  ┌──────────────────┐   ┌──────────────────────────────────┐    │
│  │  shuffles        │   │  shuffle_steps                   │    │
│  │  id, user_id     │   │  id, shuffle_id, sort_order      │    │
│  │  name            │   │  input_item_id (catalog)         │    │
│  │  timestamps      │   │  output_item_id (catalog)        │    │
│  └──────────────────┘   │  input_qty, output_qty_min       │    │
│                          │  output_qty_max (nullable)       │    │
│                          │  timestamps                      │    │
│                          └──────────────────────────────────┘    │
└─────────────────────────────────────────────────────────────────┘
```

---

## New Models Required

### Shuffle

Represents one named conversion chain (e.g., "Algari Sendoff Shuffle").

```php
// app/Models/Shuffle.php
class Shuffle extends Model
{
    protected $fillable = ['user_id', 'name'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function steps(): HasMany
    {
        return $this->hasMany(ShuffleStep::class)->orderBy('sort_order');
    }
}
```

**Migration:**
```php
Schema::create('shuffles', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->string('name');
    $table->timestamps();
});
```

### ShuffleStep

Represents one step in a chain (e.g., "Mill 5x Luredrop → 1-3 Pigment").

```php
// app/Models/ShuffleStep.php
class ShuffleStep extends Model
{
    protected $fillable = [
        'shuffle_id',
        'sort_order',
        'input_catalog_item_id',
        'output_catalog_item_id',
        'input_qty',
        'output_qty_min',
        'output_qty_max',   // nullable: null means fixed yield = output_qty_min
    ];

    protected $casts = [
        'sort_order'       => 'integer',
        'input_qty'        => 'integer',
        'output_qty_min'   => 'integer',
        'output_qty_max'   => 'integer',
    ];

    public function shuffle(): BelongsTo
    {
        return $this->belongsTo(Shuffle::class);
    }

    public function inputItem(): BelongsTo
    {
        return $this->belongsTo(CatalogItem::class, 'input_catalog_item_id');
    }

    public function outputItem(): BelongsTo
    {
        return $this->belongsTo(CatalogItem::class, 'output_catalog_item_id');
    }
}
```

**Migration:**
```php
Schema::create('shuffle_steps', function (Blueprint $table) {
    $table->id();
    $table->foreignId('shuffle_id')->constrained()->cascadeOnDelete();
    $table->unsignedTinyInteger('sort_order')->default(0);
    $table->foreignId('input_catalog_item_id')->constrained('catalog_items');
    $table->foreignId('output_catalog_item_id')->constrained('catalog_items');
    $table->unsignedInteger('input_qty')->default(1);
    $table->unsignedInteger('output_qty_min');
    $table->unsignedInteger('output_qty_max')->nullable();
    $table->timestamps();

    $table->index(['shuffle_id', 'sort_order']);
});
```

---

## Existing Model Changes

### User (modified)

Add `shuffles()` relationship:

```php
public function shuffles(): HasMany
{
    return $this->hasMany(Shuffle::class);
}
```

### CatalogItem (unchanged schema, extended usage)

No schema changes. `CatalogItem` is already the source-of-truth for item identity. `ShuffleStep` references it directly via `input_catalog_item_id` / `output_catalog_item_id`. This is cleaner than going through `WatchedItem` because steps reference catalog items, not user-specific watched items.

---

## Relationship Map

```
User
 ├── hasMany WatchedItem          (existing — for watchlist + signals)
 └── hasMany Shuffle              (NEW)
          └── hasMany ShuffleStep
                   ├── belongsTo CatalogItem (as inputItem)
                   └── belongsTo CatalogItem (as outputItem)

CatalogItem
 ├── hasMany PriceSnapshot        (existing)
 ├── used as inputItem in ShuffleStep  (NEW, via FK)
 └── used as outputItem in ShuffleStep (NEW, via FK)
```

Key design decision: **ShuffleStep links to `CatalogItem`, not `WatchedItem`.**

Rationale: A step defines "what item is used/produced" — this is catalog identity. `WatchedItem` is a user preference record (thresholds, profession tag). The auto-watch side effect (creating `WatchedItem` when a step is added) is handled in the Livewire component, not baked into the data model.

---

## New Livewire Components

### `pages.shuffles` (index + create)

Single Volt SFC at `resources/views/livewire/pages/shuffles.blade.php`.

**Responsibilities:**
- List all shuffles for the authenticated user
- Create a new shuffle (name input → persist → redirect or inline expand)
- Delete a shuffle (with confirmation)
- Link to shuffle detail / calculator

**PHP class sketch:**
```php
new #[Layout('layouts.app')] class extends Component
{
    public string $name = '';

    #[Computed]
    public function shuffles(): Collection
    {
        return auth()->user()->shuffles()->withCount('steps')->orderBy('name')->get();
    }

    public function create(): void
    {
        $this->validate(['name' => 'required|string|max:100']);
        auth()->user()->shuffles()->create(['name' => $this->name]);
        $this->name = '';
    }

    public function delete(int $id): void
    {
        auth()->user()->shuffles()->findOrFail($id)->delete();
    }
};
```

### `pages.shuffle-detail` (step editor + calculator)

Volt SFC at `resources/views/livewire/pages/shuffle-detail.blade.php`.

Route: `GET /shuffles/{shuffle}` with model binding scoped to `auth()->user()->shuffles()`.

**Responsibilities:**
- Display/edit all steps for a shuffle (reorder, add, remove steps)
- Each step: pick input item (CatalogItem search combobox, same pattern as Watchlist), pick output item, set qty/ratio
- When a step is saved: ensure both input and output `CatalogItem` IDs exist as `WatchedItem` for the user (auto-watch)
- Batch calculator: user enters input quantity → component computes per-step yields and profit using latest `median_price` from `PriceSnapshot`
- Profit summary: total cost in (copper), total value out (copper), net margin

**PHP class sketch:**
```php
new #[Layout('layouts.app')] class extends Component
{
    public Shuffle $shuffle;

    // Calculator state
    public int $batchQuantity = 1;

    public function mount(Shuffle $shuffle): void
    {
        abort_unless($shuffle->user_id === auth()->id(), 403);
        $this->shuffle = $shuffle;
    }

    #[Computed]
    public function steps(): Collection
    {
        return $this->shuffle->steps()->with(['inputItem.priceSnapshots' => fn ($q) => $q->latest('polled_at')->limit(1), 'outputItem.priceSnapshots' => fn ($q) => $q->latest('polled_at')->limit(1)])->get();
    }

    #[Computed]
    public function profitBreakdown(): array
    {
        // Returns per-step cost/yield/margin arrays for the calculator display
        // Calculation logic lives here or in a dedicated Action class
    }

    public function addStep(...): void { /* validate, persist, auto-watch */ }
    public function removeStep(int $id): void { /* delete step */ }
    public function reorderSteps(array $orderedIds): void { /* update sort_order */ }
};
```

---

## Auto-Watch Integration

When a step is saved (added or updated), the component must ensure both items are watched by the user. This prevents the calculator from showing no price data for items in a shuffle.

**Implementation location:** Inside the `addStep()` / `updateStep()` methods in the Volt component, NOT in the model observer or event listener.

**Why not observers:** Adding a `WatchedItem` silently in a model observer makes the behavior invisible and harder to test. Keeping it explicit in the component method is obvious and controllable.

```php
private function ensureWatched(int $catalogItemId): void
{
    $catalog = CatalogItem::findOrFail($catalogItemId);

    auth()->user()->watchedItems()->firstOrCreate(
        ['blizzard_item_id' => $catalog->blizzard_item_id],
        ['name' => $catalog->name, 'buy_threshold' => 10, 'sell_threshold' => 10]
    );
}
```

This mirrors the existing `addFromCatalog()` pattern in `pages.watchlist` exactly.

---

## Profit Calculation Data Flow

```
User sets $batchQuantity = 100
    ↓
$this->profitBreakdown (Computed property recalculates)
    ↓
For each ShuffleStep (ordered by sort_order):
    latestInputPrice  = step->inputItem->priceSnapshots->first()->median_price  (copper)
    latestOutputPrice = step->outputItem->priceSnapshots->first()->median_price (copper)

    inputCostCopper  = latestInputPrice * inputQty * batchQuantity
    outputYieldMin   = outputQtyMin * batchQuantity
    outputYieldMax   = (outputQtyMax ?? outputQtyMin) * batchQuantity
    outputValueMin   = latestOutputPrice * outputYieldMin
    outputValueMax   = latestOutputPrice * outputYieldMax
    netProfitMin     = outputValueMin - inputCostCopper
    netProfitMax     = outputValueMax - inputCostCopper
    ↓
Totals: sum all steps' inputCost, outputValueMin, outputValueMax
    ↓
Display using existing formatGold() from FormatsAuctionData trait
```

**Key constraint:** All arithmetic stays in integer copper. Never convert to float mid-calculation. Only convert to gold/silver/copper display at render time via `formatGold()`.

**Where this lives:** The `profitBreakdown()` computed property in the Volt component is sufficient for v1.1. If it grows complex, extract to `app/Actions/ShuffleProfitAction.php` — same pattern as `PriceAggregateAction`.

---

## Navigation Changes

File to modify: `resources/views/livewire/layout/navigation.blade.php`

Add "Shuffles" nav link in both the desktop links section and the responsive mobile menu section — same pattern as the existing Watchlist link.

```blade
<x-nav-link :href="route('shuffles')" :active="request()->routeIs('shuffles*')" wire:navigate>
    {{ __('Shuffles') }}
</x-nav-link>
```

Note: `routeIs('shuffles*')` (wildcard) keeps the link active on both `/shuffles` and `/shuffles/{id}`.

---

## Route Changes

File to modify: `routes/web.php`

```php
Volt::route('/shuffles', 'pages.shuffles')
    ->middleware(['auth'])
    ->name('shuffles');

Volt::route('/shuffles/{shuffle}', 'pages.shuffle-detail')
    ->middleware(['auth'])
    ->name('shuffles.detail');
```

Route model binding on `{shuffle}` will resolve to a `Shuffle` model automatically. Authorization check (owner = auth user) happens in `mount()`.

---

## New File Inventory

### New Files

| Path | Type | Purpose |
|------|------|---------|
| `app/Models/Shuffle.php` | Model | Named conversion chain |
| `app/Models/ShuffleStep.php` | Model | One step in a chain |
| `database/migrations/YYYY_MM_DD_create_shuffles_table.php` | Migration | `shuffles` table |
| `database/migrations/YYYY_MM_DD_create_shuffle_steps_table.php` | Migration | `shuffle_steps` table |
| `database/factories/ShuffleFactory.php` | Factory | Test seeding |
| `database/factories/ShuffleStepFactory.php` | Factory | Test seeding |
| `resources/views/livewire/pages/shuffles.blade.php` | Volt SFC | Shuffle list + create |
| `resources/views/livewire/pages/shuffle-detail.blade.php` | Volt SFC | Step editor + calculator |

### Modified Files

| Path | Change |
|------|--------|
| `app/Models/User.php` | Add `shuffles(): HasMany` relationship |
| `resources/views/livewire/layout/navigation.blade.php` | Add Shuffles nav link (desktop + mobile) |
| `routes/web.php` | Add two new Volt routes |

### Optionally New (if calculation complexity warrants)

| Path | Type | Purpose |
|------|------|---------|
| `app/Actions/ShuffleProfitAction.php` | Action | Encapsulate profit calculation for testability |

---

## Build Order

Dependencies determine this order. Each step unblocks the next.

```
1. Migrations + Models
   shuffles table → shuffle_steps table
   Shuffle model → ShuffleStep model → User::shuffles() relationship

2. Factories
   ShuffleFactory → ShuffleStepFactory
   (Needed before feature tests can run)

3. Shuffles Index Page (pages.shuffles)
   List / create / delete — no calculator yet
   Validates: route, auth scoping, basic CRUD

4. Navigation Link
   Add to navigation.blade.php
   Validates: link appears, active state works

5. Shuffle Detail Page skeleton (pages.shuffle-detail)
   Mount + authorization check + step list display
   No calculator yet — just renders steps

6. Step Add/Remove with Item Search
   Reuse CatalogItem combobox pattern from Watchlist
   Auto-watch implementation here

7. Batch Calculator
   batchQuantity input → profitBreakdown computed property
   formatGold() for display

8. Profit Summary
   Sum totals across steps, render summary row
```

**Critical path:** Steps 1 → 2 → 3 must complete before any feature tests can be written. Step 6 (item search + auto-watch) is the highest-complexity step and should be built before the calculator — bad data in steps makes calculator results meaningless.

---

## Reuse Points from v1.0

These v1.0 patterns apply directly to the Shuffles feature without modification:

| Pattern | Where Reused |
|---------|-------------|
| CatalogItem name search combobox | Step add: pick input/output items by name |
| `firstOrCreate` for WatchedItem | Auto-watch in `addStep()` |
| `FormatsAuctionData::formatGold()` | Calculator display |
| `#[Computed]` property pattern | `steps()` and `profitBreakdown()` |
| `#[Layout('layouts.app')]` | Both new Volt SFCs |
| `abort_unless(owner check, 403)` | `mount()` in shuffle-detail |
| BIGINT copper arithmetic (no float) | All profit calculations |
| `wire:key` on list items | Step list rendering |

---

## Anti-Patterns to Avoid

### Anti-Pattern 1: Storing Prices on ShuffleStep

**What people do:** Add `input_price_copper` / `output_price_copper` columns to `shuffle_steps` and update them periodically.

**Why it's wrong:** Prices are already live in `price_snapshots`. Duplicating them creates staleness — the "cached" price on the step drifts from the snapshot price. The calculator would show wrong profit until the cache is updated.

**Do this instead:** Always pull the latest `median_price` from `PriceSnapshot` at calculator render time. The `priceSnapshots` eager load with `latest('polled_at')->limit(1)` keeps this efficient.

### Anti-Pattern 2: Float Arithmetic for Profit

**What people do:** Convert copper to gold (`$price / 10000`) to make the math "easier," then multiply by quantity.

**Why it's wrong:** Floating-point rounding accumulates across multi-step chains. `58.33g * 500 units` in float can diverge from the integer result by several copper per step, magnified across the batch.

**Do this instead:** All multiplication/addition in integer copper throughout. Convert to `g/s/c` display only in `formatGold()` at the final render step.

### Anti-Pattern 3: Modeling Steps as a Linked List

**What people do:** Add a `previous_step_id` / `next_step_id` to each step to represent ordering.

**Why it's wrong:** Linked-list reordering requires multi-row updates and is hard to query in order. SQLite does not support deferred constraint checking the same way PostgreSQL does, making linked-list reorder awkward.

**Do this instead:** Use an integer `sort_order` column. Reordering updates the `sort_order` of affected rows in a single transaction. Gaps in sort_order are fine — query with `ORDER BY sort_order ASC`.

### Anti-Pattern 4: One Livewire Component Per Step

**What people do:** Create a separate Livewire/Volt component for each step row to handle its own edit state.

**Why it's wrong:** Livewire components have network round-trip overhead per interaction. For a 5-step chain, that's 5 separate component trees to maintain. The step data is small and belongs in one component.

**Do this instead:** Manage all step edit state in the parent `shuffle-detail` Volt component using public arrays or inline Alpine.js for transient UI state (e.g., showing/hiding an edit form for a specific step row).

---

## Integration Points Summary

| Touch Point | New or Modified | Notes |
|-------------|-----------------|-------|
| `shuffles` table | NEW | Owned by user, cascades on delete |
| `shuffle_steps` table | NEW | Cascades from shuffle delete |
| `Shuffle` model | NEW | Simple — no complex logic |
| `ShuffleStep` model | NEW | Holds ratio/yield config |
| `User::shuffles()` | MODIFIED | Add `HasMany` relationship |
| `pages.shuffles` Volt SFC | NEW | List + create |
| `pages.shuffle-detail` Volt SFC | NEW | Steps + calculator |
| Navigation blade | MODIFIED | Add Shuffles link |
| `routes/web.php` | MODIFIED | Add 2 routes |
| `WatchedItem` (auto-create) | MODIFIED behavior | Called from addStep(), not schema change |
| `CatalogItem` | UNCHANGED | Used as FK target for step items |
| `PriceSnapshot` | UNCHANGED | Queried by calculator for live prices |
| Background jobs | UNCHANGED | Items auto-watched → automatically polled |

---

## Sources

- Existing codebase inspection (direct — HIGH confidence): `/app/Models/`, `/resources/views/livewire/`, `/routes/web.php`, `/database/migrations/`
- Livewire Volt SFC pattern (matches existing pages): `resources/views/livewire/pages/watchlist.blade.php` — used as implementation reference
- Laravel route model binding scoping: https://laravel.com/docs/12.x/routing#implicit-model-binding-scoping
- FormatsAuctionData trait (existing): `app/Concerns/FormatsAuctionData.php`

---
*Architecture research for: WoW AH Tracker v1.1 Shuffles integration*
*Researched: 2026-03-04*
