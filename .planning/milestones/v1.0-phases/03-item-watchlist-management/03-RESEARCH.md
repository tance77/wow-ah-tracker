# Phase 3: Item Watchlist Management - Research

**Researched:** 2026-03-01
**Domain:** Laravel Livewire/Volt CRUD, Eloquent user scoping, Alpine.js combobox
**Confidence:** HIGH

## Summary

Phase 3 builds a fully CRUD watchlist page using the existing `WatchedItem` model and the Volt class-based component pattern already established in Phase 2. The model, migration, and factory already exist — this phase wires them to a real UI. The three technical challenges are: (1) a searchable combobox add-flow backed by a static catalog, (2) inline threshold editing with save-on-blur feedback, and (3) strict per-user data scoping to satisfy ITEM-05.

The stack is already decided and fully in place: Livewire 4 + Volt 1.x (class-based), Alpine.js 3 (bundled by Livewire), Tailwind v4, Pest 3. No new packages are needed. The combobox can be built with Alpine.js `x-data` + a Livewire property for the search string, following the same `x-data` pattern already used in navigation.blade.php and dropdown.blade.php. Inline threshold editing uses `wire:model.blur` on `<input>` elements rendered within the table row, toggled by Alpine click state.

The biggest risk in this phase is user scoping — forgetting to scope queries to `auth()->id()` in even one Volt action would violate ITEM-05. The guard is simple: always access items via `auth()->user()->watchedItems()`, never `WatchedItem::query()` directly.

**Primary recommendation:** Build one Volt single-file component at `resources/views/livewire/pages/watchlist.blade.php` plus a dedicated seeder for the item catalog, following all existing patterns exactly. No new packages required.

---

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions

**Item search & add flow**
- Static catalog of common WoW crafting materials (herbs, ores, cloth, leather, enchanting mats, gems) seeded into the database
- Primary add flow: searchable dropdown (combobox pattern) filtering the catalog
- Fallback: manual Blizzard item ID entry for power users who know the exact ID
- Catalog is the curated source — no Blizzard API dependency in this phase

**Watchlist display & layout**
- Table layout: dense, scannable rows
- Columns: Item Name, Buy Threshold (%), Sell Threshold (%), Remove button
- Blizzard Item ID shown as secondary text (subtitle or tooltip), not a full column
- Empty state: centered "No items on your watchlist yet" message with prominent "Add your first item" button that focuses the search dropdown
- Instant remove — click remove button, item disappears immediately, no confirmation modal

**Threshold editing UX**
- Inline editing: click threshold value in the table row to make it editable
- Save on Enter or blur, auto-persists via Livewire wire:model
- Default thresholds: 10% for both buy and sell when adding a new item
- Validation: 1-100% range enforced
- Save feedback: subtle green checkmark or flash on the cell after save, disappears after ~1 second

**Where it lives in the app**
- Dedicated `/watchlist` route and page, separate from dashboard
- "Watchlist" link added to top navigation bar alongside "Dashboard"
- Dashboard gets a lightweight "You're tracking X items" count with link to /watchlist
- Page layout matches existing style: gold heading text, wow-dark background, same content area pattern as dashboard

### Claude's Discretion
- Exact searchable dropdown implementation (Alpine.js, Livewire native, or third-party)
- Catalog seeder data structure and specific items included
- Table styling details (hover states, borders, spacing)
- How inline edit mode visually indicates editability
- Flash/checkmark animation implementation

### Deferred Ideas (OUT OF SCOPE)
None — discussion stayed within phase scope
</user_constraints>

---

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| ITEM-01 | User can add a WoW commodity item to their watchlist by name or item ID | Combobox + manual ID fallback via Volt component actions; catalog seeded into a `catalog_items` table or static array |
| ITEM-02 | User can remove an item from their watchlist | `removeItem(int $id)` Volt action using scoped `auth()->user()->watchedItems()->findOrFail($id)->delete()` |
| ITEM-03 | User can set buy threshold (% below average) per watched item | Inline `wire:model.blur` on `buy_threshold` field with validation rule `integer\|min:1\|max:100` |
| ITEM-04 | User can set sell threshold (% above average) per watched item | Same inline editing pattern as ITEM-03 for `sell_threshold` |
| ITEM-05 | Each user has their own independent watchlist | All Volt queries go through `auth()->user()->watchedItems()` — never raw `WatchedItem::query()` |
</phase_requirements>

