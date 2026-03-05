# Phase 11: Step Editor, Yield Config, and Auto-Watch - Research

**Researched:** 2026-03-04
**Domain:** Laravel Livewire Volt SFC, Alpine.js inline editing, shuffle step CRUD, auto-watch provenance
**Confidence:** HIGH

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions

**Item selection UX**
- Search-as-you-type for both input and output items, reusing the same CatalogItem search pattern from the watchlist page
- When adding a new step, the output of the previous step auto-fills as the new step's input (editable)
- "Add Step" button below the step list to append a new step (not an always-visible blank row)
- Each step saves individually (inline save), consistent with the inline rename pattern on the detail page

**Step list layout and reordering**
- Vertical card layout with arrow/connector between cards showing chain flow (input → output per card)
- WoW item icons (from CatalogItem `icon_url`) displayed alongside item names on each step card
- Up/down arrow buttons for reordering steps (no drag-and-drop / JS library)
- Each step has a delete button — removing a step auto-renumbers sort_order, no confirmation prompt
- Chain connections are by sort_order position, not linked input/output references

**Yield input design**
- Single number field by default (fixed yield), with a toggle or "Set range" link to expand to min/max fields
- When min = max, yield is fixed; when different, yield is a range
- Label format: "Yield: X" (fixed) or "Yield: X-Y" (range) — simple and scannable
- Basic validation: min >= 1, max >= min; inline error shown, prevents saving invalid yields
- New `input_qty` column needed — add unsigned integer column to `shuffle_steps` so users can express ratios like "5 ore → 1 gem" (input_qty=5, output_qty_min=1). Default value: 1

**Auto-watch behavior**
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

### Deferred Ideas (OUT OF SCOPE)
None — discussion stayed within phase scope
</user_constraints>

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| SHUF-02 | User can define multi-step conversion chains (A → B → C) | Step CRUD actions on shuffle-detail Volt component; sort_order-based chain; "Add Step" appends new card with previous step's output auto-filled as input |
| YILD-01 | User can set a fixed yield ratio per conversion step | input_qty + output_qty_min = output_qty_max = fixed value; migration adds input_qty column; ShuffleStep fillable/casts updated |
| YILD-02 | User can set min/max yield range per step for probabilistic conversions | output_qty_min != output_qty_max; "Set range" toggle reveals second field; validation min >= 1, max >= min |
| YILD-03 | User can reorder steps within a chain and the new order is saved | Up/down arrow buttons call moveStepUp/moveStepDown Livewire actions; renumber sort_order contiguously |
| INTG-01 | Items added to a shuffle are auto-watched using firstOrCreate with provenance | Auto-watch on step save using user->watchedItems()->firstOrCreate(); orphan cleanup on step delete mirrors Shuffle::deleting() boot event |
</phase_requirements>

## Summary

Phase 11 replaces the "Step editor coming soon" placeholder in `shuffle-detail.blade.php` with a fully functional step editor. The work is entirely within the existing Volt SFC + Alpine.js + Livewire stack — no new libraries or frameworks are required. All foundational data models (`Shuffle`, `ShuffleStep`, `WatchedItem`, `CatalogItem`) are already implemented from Phase 9; the only schema change is a single `input_qty` column migration on `shuffle_steps`.

The phase has three logical concerns: (1) step CRUD UI — adding, displaying, deleting, and reordering steps with item search; (2) yield configuration — input_qty + output_qty_min/max fields with range toggle and validation; (3) auto-watch integration — silently calling `firstOrCreate` on save and running orphan cleanup on delete. The orphan cleanup pattern already exists on the `Shuffle::deleting()` event and needs to be extended to per-step deletion.

**Primary recommendation:** Expand `shuffle-detail.blade.php` as a single Volt SFC with all step editor logic; reuse the CatalogItem combobox pattern verbatim from `watchlist.blade.php`; extend the existing `Shuffle::boot()` orphan cleanup to cover single-step deletion via a `ShuffleStep::deleting()` boot event.

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| Livewire Volt SFC | Already installed | Server-side step CRUD actions | All pages use Volt SFC pattern; no new tooling |
| Alpine.js | Already installed | Item search dropdown open/close state, range toggle, inline editing UX | All existing inline editing uses Alpine; no JS build step |
| Laravel Eloquent | Already installed | ShuffleStep model queries, firstOrCreate, sort_order renumbering | Project ORM |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| `x-tier-pip` component | Existing | Show quality tier pip on item search results | Used in watchlist combobox; reuse in step item search results |
| `FormatsAuctionData` concern | Existing | Gold formatting if prices displayed on step cards | Already used in shuffle-detail; available via `use` |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Up/down arrow buttons | SortableJS / drag-and-drop | User decided buttons only — avoids JS library dependency, simpler |
| Inline step save | Modal form | User decided inline save to match existing rename pattern |
| Single Volt component | Extracted Livewire component | Single component simpler; steps are scoped to one page |

