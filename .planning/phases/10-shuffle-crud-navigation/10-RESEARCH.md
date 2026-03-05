# Phase 10: Shuffle CRUD and Navigation - Research

**Researched:** 2026-03-04
**Domain:** Laravel 12 / Livewire Volt SFC / Alpine.js — CRUD UI, navigation, inline editing, modal confirmation
**Confidence:** HIGH

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions

**Shuffles list layout**
- Simple table/list layout — one row per shuffle, consistent with Watchlist page style
- Each row shows: shuffle name, step count, chain preview (input → output items), and profitability badge
- Clicking a shuffle row navigates to `/shuffles/{id}` detail page (consistent with watchlist item → item detail pattern)
- Empty state: brief explanation of what shuffles are (item conversion chains for profit tracking) plus a prominent "Create Shuffle" button

**Profitability badge**
- Color dot + profit amount in gold/silver/copper format (green for profitable, red for unprofitable)
- Per-unit profit calculation (1 input through the chain) — includes 5% AH cut for realistic numbers
- Edge states: neutral gray badge with dash ("—") when shuffle has no steps or prices are unavailable
- Badge calculates live from latest price snapshots (no cached column — decided in Phase 9)

**Create/edit/delete flow**
- Create: "New Shuffle" button creates a shuffle with a default name and immediately navigates to `/shuffles/{id}` detail page where user can rename and later add steps
- Rename: Inline edit on the list page — click the name to make it editable, press Enter or click away to save. Also editable on detail page
- Delete: Delete button with confirmation modal warning that steps will be deleted and auto-watched items not used by other shuffles will also be removed from the watchlist

**Navigation**
- Nav order: Dashboard | Watchlist | Shuffles — appended after existing links
- Both desktop (`<x-nav-link>`) and mobile responsive (`<x-responsive-nav-link>`) menus updated
- Route: `/shuffles` for list, `/shuffles/{shuffle}` for detail

**Shuffle detail page (shell)**
- Shell detail page created in Phase 10 at `/shuffles/{id}`
- Shows: shuffle name (editable), profitability badge, and placeholder section for step editor (Phase 11)
- Delete button accessible from detail page as well
- Back link to `/shuffles` list

### Claude's Discretion
- Exact Tailwind styling and spacing for shuffle rows
- Loading state patterns for the list
- Error handling for failed creates/deletes
- Detail page placeholder content and layout

### Deferred Ideas (OUT OF SCOPE)
None — discussion stayed within phase scope
</user_constraints>

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| SHUF-01 | User can create a named shuffle with a descriptive name | Volt SFC with `createShuffle()` action, `Shuffle::create()` then redirect to detail |
| SHUF-03 | User can edit an existing shuffle's name and steps | Inline editing with Alpine.js x-data pattern (same as watchlist threshold edit), `renameShuffle()` action |
| SHUF-04 | User can delete a shuffle | Confirmation modal via `<x-modal>` component, `deleteShuffle()` action on both list and detail pages |
| SHUF-05 | User can view a list of all saved shuffles with profitability badge | `#[Computed]` shuffles collection, profit method on Shuffle model, badge component or inline Blade |
</phase_requirements>

## Summary

Phase 10 adds a Shuffles section: list page at `/shuffles`, detail shell page at `/shuffles/{shuffle}`, navigation links in desktop and mobile menus, and CRUD actions (create, rename, delete). All UI follows established Volt SFC patterns already in the codebase. The existing `Shuffle` and `ShuffleStep` models from Phase 9 are ready. The `FormatsAuctionData` concern provides gold formatting for the profitability badge. The existing modal component handles delete confirmation.

The primary implementation surface is two new Volt SFC Blade files (`shuffles.blade.php` and `shuffle-detail.blade.php`) plus small edits to `navigation.blade.php` and `routes/web.php`. No new packages or migrations are needed. The only new model logic is a `profitPerUnit()` method on `Shuffle` that reads live price snapshots from related `ShuffleStep` items.