---

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| Livewire | ^4.0 | Reactive component framework | Already installed, used in all auth pages |
| Livewire Volt | ^1.7.0 | Single-file class-based components | Already installed, all auth pages use it |
| Alpine.js 3 | bundled by Livewire | DOM interactivity (combobox open/close, edit toggle) | Zero-install; nav/dropdown already use it |
| Tailwind v4 | ^4.2.1 | Styling | Already installed, CSS-first approach |
| Pest 3 | ^3.8 | Testing | Already installed with pest-plugin-laravel |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| Laravel Eloquent (scoped) | built-in | `auth()->user()->watchedItems()` relationship queries | Every DB read/write in this phase |
| `#[Validate]` attribute | Livewire built-in | Per-property inline validation | Used on threshold inputs to keep rules co-located |
| `Livewire\Attributes\Computed` | Livewire built-in | Memoized computed properties (watchlist, catalog filter) | Avoids N+1 on re-renders |
| `wire:key` | Livewire built-in | Stable DOM identity per table row | Required on every `@foreach` row in Livewire |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Alpine.js combobox (built-in) | Third-party (WireTomSelect, livewire-select) | No new packages; Alpine already bundled; sufficient for static catalog |
| Volt single-file component | Separate Livewire class + blade | Single-file is the established pattern for this app |
| Inline `wire:model.blur` | Separate edit modal | Inline is faster UX; modal is overkill for two numeric fields |

**Installation:** No new packages needed. All required libraries are already installed.

---

## Architecture Patterns

### Recommended Project Structure
```
resources/views/livewire/pages/
└── watchlist.blade.php          # Volt single-file component (PHP + Blade)

database/seeders/
├── DatabaseSeeder.php           # Calls ItemCatalogSeeder
└── ItemCatalogSeeder.php        # Seeds catalog_items (or static array in Volt)

routes/web.php                   # Add /watchlist route
resources/views/livewire/layout/
└── navigation.blade.php         # Add Watchlist nav link
resources/views/
└── dashboard.blade.php          # Add "tracking X items" count summary
```

### Pattern 1: Volt Class-Based Single-File Component (established pattern)

**What:** PHP class definition above `?>`, then Blade template below. PHP accesses Eloquent via `auth()->user()`.
**When to use:** This is the established pattern for all interactive pages in this project.
**Example (from existing codebase):**
```php
// Source: resources/views/livewire/pages/auth/register.blade.php (existing)
new #[Layout('layouts.app')] class extends Component
{
    public string $search = '';
    public ?int $selectedCatalogId = null;
    public string $manualItemId = '';

    #[Validate('required|integer|min:1|max:100')]
    public int $buyThreshold = 10;

    // Computed property — memoized per request
    #[Computed]
    public function watchedItems(): Collection
    {
        return auth()->user()->watchedItems()->orderBy('name')->get();
    }

    public function addItem(): void
    {
        // Always scope to auth user
        auth()->user()->watchedItems()->create([...]);
    }

    public function removeItem(int $id): void
    {
        // findOrFail scoped to user prevents cross-user deletion
        auth()->user()->watchedItems()->findOrFail($id)->delete();
    }

    public function updateThreshold(int $id, string $field, int $value): void
    {
        $item = auth()->user()->watchedItems()->findOrFail($id);
        $item->update([$field => max(1, min(100, $value))]);
    }
}; ?>
```

### Pattern 2: Combobox via Alpine + Livewire Search Property

**What:** Alpine manages open/close state locally. A Livewire `$search` property drives the filtered catalog list via `wire:model.live`. Selecting an item dispatches back to Livewire.
**When to use:** Static catalog; no server round-trip for filtering needed if catalog is loaded into a computed property.

```php
// Volt PHP side
#[Computed]
public function catalogSuggestions(): array
{
    if (strlen($this->search) < 2) {
        return [];
    }
    return CatalogItem::where('name', 'like', "%{$this->search}%")
        ->limit(15)
        ->get(['id', 'name', 'blizzard_item_id'])
        ->toArray();
}
```