**No new packages to install.** All dependencies are already present.

## Architecture Patterns

### Recommended Project Structure
```
resources/views/livewire/pages/
└── shuffle-detail.blade.php    # Expanded from shell to full step editor

database/migrations/
└── 2026_03_06_000000_add_input_qty_to_shuffle_steps.php  # New migration

app/Models/
└── ShuffleStep.php             # Add input_qty to $fillable, $casts, boot() for orphan cleanup
```

### Pattern 1: Volt SFC with computed steps list
**What:** `#[Computed]` property loads steps with eager-loaded CatalogItem relationships.
**When to use:** Any time the step list needs to re-render after add/delete/reorder.
**Example:**
```php
// Source: existing shuffle-detail.blade.php + ShuffleDataFoundationTest.php
#[Computed]
public function steps(): Collection
{
    return $this->shuffle->steps()
        ->with(['inputCatalogItem', 'outputCatalogItem'])
        ->get();
}
```

### Pattern 2: CatalogItem search combobox (reuse from watchlist)
**What:** `wire:model.live.debounce.200ms` on a text input drives a `#[Computed]` suggestions array; Alpine `x-data="{ open: false }"` + `@click.outside` controls dropdown visibility.
**When to use:** Item search on each step's input and output fields.
**Example:**
```php
// Source: watchlist.blade.php lines 23-54 — copy this pattern
#[Computed]
public function inputSuggestions(): array
{
    if (strlen($this->inputSearch) < 2) {
        return [];
    }
    return CatalogItem::where('name', 'like', "%{$this->inputSearch}%")
        ->orderBy('name')
        ->get(['id', 'name', 'blizzard_item_id', 'icon_url', 'quality_tier', 'rarity'])
        ->toArray();
}
```

**Challenge with multiple steps:** Each step needs its own search state. Options:
- Keep search state as component properties keyed by step id (e.g., `$inputSearches = []`, `$outputSearches = []`)
- Use Alpine-only filtering if search targets a small static list (not applicable here — CatalogItem is large)
- **Recommended:** Store `$addingStepInputSearch` and `$addingStepOutputSearch` as component properties only for the "new step" form. Existing steps show their saved item names and only open a new search when clicking an edit control.

### Pattern 3: Inline save for step fields
**What:** Livewire action called via `$wire.call()` from Alpine on blur/enter, same as shuffle rename.
**When to use:** Saving yield qty fields, saving item selection on a step.
**Example:**
```php
// Source: shuffle-detail.blade.php lines 78-80 — same pattern
public function saveStep(int $stepId, int $inputQty, int $outputQtyMin, int $outputQtyMax): void
{
    $step = $this->shuffle->steps()->findOrFail($stepId);
    // validate
    $step->update([...]);
}
```

### Pattern 4: Sort order renumbering
**What:** After up/down move or delete, re-assign sort_order as 0,1,2,... contiguously.
**When to use:** Any action that changes step order or removes a step.
**Example:**
```php
public function moveStepUp(int $stepId): void
{
    $steps = $this->shuffle->steps()->get(); // already ordered by sort_order
    $index = $steps->search(fn ($s) => $s->id === $stepId);
    if ($index < 1) return;

    // Swap sort_order with previous step
    [$steps[$index]->sort_order, $steps[$index - 1]->sort_order] =
        [$steps[$index - 1]->sort_order, $steps[$index]->sort_order];

    $steps[$index]->save();
    $steps[$index - 1]->save();
}
```
After delete, renumber all remaining steps: `$this->shuffle->steps()->get()->each(fn ($s, $i) => $s->update(['sort_order' => $i]));`