**Primary recommendation:** Follow the established Watchlist + item-detail pattern exactly. New Volt SFC pages, `#[Computed]` for data, Alpine.js inline edit for rename, `<x-modal>` for delete confirm.

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| Livewire Volt | ^4.0 / ^1.7.0 | SFC pages with co-located PHP + Blade | Project standard for all pages |
| Alpine.js | Included with Livewire | Inline UI state (edit mode toggle, modal show/hide) | Already used throughout project |
| Tailwind CSS | Project-wide | Styling consistent with WoW dark theme | Project standard |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| `FormatsAuctionData` concern | Project | `formatGold(int $copper): string` for badge display | Use on both list and detail pages |
| `<x-modal>` component | Project | Delete confirmation dialog | Already styled; receives `name` prop for AlpineJS binding |
| `<x-nav-link>` / `<x-responsive-nav-link>` | Project | Nav links with active-state styling | Adding Shuffles to desktop + mobile nav |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Inline Alpine edit pattern | Livewire `wire:model` + form | Alpine is lighter for single-field rename; matches existing threshold-edit pattern in watchlist |
| `<x-modal>` for confirm | `wire:confirm` directive | `wire:confirm` is a browser `confirm()` dialog — no custom styling; locked decision uses modal |

**Installation:**
No new packages needed. Everything required is already in the project.

## Architecture Patterns

### Recommended Project Structure
```
resources/views/livewire/pages/
├── shuffles.blade.php          # NEW — Shuffles list page (SHUF-01, SHUF-03, SHUF-04, SHUF-05)
└── shuffle-detail.blade.php    # NEW — Shuffle detail shell (SHUF-03, SHUF-04)

routes/web.php                  # EDIT — Add two Volt routes
resources/views/livewire/layout/navigation.blade.php  # EDIT — Add nav links
app/Models/Shuffle.php          # EDIT — Add profitPerUnit() method
tests/Feature/
└── ShuffleCrudTest.php         # NEW — Feature tests for SHUF-01, 03, 04, 05
```

### Pattern 1: Volt SFC Page Structure
**What:** All pages are anonymous classes extending `Component` with `#[Layout('layouts.app')]`, co-located above the Blade template in one `.blade.php` file.
**When to use:** Every new page in this project.
**Example:**
```php
<?php
declare(strict_types=1);

use App\Concerns\FormatsAuctionData;
use App\Models\Shuffle;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    use FormatsAuctionData;

    public function createShuffle(): void
    {
        $shuffle = auth()->user()->shuffles()->create([
            'name' => 'New Shuffle',
        ]);

        $this->redirect(route('shuffles.show', $shuffle), navigate: true);
    }

    #[Computed]
    public function shuffles(): Collection
    {
        return auth()->user()->shuffles()
            ->with(['steps.inputCatalogItem', 'steps.outputCatalogItem'])
            ->orderBy('created_at', 'desc')
            ->get();
    }
}; ?>
```

### Pattern 2: Alpine.js Inline Edit (matches existing watchlist threshold pattern)
**What:** Click to toggle edit mode on a single field; blur/Enter to save; Escape to cancel.
**When to use:** Single-field inline rename (shuffle name on list and detail pages).
**Example:**
```html
{{-- Source: existing watchlist.blade.php inline threshold edit --}}
<div
    x-data="{ editing: false, saved: false }"
    x-init="$watch('editing', v => v && $nextTick(() => $refs.nameInput.focus()))"
>
    <span
        x-show="!editing"
        @click="editing = true"
        class="cursor-pointer hover:text-wow-gold"
    >{{ $shuffle->name }}</span>
    <input
        type="text"
        x-show="editing"
        x-ref="nameInput"
        value="{{ $shuffle->name }}"
        wire:change="renameShuffle({{ $shuffle->id }}, $event.target.value)"
        @blur="editing = false; saved = true; setTimeout(() => saved = false, 1000)"
        @keydown.enter="$el.blur()"
        @keydown.escape="editing = false"
        class="rounded border border-gray-600 bg-wow-darker px-2 py-1 text-gray-100 focus:border-wow-gold focus:outline-none focus:ring-1 focus:ring-wow-gold"
    />
    <span x-show="saved" x-transition class="ml-1 text-xs text-green-400">Saved</span>
</div>
```