```html
<!-- Alpine manages dropdown visibility; Livewire drives the data -->
<div x-data="{ open: false }" @click.outside="open = false">
    <input
        wire:model.live.debounce.200ms="search"
        @focus="open = true"
        @input="open = true"
        type="text"
        placeholder="Search items..."
        class="border-gray-600 bg-wow-darker text-gray-100 ..."
    />
    <ul x-show="open && $wire.catalogSuggestions.length > 0" class="absolute z-50 ...">
        @foreach($this->catalogSuggestions as $item)
            <li wire:key="catalog-{{ $item['id'] }}"
                wire:click="selectCatalogItem({{ $item['id'] }})"
                @click="open = false"
                class="px-4 py-2 hover:bg-wow-dark cursor-pointer text-gray-200">
                {{ $item['name'] }}
                <span class="text-xs text-gray-500 ml-2">#{{ $item['blizzard_item_id'] }}</span>
            </li>
        @endforeach
    </ul>
</div>
```

**Note:** `$wire` is Alpine's bridge to the Livewire component — access computed properties and call actions via `$wire.propertyName` or `$wire.methodName()`.

### Pattern 3: Inline Threshold Editing with wire:model.blur

**What:** Table cells show the threshold value. Click reveals an `<input>` (Alpine toggle). `wire:model.blur` saves when user leaves the field. Visual feedback via Alpine `x-show` or temporary class toggle.
**When to use:** This is the confirmed UX pattern from CONTEXT.md.

```html
<!-- Per table row: wire:key required for Livewire DOM stability -->
@foreach($this->watchedItems as $item)
<tr wire:key="item-{{ $item->id }}" class="border-t border-gray-700/50">
    <td class="px-4 py-3 text-gray-200">
        {{ $item->name }}
        <span class="text-xs text-gray-500 block">ID: {{ $item->blizzard_item_id }}</span>
    </td>
    <td class="px-4 py-3" x-data="{ editing: false, saved: false }">
        <span x-show="!editing" @click="editing = true" class="cursor-pointer hover:text-wow-gold">
            {{ $item->buy_threshold }}%
        </span>
        <div x-show="editing" class="flex items-center gap-1">
            <input
                type="number"
                wire:model.blur="items.{{ $item->id }}.buy_threshold"
                min="1" max="100"
                class="w-16 ..."
                @blur="editing = false; saved = true; setTimeout(() => saved = false, 1000)"
                x-ref="buyInput"
                x-init="$watch('editing', v => v && $nextTick(() => $refs.buyInput.focus()))"
            />
        </div>
        <span x-show="saved" class="text-green-400 text-xs ml-1">✓</span>
    </td>
    <!-- Similar for sell_threshold -->
    <td class="px-4 py-3">
        <button wire:click="removeItem({{ $item->id }})" class="text-red-400 hover:text-red-300 text-sm">
            Remove
        </button>
    </td>
</tr>
@endforeach
```

**Alternative approach for threshold updates:** Instead of `wire:model` bound to a nested array, use `wire:change="updateThreshold({{ $item->id }}, 'buy_threshold', $event.target.value)"` which is cleaner for discrete per-row updates on a collection.

### Pattern 4: Catalog Seeder

**What:** A dedicated `ItemCatalogSeeder` seeds WoW crafting materials into a `catalog_items` table (or the seeder populates a static PHP array used by the component).
**Decision:** Use a separate `catalog_items` table with columns `(id, blizzard_item_id, name, category)` rather than a static PHP array. This makes catalog lookup consistent and lets the combobox filter via SQL `LIKE`.

**Catalog item categories (WoW TWW-era crafting materials):**
- **Herbs:** Luredrop (222788), Orbinid (222785), Blessing Blossom (222790), Gundegrass (222789), Arathor's Spear (222791), Ironcap Mushroom (222792)
- **Ores:** Uldricite (224023), Bismuth (224025), Ironcite (224024)
- **Cloth:** Weavercloth (224570), Dawnweave (224569)
- **Leather:** Amplified Monstrous Hide (224552), Monstrous Hide (224551)
- **Enchanting:** Resonant Crystal (222825), Spark of Omens (222826), Glittering Parchment (222823)
- **Gems:** Irradiated Taaffeite (220156), Irradiated Ruby (220153), Irradiated Emerald (220155)