### Pattern 5: Auto-watch via firstOrCreate on step save
**What:** After saving a step, call `firstOrCreate` for both input and output items on the user's watchedItems relation.
**When to use:** Every time a step is saved (add or update).
**Example:**
```php
// Source: watchlist.blade.php lines 56-65 — same firstOrCreate pattern
private function autoWatch(int $blizzardItemId, ?string $name): void
{
    auth()->user()->watchedItems()->firstOrCreate(
        ['blizzard_item_id' => $blizzardItemId],
        [
            'name'                  => $name ?? "Item #{$blizzardItemId}",
            'buy_threshold'         => null,
            'sell_threshold'        => null,
            'created_by_shuffle_id' => $this->shuffle->id,
        ]
    );
}
```
Note: `buy_threshold` and `sell_threshold` are set to `null` for auto-watched items (price tracking only). The existing `WatchedItem` model has both columns nullable per the migration.

### Pattern 6: Per-step orphan cleanup on step delete
**What:** Extend orphan cleanup to run when a single step is deleted, not only when the whole shuffle is deleted.
**When to use:** `deleteStep()` Livewire action.
**Example:**
```php
// Source: Shuffle::boot() deleting event — mirror this in ShuffleStep::boot()
// OR run cleanup inline in deleteStep() action before calling $step->delete()
public function deleteStep(int $stepId): void
{
    $step = $this->shuffle->steps()->findOrFail($stepId);

    // Orphan cleanup: items used by this step that no other step in any shuffle references
    $itemIds = [$step->input_blizzard_item_id, $step->output_blizzard_item_id];
    $step->delete();
    $this->renumberSortOrder();

    foreach ($itemIds as $blizzardItemId) {
        $stillReferenced = ShuffleStep::where('input_blizzard_item_id', $blizzardItemId)
            ->orWhere('output_blizzard_item_id', $blizzardItemId)
            ->exists();

        if (!$stillReferenced) {
            WatchedItem::where('blizzard_item_id', $blizzardItemId)
                ->whereNotNull('created_by_shuffle_id')
                ->delete();
        }
    }
}
```

### Anti-Patterns to Avoid
- **Drag-and-drop library:** User explicitly decided against it. Use up/down arrow buttons only.
- **Confirmation dialog on step delete:** User decided no confirmation prompt. Delete immediately.
- **Toast/notification on auto-watch:** User decided auto-watch is silent. No toast.
- **Always-visible blank new-step row:** User decided "Add Step" button appends a new form. No persistent empty row.
- **Re-querying steps on every keystroke:** Use debounce (200ms) on search inputs; never query on each character synchronously.
- **Storing full item objects in Livewire public properties:** Store only scalar IDs; fetch items via `#[Computed]`. Livewire serializes public properties between requests — objects cause bloat.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Item search dropdown | Custom search component | Reuse `watchlist.blade.php` combobox pattern verbatim | Already battle-tested; same CatalogItem query; Alpine open/close already works |
| Orphan cleanup on step delete | Custom tracking table | Mirror `Shuffle::boot() deleting` subquery logic | Existing pattern handles all edge cases; unit tested |
| sort_order gap prevention | Complex reorder algorithm | Simple renumber-all after any change | Contiguous 0,1,2,... is trivially correct; no gaps to manage |
| Auto-watch deduplication | Custom check | `firstOrCreate` on `watchedItems()` | Built-in; existing tests confirm behavior |

**Key insight:** Every hard problem in this phase is already solved by existing code patterns. The task is composition and extension, not invention.

## Common Pitfalls

### Pitfall 1: Multiple search state properties for step forms
**What goes wrong:** Each step card might need its own search string and dropdown open state. Naively adding `$inputSearch` and `$outputSearch` as single strings breaks when multiple steps are being edited simultaneously.
**Why it happens:** Livewire components have a flat property space; multiple steps share the same component instance.
**How to avoid:** Scope search properties to the "new step being added" form only. Existing saved steps display their item name statically; clicking "edit" on a saved item opens a search only for that one step using an Alpine-local state + a single Livewire property for the active search target.
**Warning signs:** Two step cards' search dropdowns interfering with each other.

### Pitfall 2: Orphan cleanup deletes user-manually-watched items
**What goes wrong:** A user manually adds ore to their watchlist, then creates a shuffle using ore. Deleting the shuffle step removes the manual watch entry.
**Why it happens:** Orphan cleanup doesn't check `created_by_shuffle_id`.
**How to avoid:** The cleanup query must filter `WHERE created_by_shuffle_id IS NOT NULL` — only auto-watch entries are eligible for orphan removal. The existing `Shuffle::boot()` pattern already does this correctly via `where('created_by_shuffle_id', $shuffle->id)`. The per-step version must also filter on `whereNotNull('created_by_shuffle_id')`.
**Warning signs:** `ShuffleDataFoundationTest.php` test "deleting a shuffle preserves manually-watched items" would catch this.