### Pattern 3: Modal Delete Confirmation (existing `<x-modal>` component)
**What:** Show named modal via Alpine.js, wire action on confirm button.
**When to use:** Delete shuffle (both list and detail pages).
**Example:**
```html
{{-- Trigger --}}
<button @click="$dispatch('open-modal', 'confirm-delete-{{ $shuffle->id }}')">Delete</button>

{{-- Modal --}}
<x-modal name="confirm-delete-{{ $shuffle->id }}" :show="false">
    <div class="p-6">
        <h2 class="text-lg font-medium text-gray-100">Delete Shuffle?</h2>
        <p class="mt-1 text-sm text-gray-400">
            This will delete all steps. Auto-watched items not used by other shuffles will also be removed from your watchlist.
        </p>
        <div class="mt-6 flex justify-end gap-3">
            <button @click="$dispatch('close-modal', 'confirm-delete-{{ $shuffle->id }}')">Cancel</button>
            <button wire:click="deleteShuffle({{ $shuffle->id }})">Delete</button>
        </div>
    </div>
</x-modal>
```

### Pattern 4: Route Registration (Volt)
**What:** `Volt::route()` with auth middleware and named route.
**When to use:** Every new authenticated page.
**Example:**
```php
// Source: existing routes/web.php
Volt::route('/shuffles', 'pages.shuffles')
    ->middleware(['auth'])
    ->name('shuffles');

Volt::route('/shuffles/{shuffle}', 'pages.shuffle-detail')
    ->middleware(['auth'])
    ->name('shuffles.show');
```

### Pattern 5: Profitability Calculation on Shuffle Model
**What:** Method on `Shuffle` that calculates per-unit net profit from all steps with latest price snapshots, including 5% AH cut on final output.
**When to use:** Badge display on list and detail pages.
**Example:**
```php
// On App\Models\Shuffle
public function profitPerUnit(): ?int
{
    $steps = $this->steps()->with([
        'inputCatalogItem.priceSnapshots' => fn ($q) => $q->latest('polled_at')->limit(1),
        'outputCatalogItem.priceSnapshots' => fn ($q) => $q->latest('polled_at')->limit(1),
    ])->get();

    if ($steps->isEmpty()) {
        return null; // triggers neutral badge
    }

    // First step input cost = cost in
    $firstInputPrice = $steps->first()->inputCatalogItem?->priceSnapshots->first()?->median_price;
    if ($firstInputPrice === null) {
        return null;
    }

    // Last step output value after 5% AH cut
    $lastStep = $steps->last();
    $outputPrice = $lastStep->outputCatalogItem?->priceSnapshots->first()?->median_price;
    if ($outputPrice === null) {
        return null;
    }

    // Apply yield from last step (use min qty for conservative estimate)
    $outputQty = $lastStep->output_qty_min ?? 1;
    $grossOutput = $outputPrice * $outputQty;
    $netOutput = (int) round($grossOutput * 0.95); // 5% AH cut

    return $netOutput - $firstInputPrice;
}
```

### Pattern 6: Navigation Link Addition
**What:** Add `<x-nav-link>` (desktop) and `<x-responsive-nav-link>` (mobile) in `navigation.blade.php`.
**When to use:** Adding Shuffles to both nav sections.
**Example:**
```html
{{-- Desktop nav — inside the space-x-8 div after Watchlist --}}
<x-nav-link :href="route('shuffles')" :active="request()->routeIs('shuffles*')" wire:navigate>
    {{ __('Shuffles') }}
</x-nav-link>

{{-- Mobile nav — inside the pt-2 pb-3 div after Watchlist --}}
<x-responsive-nav-link :href="route('shuffles')" :active="request()->routeIs('shuffles*')" wire:navigate>
    {{ __('Shuffles') }}
</x-responsive-nav-link>
```