**Note on Blizzard item IDs:** The IDs above are approximate for TWW expansion materials. Verify against live Blizzard API in Phase 4 — correctness of IDs is not critical in Phase 3 since this phase uses static catalog only. They are placeholders until live API integration.

### Anti-Patterns to Avoid

- **Raw `WatchedItem::query()` in Volt:** Always use `auth()->user()->watchedItems()` — raw queries break user isolation (ITEM-05).
- **Forgetting `wire:key`:** Every `@foreach` over Livewire data needs `wire:key`. Without it, Livewire DOM diffing breaks on remove/add.
- **Deeply nested `wire:model` array bindings:** `wire:model="items.{{ $id }}.threshold"` requires special Livewire array property setup. Prefer `wire:change` calling a named action with the ID.
- **No threshold validation on server:** Client-side min/max attributes are not enough — validate in the Volt action too.
- **Empty `$search` triggering full catalog load:** Guard computed `catalogSuggestions` with `strlen($this->search) < 2` to avoid large queries on every keystroke.

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Combobox dropdown | Custom JS autocomplete | Alpine.js `x-data` + `wire:model.live` | Alpine already bundled; dropdown component already exists |
| Row identity during re-render | Complex DOM reconciliation | `wire:key="item-{{ $item->id }}"` | Livewire built-in; one attribute fixes all flickering |
| Threshold range enforcement | Custom JS guard | `#[Validate('integer\|min:1\|max:100')]` + server validation | Single source of truth; prevents bypass |
| User scoping | Manual `user_id` WHERE clauses | `auth()->user()->watchedItems()` Eloquent relationship | Relationship handles the WHERE, findOrFail handles 404 on wrong user |
| Save feedback animation | Custom JS timer class | Alpine `x-show="saved"` + `setTimeout(() => saved = false, 1000)` | Three lines of Alpine, no CSS complexity |

**Key insight:** Livewire re-renders the entire component on each action — this is a feature, not a bug. Don't fight it with manual DOM updates. Let `wire:key` maintain row identity and Livewire handle the rest.

---

## Common Pitfalls

### Pitfall 1: Cross-User Item Access
**What goes wrong:** A user crafts a URL like `/watchlist` and calls `removeItem(5)` where `5` belongs to another user.
**Why it happens:** Using `WatchedItem::findOrFail($id)` instead of scoped relationship.
**How to avoid:** `auth()->user()->watchedItems()->findOrFail($id)` — the relationship WHERE clause makes cross-user access return 404 automatically.
**Warning signs:** Any `WatchedItem::` static call in Volt actions is a red flag.

### Pitfall 2: Missing wire:key Breaks Table Rows
**What goes wrong:** Removing or reordering items causes Livewire to re-render the wrong row, making the wrong item disappear or inputs lose focus.
**Why it happens:** Livewire DOM diffing uses positional identity without `wire:key`.
**How to avoid:** Add `wire:key="item-{{ $item->id }}"` to every `<tr>` in the watchlist loop.
**Warning signs:** Remove seems to delete the wrong item, or threshold inputs reset after typing.

### Pitfall 3: wire:model on Collection Rows
**What goes wrong:** Binding `wire:model="watchedItems.0.buy_threshold"` to a collection from a computed property triggers Livewire errors or doesn't persist (computed properties are read-only).
**Why it happens:** Computed properties cannot be mutated via `wire:model`.
**How to avoid:** Use `wire:change="updateThreshold({{ $item->id }}, 'buy_threshold', $event.target.value)"` to call an explicit Volt action instead of trying to bind directly to the computed collection.
**Warning signs:** Livewire throws "property not found" or changes silently disappear after re-render.