### Pitfall 3: sort_order swap collides on unique constraint
**What goes wrong:** If `sort_order` has a unique constraint, swapping A(0) and B(1) by setting A=1 first violates uniqueness before B is set to 0.
**Why it happens:** Database constraint checked at statement level, not transaction end.
**How to avoid:** Check the migration — `shuffle_steps` uses a composite index `['shuffle_id', 'sort_order']` (not a unique constraint), so swaps are safe. Confirm there is no unique constraint before implementing.
**Warning signs:** `SQLSTATE[23000] Integrity constraint violation` on move operations.

### Pitfall 4: `input_qty` column missing from watched_items validation
**What goes wrong:** Saving a step with `input_qty` fails because the column doesn't exist yet.
**Why it happens:** Migration not run, or `input_qty` not added to `$fillable`.
**How to avoid:** Wave 0 of the plan must create the migration and update `ShuffleStep::$fillable` and `$casts` before any step save logic is implemented.
**Warning signs:** `Mass assignment` or `Unknown column` errors during step save.

### Pitfall 5: Auto-fill of previous step's output breaks on empty chain
**What goes wrong:** "Add Step" tries to read `$previousStep->output_blizzard_item_id` when no steps exist yet.
**Why it happens:** The auto-fill logic doesn't guard for empty chain.
**How to avoid:** Auto-fill only when `$this->steps->isNotEmpty()`. For the first step, both input and output search start empty.
**Warning signs:** `Trying to get property of non-object` error on first step add.

### Pitfall 6: `#[Computed]` steps not refreshing after mutation
**What goes wrong:** After adding or deleting a step, the step list doesn't update in the UI.
**Why it happens:** Livewire caches `#[Computed]` properties per request; calling `unset($this->steps)` (or using `$this->unsetComputedProperties()`) is required to invalidate the cache after mutations.
**How to avoid:** After every mutation (add, delete, move, save), call `unset($this->steps)` to force re-computation on next render.
**Warning signs:** UI shows stale step list after actions.

## Code Examples

Verified patterns from existing codebase:

### CatalogItem search combobox (watchlist.blade.php, reuse verbatim)
```php
// Source: watchlist.blade.php lines 23-54
#[Computed]
public function catalogSuggestions(): array
{
    if (strlen($this->search) < 2) {
        return [];
    }

    $items = CatalogItem::where('name', 'like', "%{$this->search}%")
        ->orderBy('name')
        ->orderBy('quality_tier')
        ->get(['id', 'name', 'blizzard_item_id', 'icon_url', 'quality_tier', 'rarity']);

    return $items->groupBy('name')
        ->take(15)
        ->flatMap(function ($group) {
            $icon = $group->firstWhere('icon_url', '!=', null)?->icon_url;
            $rarity = $group->firstWhere('rarity', '!=', null)?->rarity;
            return $group->map(fn ($item) => [
                'id'               => $item->id,
                'name'             => $item->name,
                'blizzard_item_id' => $item->blizzard_item_id,
                'icon_url'         => $item->icon_url ?? $icon,
                'quality_tier'     => $item->quality_tier,
                'rarity'           => $item->rarity ?? $rarity,
            ]);
        })
        ->values()
        ->toArray();
}
```

### Combobox Alpine template (watchlist.blade.php lines 120-168)
```html
<!-- Source: watchlist.blade.php -->
<div class="relative flex-1" x-data="{ open: false }" @click.outside="open = false">
    <input
        type="text"
        wire:model.live.debounce.200ms="search"
        @focus="open = true"
        @input="open = true"
        placeholder="Search items by name..."
        class="w-full rounded-md border border-gray-600 bg-wow-darker px-3 py-2 text-gray-100 placeholder-gray-500 focus:border-wow-gold focus:ring-wow-gold sm:text-sm"
    />
    @if (count($this->catalogSuggestions) > 0)
        <ul x-show="open" class="absolute z-50 mt-1 max-h-60 w-full overflow-y-auto rounded-md border border-gray-600 bg-wow-darker shadow-lg" x-cloak>
            @foreach ($this->catalogSuggestions as $item)
                <li wire:click="addFromCatalog({{ $item['id'] }})" @click="open = false"
                    class="flex cursor-pointer items-center gap-2 px-3 py-2 text-sm text-gray-200 hover:bg-wow-dark">
                    @if ($item['icon_url'])
                        <img src="{{ $item['icon_url'] }}" alt="" class="h-6 w-6 rounded" loading="lazy" />
                    @endif
                    <span class="...">{{ $item['name'] }}</span>
                </li>
            @endforeach
        </ul>
    @endif
</div>
```