Note: Use `routeIs('shuffles*')` (wildcard) so the link stays active on both list and detail pages.

### Anti-Patterns to Avoid
- **Fetching eager-loaded data inside loops:** Load steps and catalog items with `with()` in the `#[Computed]` query, not lazily inside Blade loops.
- **Caching profitability in DB:** Decided out of scope for Phase 10; calculate live each request.
- **Using `wire:confirm` for delete:** Browser `confirm()` dialog has no WoW theming; use `<x-modal>` per locked decision.
- **Opening detail page in same Livewire request as create:** Use `$this->redirect(..., navigate: true)` after create — Livewire SPA navigation is faster than full page reload.
- **Authorization via route only:** Check `abort_unless($shuffle->user_id === auth()->id(), 403)` in `mount()` on the detail page — same as `item-detail.blade.php`.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Gold/silver/copper formatting | Custom formatter | `FormatsAuctionData::formatGold()` | Already handles negative values, zero-parts omission, localized number format |
| Delete confirmation UI | Custom JS modal | `<x-modal>` component | Already styled, keyboard-accessible, focus-trapping |
| Nav link active state | Manual CSS class toggle | `<x-nav-link :active="request()->routeIs(...)">` | Component already handles active styling |
| Orphan watchlist cleanup on delete | Manual DB query in controller | `Shuffle::deleting()` model event | Already implemented in Phase 9 — just call `$shuffle->delete()` |
| Inline edit save feedback | Custom polling | Alpine.js `saved` state with `setTimeout` clear | Established pattern in watchlist; zero wire round-trips |

**Key insight:** The project already has all the building blocks. Phase 10 is assembly, not invention.

## Common Pitfalls

### Pitfall 1: Profitability Calculation with Multi-Step Chains
**What goes wrong:** Calculating profit by only looking at first step input and last step output ignores intermediate steps that could change the input quantity or price basis.
**Why it happens:** Simple subtraction works for 1-step shuffles; multi-step shuffles may feed intermediate outputs into subsequent inputs at different quantities.
**How to avoid:** For Phase 10, the badge is display-only and steps are not yet configurable (Phase 11). Use the naive first-input/last-output calculation with a `null` guard — the design accepted this simplification for Phase 10.
**Warning signs:** Wildly incorrect profit numbers for multi-step shuffles; revisit in Phase 12 when batch calculator is added.

### Pitfall 2: Missing `wire:key` on Shuffles List
**What goes wrong:** Without `wire:key="shuffle-{{ $shuffle->id }}"` on list rows, Livewire cannot correctly diff the DOM after create/delete, causing stale UI or incorrect re-renders.
**Why it happens:** Livewire uses `wire:key` to identify list items across re-renders.
**How to avoid:** Always add `wire:key` to elements inside `@foreach`.

### Pitfall 3: Authorization Not Checked on Detail Page
**What goes wrong:** User A navigates directly to `/shuffles/5` which belongs to User B.
**Why it happens:** Route model binding resolves the Shuffle without user scope.
**How to avoid:** Add `abort_unless($this->shuffle->user_id === auth()->id(), 403)` in the detail page's `mount()` method — same pattern as `item-detail.blade.php`.

### Pitfall 4: Active Nav State Not Matching Detail Route
**What goes wrong:** "Shuffles" nav link appears inactive when viewing `/shuffles/{id}`.
**Why it happens:** `request()->routeIs('shuffles')` is exact — does not match `shuffles.show`.
**How to avoid:** Use `request()->routeIs('shuffles*')` (wildcard) to match both routes.

### Pitfall 5: Chain Preview Requires Eager Loading
**What goes wrong:** N+1 queries if chain preview text is built by accessing `$step->inputCatalogItem->name` inside a Blade loop without eager loading.
**Why it happens:** Lazy loading triggers a query per step.
**How to avoid:** Eager-load in the `#[Computed]` shuffles query: `->with(['steps.inputCatalogItem', 'steps.outputCatalogItem'])`.