### Pitfall 4: Alpine $wire Access to Computed Properties
**What goes wrong:** `$wire.catalogSuggestions` is undefined or throws an error in Alpine.
**Why it happens:** Computed properties decorated with `#[Computed]` are accessible via `$wire` in Alpine, but the property name must match exactly (camelCase).
**How to avoid:** Name the computed property clearly (`catalogSuggestions`) and access it as `$wire.catalogSuggestions` in Alpine x-show conditions.
**Warning signs:** Dropdown never appears; browser console shows `undefined` on `$wire.catalogSuggestions`.

### Pitfall 5: Full Catalog Query on Every Keystroke
**What goes wrong:** Every letter typed fires a Livewire round-trip that loads hundreds of catalog items.
**Why it happens:** `wire:model.live` on search without a debounce or minimum length guard.
**How to avoid:** Use `wire:model.live.debounce.200ms` and return `[]` in `catalogSuggestions` when `strlen($this->search) < 2`.
**Warning signs:** Network tab shows 10+ XHR requests per second while typing.

### Pitfall 6: declare(strict_types=1) Missing
**What goes wrong:** Pint or CI fails because the new Volt file lacks strict types.
**Why it happens:** Pint is configured with `declare_strict_types` enforced across the project (set in Phase 1).
**How to avoid:** The PHP block in every Volt file must begin with `declare(strict_types=1);`.

---

## Code Examples

Verified patterns from project codebase and official sources:

### Volt Route + Auth Middleware (existing pattern)
```php
// Source: routes/web.php (existing)
Route::get('/watchlist', function () {
    return view('livewire.pages.watchlist');
})->middleware(['auth'])->name('watchlist');
```

**Alternative using Volt::route (if supported):**
```php
// Using Volt facade for cleaner route declaration
Volt::route('/watchlist', 'pages.watchlist')->middleware(['auth'])->name('watchlist');
```

### Navigation Link Addition (existing pattern)
```html
<!-- Source: resources/views/livewire/layout/navigation.blade.php (existing pattern) -->
<x-nav-link :href="route('watchlist')" :active="request()->routeIs('watchlist')" wire:navigate>
    {{ __('Watchlist') }}
</x-nav-link>
```
Add this alongside the existing Dashboard link (both desktop and responsive sections).

### Volt Component Structure
```php
<?php

declare(strict_types=1);

use App\Models\CatalogItem;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public string $search = '';
    public string $manualItemId = '';

    #[Computed]
    public function watchedItems(): Collection
    {
        return auth()->user()->watchedItems()->orderBy('name')->get();
    }

    #[Computed]
    public function catalogSuggestions(): array
    {
        if (strlen($this->search) < 2) {
            return [];
        }

        return CatalogItem::where('name', 'like', "%{$this->search}%")
            ->limit(15)
            ->orderBy('name')
            ->get(['id', 'name', 'blizzard_item_id'])
            ->toArray();
    }

    public function addFromCatalog(int $catalogId): void
    {
        $catalog = CatalogItem::findOrFail($catalogId);

        // Prevent duplicate watched items per user
        auth()->user()->watchedItems()->firstOrCreate(
            ['blizzard_item_id' => $catalog->blizzard_item_id],
            [
                'name' => $catalog->name,
                'buy_threshold' => 10,
                'sell_threshold' => 10,
            ]
        );

        $this->search = '';
    }

    public function addManual(): void
    {
        $this->validate(['manualItemId' => 'required|integer|min:1']);

        $id = (int) $this->manualItemId;

        auth()->user()->watchedItems()->firstOrCreate(
            ['blizzard_item_id' => $id],
            [
                'name' => "Item #{$id}",  // Phase 4 will resolve real name
                'buy_threshold' => 10,
                'sell_threshold' => 10,
            ]
        );

        $this->manualItemId = '';
    }

    public function removeItem(int $id): void
    {
        auth()->user()->watchedItems()->findOrFail($id)->delete();
    }

    public function updateThreshold(int $id, string $field, int $value): void
    {
        $allowed = ['buy_threshold', 'sell_threshold'];

        if (! in_array($field, $allowed, true)) {
            return;
        }

        $item = auth()->user()->watchedItems()->findOrFail($id);
        $item->update([$field => max(1, min(100, $value))]);
    }
}; ?>
```