### WatchedItem firstOrCreate pattern (watchlist.blade.php lines 56-65)
```php
// Source: watchlist.blade.php
auth()->user()->watchedItems()->firstOrCreate(
    ['blizzard_item_id' => $catalog->blizzard_item_id],
    ['name' => $catalog->name, 'buy_threshold' => null, 'sell_threshold' => null,
     'created_by_shuffle_id' => $this->shuffle->id]
);
```

### Orphan cleanup subquery (Shuffle.php lines 69-84)
```php
// Source: app/Models/Shuffle.php
$orphanIds = WatchedItem::where('created_by_shuffle_id', $shuffle->id)
    ->whereNotIn('id', function ($query) use ($shuffle) {
        $query->select('watched_items.id')
            ->from('watched_items')
            ->join('shuffle_steps as ss', function ($join) {
                $join->on('watched_items.blizzard_item_id', '=', 'ss.input_blizzard_item_id')
                    ->orOn('watched_items.blizzard_item_id', '=', 'ss.output_blizzard_item_id');
            })
            ->join('shuffles', 'ss.shuffle_id', '=', 'shuffles.id')
            ->where('shuffles.id', '!=', $shuffle->id);
    })
    ->pluck('id');
```

### Inline edit save with Alpine blur/enter (shuffle-detail.blade.php lines 78-80)
```html
<!-- Source: shuffle-detail.blade.php -->
@keydown.enter="$wire.renameShuffle(name); editing = false; saved = true; setTimeout(() => saved = false, 1500)"
@blur="$wire.renameShuffle(name); editing = false; saved = true; setTimeout(() => saved = false, 1500)"
```

### Migration: add input_qty to shuffle_steps
```php
// New migration: database/migrations/2026_03_06_000000_add_input_qty_to_shuffle_steps.php
Schema::table('shuffle_steps', function (Blueprint $table) {
    $table->unsignedInteger('input_qty')->default(1)->after('output_blizzard_item_id');
});
```