### Pitfall 6: Inline Edit on Detail Page vs. List Page Are Independent Components
**What goes wrong:** Renaming on the list page and expecting detail page to reflect it immediately (or vice versa) without a redirect.
**Why it happens:** Two separate Livewire components, each with their own state.
**How to avoid:** After rename on detail page, no redirect needed — the page already displays the updated name from its own property. On the list page, Livewire re-renders the `#[Computed]` shuffles after `wire:change`, which reflects the updated name.

## Code Examples

Verified patterns from existing codebase:

### User Shuffles Relationship (needed on User model if not present)
```php
// Check if this exists — if not, add to app/Models/User.php
public function shuffles(): HasMany
{
    return $this->hasMany(Shuffle::class);
}
```

### Watchlist Page Empty State Pattern (reuse for Shuffles)
```html
{{-- Source: watchlist.blade.php --}}
<div class="flex flex-col items-center justify-center p-16 text-center">
    <p class="mb-4 text-gray-400">No shuffles yet</p>
    <p class="mb-6 text-sm text-gray-500">
        Shuffles track item conversion chains (e.g., Ore → Gems → Rings) so you can see profitability at a glance.
    </p>
    <button
        wire:click="createShuffle"
        class="rounded-md bg-wow-gold px-4 py-2 text-sm font-semibold text-wow-darker transition-colors hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-wow-gold focus:ring-offset-2 focus:ring-offset-wow-dark"
    >
        Create Shuffle
    </button>
</div>
```

### Profitability Badge Display
```html
{{-- Green for profit, red for loss, gray for unknown --}}
@php $profit = $shuffle->profitPerUnit(); @endphp
@if ($profit === null)
    <span class="inline-flex items-center gap-1 text-gray-400 text-sm">
        <span class="h-2 w-2 rounded-full bg-gray-500"></span>—
    </span>
@elseif ($profit >= 0)
    <span class="inline-flex items-center gap-1 text-green-400 text-sm">
        <span class="h-2 w-2 rounded-full bg-green-400"></span>{{ $this->formatGold($profit) }}
    </span>
@else
    <span class="inline-flex items-center gap-1 text-red-400 text-sm">
        <span class="h-2 w-2 rounded-full bg-red-400"></span>{{ $this->formatGold($profit) }}
    </span>
@endif
```

### Chain Preview Text
```php
// In Blade — renders "Ore → Gems → Rings" from ordered steps
@if ($shuffle->steps->isNotEmpty())
    {{ $shuffle->steps->map(fn ($s) => $s->inputCatalogItem?->name ?? 'Unknown')->join(' → ') }}
    → {{ $shuffle->steps->last()->outputCatalogItem?->name ?? 'Unknown' }}
@else
    <span class="text-gray-500 text-xs">No steps yet</span>
@endif
```