### Pest Test Pattern for Watchlist
```php
// Source: tests/Feature/Auth/RouteProtectionTest.php (existing pattern)
use App\Models\User;
use App\Models\WatchedItem;
use Livewire\Volt\Volt;

test('watchlist redirects unauthenticated users to login', function () {
    $this->get('/watchlist')->assertRedirect('/login');
});

test('user can view their own watchlist', function () {
    $user = User::factory()->create();
    $item = WatchedItem::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)->get('/watchlist')->assertOk()->assertSee($item->name);
});

test('user cannot see another user watchlist items', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $itemB = WatchedItem::factory()->create(['user_id' => $userB->id]);

    $this->actingAs($userA)->get('/watchlist')->assertDontSee($itemB->name);
});

test('user can add item from catalog', function () {
    $user = User::factory()->create();

    Volt::test('pages.watchlist')
        ->actingAs($user)
        ->call('addFromCatalog', 1)  // catalog ID 1
        ->assertHasNoErrors();

    expect(WatchedItem::where('user_id', $user->id)->count())->toBe(1);
});

test('user can remove watched item', function () {
    $user = User::factory()->create();
    $item = WatchedItem::factory()->create(['user_id' => $user->id]);

    Volt::test('pages.watchlist')
        ->actingAs($user)
        ->call('removeItem', $item->id)
        ->assertHasNoErrors();

    expect(WatchedItem::find($item->id))->toBeNull();
});

test('user cannot remove another users watched item', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $item = WatchedItem::factory()->create(['user_id' => $userB->id]);

    Volt::test('pages.watchlist')
        ->actingAs($userA)
        ->call('removeItem', $item->id);

    expect(WatchedItem::find($item->id))->not->toBeNull();
});

test('threshold update is clamped to 1-100', function () {
    $user = User::factory()->create();
    $item = WatchedItem::factory()->create(['user_id' => $user->id, 'buy_threshold' => 10]);

    Volt::test('pages.watchlist')
        ->actingAs($user)
        ->call('updateThreshold', $item->id, 'buy_threshold', 150);

    expect($item->fresh()->buy_threshold)->toBe(100);
});
```

### CatalogItem Migration
```php
// database/migrations/YYYY_MM_DD_create_catalog_items_table.php
Schema::create('catalog_items', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('blizzard_item_id')->unique();
    $table->string('name');
    $table->string('category');  // 'herb', 'ore', 'cloth', 'leather', 'enchanting', 'gem'
    $table->timestamps();
});
```

### ItemCatalogSeeder Structure
```php
// database/seeders/ItemCatalogSeeder.php
declare(strict_types=1);

namespace Database\Seeders;

use App\Models\CatalogItem;
use Illuminate\Database\Seeder;

class ItemCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            // Herbs (The War Within era)
            ['blizzard_item_id' => 222788, 'name' => 'Luredrop', 'category' => 'herb'],
            ['blizzard_item_id' => 222785, 'name' => 'Orbinid', 'category' => 'herb'],
            ['blizzard_item_id' => 222790, 'name' => 'Blessing Blossom', 'category' => 'herb'],
            ['blizzard_item_id' => 222789, 'name' => 'Gundegrass', 'category' => 'herb'],
            // Ores
            ['blizzard_item_id' => 224023, 'name' => 'Uldricite', 'category' => 'ore'],
            ['blizzard_item_id' => 224025, 'name' => 'Bismuth', 'category' => 'ore'],
            // Cloth
            ['blizzard_item_id' => 224570, 'name' => 'Weavercloth', 'category' => 'cloth'],
            // Leather
            ['blizzard_item_id' => 224552, 'name' => 'Amplified Monstrous Hide', 'category' => 'leather'],
            // Enchanting
            ['blizzard_item_id' => 222825, 'name' => 'Resonant Crystal', 'category' => 'enchanting'],
            // Gems
            ['blizzard_item_id' => 220156, 'name' => 'Irradiated Taaffeite', 'category' => 'gem'],
            // ... ~20 total items
        ];

        foreach ($items as $item) {
            CatalogItem::updateOrCreate(
                ['blizzard_item_id' => $item['blizzard_item_id']],
                $item
            );
        }
    }
}
```