### ShuffleStep model update
```php
// app/Models/ShuffleStep.php — add to $fillable and $casts
protected $fillable = [
    'shuffle_id',
    'input_blizzard_item_id',
    'output_blizzard_item_id',
    'input_qty',           // NEW
    'output_qty_min',
    'output_qty_max',
    'sort_order',
];

protected $casts = [
    'input_blizzard_item_id'  => 'integer',
    'output_blizzard_item_id' => 'integer',
    'input_qty'               => 'integer',   // NEW
    'output_qty_min'          => 'integer',
    'output_qty_max'          => 'integer',
    'sort_order'              => 'integer',
];
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Steps placeholder UI | Full step editor | Phase 11 | Replaces empty state with functional CRUD |
| No input quantity | input_qty column on shuffle_steps | Phase 11 migration | Enables "5 ore → 1 gem" style ratios |
| Orphan cleanup only on shuffle delete | Also runs on per-step delete | Phase 11 | Keeps watchlist clean when user removes individual steps |

**Existing and unchanged:**
- `output_qty_min` / `output_qty_max` schema: already in place from Phase 9
- `created_by_shuffle_id` provenance on `watched_items`: already in place from Phase 9
- `Shuffle::steps()` ordered by `sort_order`: already in place

## Open Questions

1. **`buy_threshold` and `sell_threshold` nullability for auto-watched items**
   - What we know: CONTEXT.md says "null thresholds — price tracking only"
   - What's unclear: The current `watchlist.blade.php` `addFromCatalog()` sets thresholds to 10 (non-null). The migration needs to confirm both columns are nullable.
   - Recommendation: Check the `create_watched_items_table` migration. If columns are `NOT NULL DEFAULT 10`, the auto-watch `firstOrCreate` should pass `null` only if the column accepts null. If not nullable, use a sentinel value or alter the column. Given CONTEXT.md explicitly says null, the migration likely allows it — verify before coding.

2. **Per-step search state when editing an existing step's item**
   - What we know: New step form has clear dedicated search properties. But what about editing an already-saved step's item?
   - What's unclear: Whether the UX allows re-selecting a step's item after initial save (beyond yield qty edits).
   - Recommendation: For Phase 11 scope, item selection on an existing step is read-only after save (display name + icon). Only yield fields are editable inline. Users who want to change items should delete and re-add the step. This simplifies state management significantly.

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | Pest PHP (existing) |
| Config file | `phpunit.xml` + `tests/Pest.php` |
| Quick run command | `php artisan test --filter ShuffleStep` |
| Full suite command | `php artisan test` |

### Phase Requirements → Test Map
| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| SHUF-02 | User can add a step; chain of 2+ steps saved in order | Feature (Volt) | `php artisan test --filter ShuffleStepEditorTest` | Wave 0 |
| SHUF-02 | Output of previous step auto-fills as next step's input | Feature (Volt) | `php artisan test --filter ShuffleStepEditorTest` | Wave 0 |
| YILD-01 | Fixed yield (input_qty + equal min/max) saves correctly | Feature (Volt) | `php artisan test --filter ShuffleStepEditorTest` | Wave 0 |
| YILD-02 | Min/max yield range saves when min != max; rejects invalid range | Feature (Volt) | `php artisan test --filter ShuffleStepEditorTest` | Wave 0 |
| YILD-03 | Move step up/down renumbers sort_order correctly | Feature (Volt) | `php artisan test --filter ShuffleStepEditorTest` | Wave 0 |
| YILD-03 | Delete step renumbers remaining steps | Feature (Volt) | `php artisan test --filter ShuffleStepEditorTest` | Wave 0 |
| INTG-01 | Saving a step auto-watches both input and output items via firstOrCreate | Feature (Volt) | `php artisan test --filter ShuffleStepEditorTest` | Wave 0 |
| INTG-01 | Auto-watch does not overwrite existing manual watch thresholds | Feature (Volt) | `php artisan test --filter ShuffleStepEditorTest` | Wave 0 |
| INTG-01 | Deleting a step removes orphan auto-watched items | Feature (Volt) | `php artisan test --filter ShuffleStepEditorTest` | Wave 0 |
| INTG-01 | Deleting a step preserves items still referenced by other shuffles | Feature (Volt) | `php artisan test --filter ShuffleStepEditorTest` | Wave 0 |
| INTG-01 | Deleting a step preserves manually-watched items | Feature (Volt) | `php artisan test --filter ShuffleStepEditorTest` | Wave 0 |

### Sampling Rate
- **Per task commit:** `php artisan test --filter ShuffleStep`
- **Per wave merge:** `php artisan test`
- **Phase gate:** Full suite green before `/gsd:verify-work`

### Wave 0 Gaps
- [ ] `tests/Feature/ShuffleStepEditorTest.php` — covers all SHUF-02, YILD-01/02/03, INTG-01 behaviors above
- [ ] Migration `add_input_qty_to_shuffle_steps` must exist and be run before any other task

## Sources

### Primary (HIGH confidence)
- Direct code inspection: `app/Models/Shuffle.php` — orphan cleanup pattern, steps relationship
- Direct code inspection: `app/Models/ShuffleStep.php` — existing schema, relationships
- Direct code inspection: `app/Models/WatchedItem.php` — firstOrCreate shape, created_by_shuffle_id
- Direct code inspection: `resources/views/livewire/pages/watchlist.blade.php` — combobox pattern to reuse verbatim
- Direct code inspection: `resources/views/livewire/pages/shuffle-detail.blade.php` — Alpine inline edit pattern, placeholder to replace
- Direct code inspection: `database/migrations/2026_03_05_100001_create_shuffle_steps_table.php` — confirms no unique constraint on sort_order
- Direct code inspection: `tests/Feature/ShuffleDataFoundationTest.php` — confirms existing orphan cleanup tests
- Direct code inspection: `tests/Feature/ShuffleCrudTest.php` — confirms Volt test pattern for this page

### Secondary (MEDIUM confidence)
- Livewire Computed property cache invalidation: `unset($this->propertyName)` is the standard pattern for invalidating `#[Computed]` cache after mutations (well-established Livewire v3 pattern)

### Tertiary (LOW confidence)
- None

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — all code read directly from codebase; no speculation
- Architecture: HIGH — patterns derived from existing working code in the same project
- Pitfalls: HIGH — derived from actual existing implementation decisions visible in the codebase
- Test map: HIGH — test structure matches existing `ShuffleCrudTest.php` and `ShuffleDataFoundationTest.php` patterns

**Research date:** 2026-03-04
**Valid until:** 2026-04-04 (stable codebase; only risk is if project upgrades Livewire or Laravel before planning)
