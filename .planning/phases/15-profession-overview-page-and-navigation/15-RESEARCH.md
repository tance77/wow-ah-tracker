# Phase 15: Profession Overview Page and Navigation - Research

**Researched:** 2026-03-05
**Domain:** Livewire Volt page + navigation integration + Eloquent aggregation
**Confidence:** HIGH

## Summary

Phase 15 adds a "Crafting" nav link and a `/crafting` overview page displaying profession cards with top 5 profitable recipes each. The entire implementation uses established project patterns: Livewire Volt SFC, `#[Computed]` properties, `RecipeProfitAction` for profit calculation, `FormatsAuctionData` trait for gold formatting, and the existing WoW dark theme with Tailwind.

The main technical concern is the N+1 query problem: computing profit for every recipe in every profession requires eager-loading reagents with price snapshots and crafted item price snapshots. A single well-structured eager load query with a computed property that iterates professions and ranks recipes will keep this performant.

**Primary recommendation:** Build as a single Livewire Volt SFC page (`pages.crafting`) with one `#[Computed]` method that eager-loads all professions with recipes and their price relationships, runs `RecipeProfitAction` per recipe, groups by profession, and returns a sorted collection for rendering.

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions
- Each card shows: profession icon (from `icon_url`), profession name, top 5 recipes, recipe stats ("X of Y profitable")
- Recipe stats show total recipe count and how many are profitable (median_profit > 0)
- Whole card is clickable, links to `/crafting/{profession-slug}` detail page (Phase 16)
- Recipes with missing price data excluded from the top list
- Professions with zero profitable recipes still show a card with "No profitable recipes" message
- Show top 5 recipes per profession, sorted by median profit descending
- Each recipe shows: name + median profit in gold format only (no tier breakdown, no reagent cost)
- Always show 5 recipes even if some are at a loss -- use red/negative styling for loss recipes
- If fewer than 5 recipes have complete data, show what's available
- Responsive grid: 3 columns desktop, 2 tablet, 1 mobile (matches dashboard grid pattern)
- Profession cards sorted by most profitable first (top recipe's median profit descending)
- Page header with title + summary stats (e.g. "8 professions - 142 recipes - 67 profitable")
- Show Blizzard profession icons on cards (stored in `professions.icon_url` from Phase 13 sync)
- Nav link labeled "Crafting" placed after Shuffles: Dashboard -> Watchlist -> Shuffles -> Crafting
- Route: `/crafting` for overview page
- Detail page route: `/crafting/{slug}` using profession name slugs (e.g. `/crafting/alchemy`)
- Slug column needed on professions table (or generated from name)
- Active state highlights "Crafting" nav link on both `/crafting` and `/crafting/*` pages (`routeIs('crafting*')`)

### Claude's Discretion
- Ranked numbering vs plain sorted list for top recipes
- Exact card sizing, spacing, and typography
- Loading skeleton / spinner design
- Error state handling
- Summary stats formatting and layout

### Deferred Ideas (OUT OF SCOPE)
None -- discussion stayed within phase scope
</user_constraints>

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| OVERVIEW-01 | Crafting page shows cards for each Midnight profession | Livewire Volt SFC page at `/crafting` with `#[Computed]` property loading professions with recipes; responsive card grid layout |
| OVERVIEW-02 | Each profession card displays top 3-5 most profitable recipes | `RecipeProfitAction` computes `median_profit` per recipe; sort descending, take top 5, exclude `has_missing_prices` from ranking |
| NAV-01 | "Crafting" link added to main navigation | Add `x-nav-link` and `x-responsive-nav-link` entries in `navigation.blade.php` with `routeIs('crafting*')` active state |
</phase_requirements>

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| Livewire Volt | 1.x | SFC page component | Project standard for all pages |
| Laravel Eloquent | 12.x | Data access + eager loading | Project ORM, established relationships |
| Tailwind CSS | 4.x | Styling + responsive grid | Project CSS framework with custom WoW theme |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| `RecipeProfitAction` | local | Profit calculation per recipe | Already built in Phase 14 |
| `FormatsAuctionData` trait | local | `formatGold()` for copper-to-display | Already used in dashboard, shuffles |
| `Str::slug()` | Laravel | Generate URL slugs from profession names | For route model binding or slug generation |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Slug column on professions table | `Str::slug($profession->name)` at runtime | DB column is more reliable for route binding; runtime generation works for 8 professions but less robust |
| `RecipeProfitAction` per recipe | Raw SQL aggregation | Action is already built and tested; SQL would duplicate logic |

## Architecture Patterns

### Recommended Project Structure
```
resources/views/livewire/pages/
  crafting.blade.php          # New: profession overview page (Volt SFC)
resources/views/livewire/layout/
  navigation.blade.php        # Modified: add Crafting nav link
routes/
  web.php                     # Modified: add Volt::route for /crafting
database/migrations/
  XXXX_add_slug_to_professions_table.php  # New: add slug column
app/Models/
  Profession.php              # Modified: add slug attribute/accessor
```

### Pattern 1: Livewire Volt SFC Page
**What:** Single-file component with PHP class + Blade template in one `.blade.php` file
**When to use:** Every page in this project
**Example:**
```php
// Source: existing project pattern (dashboard.blade.php, shuffles.blade.php)
<?php
declare(strict_types=1);

use App\Actions\RecipeProfitAction;
use App\Concerns\FormatsAuctionData;
use App\Models\Profession;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    use FormatsAuctionData;

    #[Computed]
    public function professions(): Collection
    {
        // Eager load all relationships needed by RecipeProfitAction
        $professions = Profession::with([
            'recipes.reagents.catalogItem.priceSnapshots' => fn ($q) => $q->latest('polled_at')->limit(1),
            'recipes.craftedItemSilver.priceSnapshots' => fn ($q) => $q->latest('polled_at')->limit(1),
            'recipes.craftedItemGold.priceSnapshots' => fn ($q) => $q->latest('polled_at')->limit(1),
        ])->get();

        $action = new RecipeProfitAction();

        return $professions->map(function (Profession $profession) use ($action) {
            // Compute profit for each recipe
            $recipesWithProfit = $profession->recipes
                ->map(fn ($recipe) => [
                    'recipe' => $recipe,
                    'profit' => $action($recipe),
                ])
                ->filter(fn ($r) => $r['profit']['median_profit'] !== null) // exclude missing price data
                ->sortByDesc(fn ($r) => $r['profit']['median_profit'])
                ->take(5)
                ->values();

            $totalRecipes = $profession->recipes->count();
            $profitableCount = $profession->recipes
                ->map(fn ($recipe) => $action($recipe))
                ->filter(fn ($p) => $p['median_profit'] !== null && $p['median_profit'] > 0)
                ->count();

            $profession->_top_recipes = $recipesWithProfit;
            $profession->_total_recipes = $totalRecipes;
            $profession->_profitable_count = $profitableCount;
            $profession->_best_profit = $recipesWithProfit->first()['profit']['median_profit'] ?? null;

            return $profession;
        })->sortByDesc('_best_profit')->values();
    }
};
```

### Pattern 2: Navigation Link Addition
**What:** Add `x-nav-link` in desktop nav and `x-responsive-nav-link` in mobile nav
**When to use:** Any new top-level page
**Example:**
```blade
{{-- Desktop nav (inside .hidden.sm:flex div) --}}
<x-nav-link :href="route('crafting')" :active="request()->routeIs('crafting*')" wire:navigate>
    {{ __('Crafting') }}
</x-nav-link>

{{-- Mobile nav (inside .pt-2.pb-3 div) --}}
<x-responsive-nav-link :href="route('crafting')" :active="request()->routeIs('crafting*')" wire:navigate>
    {{ __('Crafting') }}
</x-responsive-nav-link>
```

### Pattern 3: Route Registration
**What:** Volt::route with auth middleware and named route
**When to use:** New pages
**Example:**
```php
// Source: existing routes/web.php pattern
Volt::route('/crafting', 'pages.crafting')
    ->middleware(['auth'])
    ->name('crafting');
```

### Pattern 4: Slug Column + Route Model Binding
**What:** Add `slug` column to professions, use for URL routing
**When to use:** Human-readable URLs for profession detail pages
**Example:**
```php
// Migration
$table->string('slug')->unique()->after('name');

// Model accessor or boot method to auto-generate
protected static function booted(): void
{
    static::creating(fn (Profession $p) => $p->slug = $p->slug ?? Str::slug($p->name));
    static::updating(fn (Profession $p) => $p->slug = Str::slug($p->name));
}

// Or add to SyncRecipes command when creating/updating professions
```

### Anti-Patterns to Avoid
- **N+1 queries in template:** Never call `$recipe->reagents` in Blade without eager loading -- use the `#[Computed]` method to pre-compute all data
- **Calling RecipeProfitAction twice per recipe:** The top-recipe filter and the profitable-count stat should share the same computed profit data, not re-invoke the action
- **Hardcoding profession order:** Sort by computed profitability, not alphabetical or ID order

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Profit calculation | Custom SQL or inline math | `RecipeProfitAction` | Already handles AH cut, missing prices, tier averaging |
| Gold formatting | Inline copper-to-gold math in template | `$this->formatGold()` via `FormatsAuctionData` trait | Handles negatives, proper g/s/c formatting |
| URL slugs | Manual string manipulation | `Str::slug()` | Handles edge cases, Unicode, special characters |
| Responsive grid | Custom CSS breakpoints | Tailwind `grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3` | Matches dashboard pattern |

**Key insight:** Phase 14 built `RecipeProfitAction` specifically for this use case. The entire profit computation is encapsulated -- just eager-load the right relationships and invoke it.

## Common Pitfalls

### Pitfall 1: Eager Loading Depth for RecipeProfitAction
**What goes wrong:** `RecipeProfitAction` accesses `$recipe->reagents->each->catalogItem->priceSnapshots->first()` and `$recipe->craftedItemSilver->priceSnapshots->first()`. Missing any nested relationship causes N+1 queries or null errors.
**Why it happens:** The eager load chain is 3 levels deep with multiple branches.
**How to avoid:** Use the exact eager load structure from the code example above. Test with `DB::enableQueryLog()` to verify query count.
**Warning signs:** Page load taking >1 second, Laravel Debugbar showing 50+ queries.

### Pitfall 2: Double-Computing Profits
**What goes wrong:** Computing profit once for top-recipe ranking and again for the "X of Y profitable" stat duplicates work.
**Why it happens:** Natural to compute in separate loops.
**How to avoid:** Compute profit for all recipes once in the `#[Computed]` method, then derive both the top-5 list and the profitable count from the same data.
**Warning signs:** `RecipeProfitAction` being invoked 2x per recipe in profiler.

### Pitfall 3: Slug Collision or Missing Slug
**What goes wrong:** Route model binding fails because slug column doesn't exist or wasn't populated.
**Why it happens:** Forgetting to run the migration or forgetting to backfill existing profession rows.
**How to avoid:** Migration should add the column AND backfill existing rows using `Str::slug($name)`. Include a data migration step.
**Warning signs:** 404 errors when clicking profession cards.

### Pitfall 4: Profession Cards Linking to Phase 16 Routes That Don't Exist Yet
**What goes wrong:** Clicking a profession card navigates to `/crafting/{slug}` which returns 404.
**Why it happens:** Phase 16 adds the detail page; Phase 15 only adds the overview.
**How to avoid:** Register the `/crafting/{slug}` route in Phase 15 with a placeholder page, or make the card link conditional. Recommended: register the route pointing to a minimal placeholder component.
**Warning signs:** Broken links in production.

### Pitfall 5: Missing Price Data Exclusion Logic
**What goes wrong:** Recipes with `has_missing_prices: true` or `median_profit: null` still appear in top-5 ranking.
**Why it happens:** Not filtering properly on the `RecipeProfitAction` return value.
**How to avoid:** Filter `median_profit !== null` before sorting. Per user decision: exclude recipes with missing price data from the top list, but still count them in total recipe count.
**Warning signs:** "N/A" or "0g" showing up in top recipe lists.

## Code Examples

### Responsive Card Grid (matching dashboard pattern)
```blade
{{-- Source: existing dashboard.blade.php grid pattern --}}
<div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
    @foreach ($this->professions as $profession)
        <a
            href="{{ route('crafting.show', $profession->slug) }}"
            wire:navigate
            wire:key="profession-{{ $profession->id }}"
            class="block rounded-lg border border-gray-700/50 bg-wow-dark p-5 transition-colors hover:border-wow-gold/50"
        >
            {{-- Card content --}}
        </a>
    @endforeach
</div>
```

### Profit Display with Red/Green Styling
```blade
{{-- Source: existing shuffle profitability pattern --}}
@if ($profit >= 0)
    <span class="text-green-400">+{{ $this->formatGold($profit) }}</span>
@else
    <span class="text-red-400">{{ $this->formatGold($profit) }}</span>
@endif
```

### Page Header with Summary Stats
```blade
{{-- Source: existing dashboard header pattern --}}
<x-slot name="header">
    <div class="flex items-center justify-between">
        <h2 class="text-xl font-semibold leading-tight text-wow-gold">
            {{ __('Crafting') }}
        </h2>
        <span class="text-sm text-gray-400">
            {{ $this->professions->count() }} professions
            &bull; {{ $this->totalRecipes }} recipes
            &bull; {{ $this->profitableRecipes }} profitable
        </span>
    </div>
</x-slot>
```

### Profession Icon Display
```blade
@if ($profession->icon_url)
    <img src="{{ $profession->icon_url }}" alt="{{ $profession->name }}" class="h-10 w-10 rounded" loading="lazy" />
@endif
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Full Livewire components in separate class/view files | Livewire Volt SFC (single file) | Livewire 3 / Volt 1.0 | All project pages use Volt SFC pattern |
| `#[Layout('layouts.app')]` as attribute | Same (current) | Livewire 3 | Project standard |

**Deprecated/outdated:**
- Livewire 2 `$this->render()` pattern -- replaced by Volt SFC `#[Layout]` attribute

## Open Questions

1. **Slug storage vs runtime generation**
   - What we know: Professions table has no `slug` column currently. Only ~8 Midnight professions exist.
   - What's unclear: Whether to add a DB column or generate at runtime via `Str::slug()`
   - Recommendation: Add a `slug` column with migration + backfill. More robust for route model binding and future use in Phase 16.

2. **Phase 16 route placeholder**
   - What we know: Cards link to `/crafting/{slug}` but that page is Phase 16
   - What's unclear: Should Phase 15 register the route with a placeholder?
   - Recommendation: Register the route pointing to a minimal "Coming soon" or empty component to prevent 404s. Or defer the linking to Phase 16 and make cards non-clickable until then. User decision says "whole card is clickable" so a placeholder route is better.

3. **RecipeProfitAction performance at scale**
   - What we know: Currently ~100-200 recipes across 8 professions. Action is invoked per-recipe.
   - What's unclear: Whether computing all profits on every page load is acceptable
   - Recommendation: At current scale (8 professions x ~20 recipes = ~160 invocations), this is fine. No caching needed yet. If recipe count grows significantly, consider caching profits with short TTL.

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | Pest 3.x (PHP) |
| Config file | `phpunit.xml` + `tests/Pest.php` |
| Quick run command | `php artisan test --filter=CraftingOverview` |
| Full suite command | `php artisan test` |

### Phase Requirements -> Test Map
| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| NAV-01 | Crafting link appears in nav, routes to /crafting | Feature (HTTP) | `php artisan test --filter=CraftingOverviewTest::it_shows_crafting_nav_link -x` | No - Wave 0 |
| OVERVIEW-01 | /crafting page displays profession cards | Feature (HTTP) | `php artisan test --filter=CraftingOverviewTest::it_displays_profession_cards -x` | No - Wave 0 |
| OVERVIEW-02 | Each card shows top 5 profitable recipes | Feature (HTTP) | `php artisan test --filter=CraftingOverviewTest::it_shows_top_recipes_per_profession -x` | No - Wave 0 |

### Sampling Rate
- **Per task commit:** `php artisan test --filter=CraftingOverview`
- **Per wave merge:** `php artisan test`
- **Phase gate:** Full suite green before `/gsd:verify-work`

### Wave 0 Gaps
- [ ] `tests/Feature/CraftingOverviewTest.php` -- covers NAV-01, OVERVIEW-01, OVERVIEW-02
- [ ] Profession factory needed (if not already in `database/factories/`)
- [ ] Recipe factory with reagents and price snapshots for test data setup

## Sources

### Primary (HIGH confidence)
- Project codebase: `app/Actions/RecipeProfitAction.php` -- exact API and eager load requirements
- Project codebase: `resources/views/livewire/layout/navigation.blade.php` -- exact nav structure to modify
- Project codebase: `routes/web.php` -- route registration pattern
- Project codebase: `resources/views/livewire/pages/dashboard.blade.php` -- Volt SFC + card grid pattern
- Project codebase: `database/migrations/2026_03_06_200000_create_professions_table.php` -- current schema (no slug column)

### Secondary (MEDIUM confidence)
- Project codebase: `app/Concerns/FormatsAuctionData.php` -- formatGold() method signature and behavior

### Tertiary (LOW confidence)
- None

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - all tools already exist in the project, no new dependencies
- Architecture: HIGH - follows exact patterns from dashboard.blade.php and shuffles.blade.php
- Pitfalls: HIGH - derived from direct analysis of RecipeProfitAction eager load requirements

**Research date:** 2026-03-05
**Valid until:** 2026-04-05 (stable -- no external dependencies, all project-internal)