### Volt Test Pattern for Shuffle Pages
```php
// Source: existing tests/Feature/WatchlistTest.php pattern
use Livewire\Volt\Volt;

test('user can create a shuffle', function () {
    $user = User::factory()->create();

    Volt::actingAs($user)->test('pages.shuffles')
        ->call('createShuffle')
        ->assertRedirect(); // navigates to detail page

    expect($user->shuffles()->count())->toBe(1);
});
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Blade + Controller | Livewire Volt SFC | Project baseline | All pages are SFC — follow this always |
| `wire:confirm` for delete | `<x-modal>` component | Project decision | More control over messaging and styling |

## Open Questions

1. **User model `shuffles()` relationship**
   - What we know: `Shuffle` model has `user_id` FK and `belongsTo(User::class)`.
   - What's unclear: Whether `User::shuffles()` `HasMany` is already defined (not seen in the files read, but User model not fully read).
   - Recommendation: Check `app/Models/User.php` at plan time; add `HasMany shuffles()` if missing. This is a one-line addition.

2. **Profit calculation for multi-step shuffles with intermediate quantities**
   - What we know: Phase 10 badge is display-only; steps are not configurable yet (Phase 11).
   - What's unclear: Whether the naive first-in/last-out calculation is accurate enough for multi-step chains.
   - Recommendation: Implement naive calculation with clear comment noting Phase 12 will refine it when batch calculator is built. Return `null` if any prices unavailable.

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | Pest PHP (via PHPUnit) |
| Config file | `phpunit.xml` |
| Quick run command | `php artisan test --filter ShuffleCrud` |
| Full suite command | `php artisan test` |

### Phase Requirements → Test Map
| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| SHUF-01 | User can create a named shuffle | Feature | `php artisan test --filter "user can create a shuffle"` | Wave 0 |
| SHUF-03 | User can rename an existing shuffle | Feature | `php artisan test --filter "user can rename a shuffle"` | Wave 0 |
| SHUF-04 | User can delete a shuffle | Feature | `php artisan test --filter "user can delete a shuffle"` | Wave 0 |
| SHUF-05 | Shuffles list shows profitability badge | Feature | `php artisan test --filter "shuffles list shows profitability badge"` | Wave 0 |

Additional tests needed:
| Behavior | Test Type | Command |
|----------|-----------|---------|
| `/shuffles` redirects unauthenticated users | Feature | `php artisan test --filter "shuffles redirects unauthenticated"` |
| `/shuffles/{id}` returns 403 for wrong user | Feature | `php artisan test --filter "shuffle detail returns 403"` |
| Shuffles nav link is active on list and detail pages | Feature | `php artisan test --filter "shuffles nav link is active"` |
| Create shuffle immediately navigates to detail | Feature | `php artisan test --filter "create shuffle navigates to detail"` |

### Sampling Rate
- **Per task commit:** `php artisan test --filter ShuffleCrud`
- **Per wave merge:** `php artisan test`
- **Phase gate:** Full suite green before `/gsd:verify-work`

### Wave 0 Gaps
- [ ] `tests/Feature/ShuffleCrudTest.php` — covers SHUF-01, SHUF-03, SHUF-04, SHUF-05

*(Pest.php, TestCase.php, and factory infrastructure are already in place from Phase 9.)*

## Sources

### Primary (HIGH confidence)
- Existing codebase: `resources/views/livewire/pages/watchlist.blade.php` — inline edit Alpine pattern, Volt SFC structure, table layout
- Existing codebase: `resources/views/livewire/layout/navigation.blade.php` — nav link locations, `<x-nav-link>` / `<x-responsive-nav-link>` usage
- Existing codebase: `resources/views/components/modal.blade.php` — modal API (`name` prop, Alpine dispatch)
- Existing codebase: `app/Models/Shuffle.php` — model structure, relationships, orphan cleanup boot logic
- Existing codebase: `app/Models/ShuffleStep.php` — step relationships to CatalogItem
- Existing codebase: `app/Concerns/FormatsAuctionData.php` — `formatGold()` signature
- Existing codebase: `routes/web.php` — `Volt::route()` pattern with middleware chain
- Existing codebase: `tests/Feature/WatchlistTest.php` — `Volt::actingAs()->test()->call()` pattern
- `.planning/phases/10-shuffle-crud-navigation/10-CONTEXT.md` — locked user decisions

### Secondary (MEDIUM confidence)
- Livewire Volt 1.7 docs — `#[Computed]` attribute, `#[Layout]` attribute, `$this->redirect(..., navigate: true)` for SPA navigation

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — entirely existing project libraries, no new dependencies
- Architecture: HIGH — directly derived from existing Watchlist + item-detail patterns in the codebase
- Pitfalls: HIGH — derived from existing code patterns and observed conventions (authorization in `mount()`, `wire:key` in loops)
- Profit calculation: MEDIUM — multi-step chain accuracy depends on Phase 11 step structure; current naive approach is acceptable for Phase 10 badge

**Research date:** 2026-03-04
**Valid until:** 2026-06-04 (stable Laravel/Livewire ecosystem)