---

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Livewire 2/3 `wire:model` on select | Livewire 4 `wire:model.live` / `wire:model.blur` modifiers | Livewire 4.0 | `.blur` is the correct modifier for save-on-blur patterns |
| `wire:model.defer` | Removed in Livewire 4 (use `wire:model` default) | Livewire 4.0 | Don't use `.defer` — it doesn't exist in v4 |
| Volt functional API (`state()`, `computed()`) | Class-based API (`new class extends Component`) | Volt 1.x (optional) | This project uses class-based; don't mix patterns |
| Alpine.js installed separately | Alpine.js bundled by Livewire | Livewire 3+ | No separate Alpine install; `x-data` just works |
| `Livewire::test()` for Volt | `Volt::test('pages.watchlist')` | Livewire 4 + Volt | Use `Volt::test()` for Volt single-file components |

**Deprecated/outdated:**
- `wire:model.defer`: Does not exist in Livewire 4. Use default `wire:model` (deferred by default) or `.blur`.
- Volt functional API (`state()`, `computed()` as functions): Works but is the other pattern — this project uses class-based throughout, do not introduce functional API.

---

## Open Questions

1. **CatalogItem model: separate table vs. static array?**
   - What we know: CONTEXT.md requires a seeded catalog. A separate table enables SQL LIKE filtering without loading all items.
   - What's unclear: Whether a `CatalogItem` Eloquent model adds complexity that isn't justified for ~20-30 static items.
   - Recommendation: Use a separate `catalog_items` table — enables LIKE search, is testable, and Phase 4 may want to expand or sync the catalog from Blizzard API.

2. **Blizzard item ID accuracy in catalog seeder**
   - What we know: TWW expansion item IDs are approximate from training data, not verified against live API.
   - What's unclear: Whether the seeded IDs will match what Blizzard's API returns in Phase 4.
   - Recommendation: Use the IDs as placeholders. Document in seeder comments that IDs need Phase 4 verification. The catalog's purpose in Phase 3 is UI/UX testing, not production data accuracy.

3. **`firstOrCreate` vs. unique constraint on `(user_id, blizzard_item_id)`**
   - What we know: A user adding the same item twice should be a no-op, not an error.
   - What's unclear: Whether the migration should add a unique constraint on `(user_id, blizzard_item_id)`.
   - Recommendation: Add a unique constraint in the migration (or via `->unique(['user_id', 'blizzard_item_id'])`) to enforce at DB level, and use `firstOrCreate` in the Volt action to handle it gracefully.

4. **Dashboard count query**
   - What we know: Dashboard should show "You're tracking X items" with a link to /watchlist.
   - What's unclear: Whether this is a live Livewire component or a simple blade include.
   - Recommendation: Simple blade view with `auth()->user()->watchedItems()->count()` — no Livewire needed for a static count on a non-interactive dashboard.

---

## Sources

### Primary (HIGH confidence)
- Project codebase (`app/Models/WatchedItem.php`, `resources/views/livewire/`) — established Volt class-based pattern, Alpine usage, theme tokens, route/middleware structure
- `https://livewire.laravel.com/docs/wire-model` — wire:model modifiers (`.blur`, `.live`, `.change`); `.defer` removal confirmed

### Secondary (MEDIUM confidence)
- `https://livewire.laravel.com/docs/testing` — `Volt::test()` exists, core assertion methods verified
- WebSearch + GitHub discussions — Alpine `$wire` bridge for computed properties; `x-data` + `wire:model.live` combobox pattern; `wire:key` requirement for collection loops

### Tertiary (LOW confidence)
- Blizzard item IDs in catalog examples — based on training data knowledge of TWW expansion, not verified against live API. Treat as placeholders.

---

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — all libraries already installed and in use
- Architecture patterns: HIGH — directly mirrors existing Volt auth components
- Pitfalls: HIGH — wire:key, user scoping, computed property mutability are well-documented Livewire behaviors
- Catalog item IDs: LOW — training data, unverified against live Blizzard API

**Research date:** 2026-03-01
**Valid until:** 2026-04-01 (Livewire 4.x stable; Alpine bundled; no breaking changes expected in 30 days)
