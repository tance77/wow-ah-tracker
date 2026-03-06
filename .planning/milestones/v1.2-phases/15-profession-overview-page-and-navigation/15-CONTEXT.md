# Phase 15: Profession Overview Page and Navigation - Context

**Gathered:** 2026-03-05
**Status:** Ready for planning

<domain>
## Phase Boundary

Users can navigate to a Crafting section and see all Midnight professions at a glance with the most profitable recipes highlighted per profession. Delivers the overview page and navigation link. Per-profession detail tables are Phase 16.

</domain>

<decisions>
## Implementation Decisions

### Profession card content
- Each card shows: profession icon (from `icon_url`), profession name, top 5 recipes, recipe stats ("X of Y profitable")
- Recipe stats show total recipe count and how many are profitable (median_profit > 0)
- Whole card is clickable, links to `/crafting/{profession-slug}` detail page (Phase 16)
- Recipes with missing price data excluded from the top list
- Professions with zero profitable recipes still show a card with "No profitable recipes" message

### Top recipe display
- Show top 5 recipes per profession, sorted by median profit descending
- Each recipe shows: name + median profit in gold format only (no tier breakdown, no reagent cost)
- Always show 5 recipes even if some are at a loss — use red/negative styling for loss recipes
- If fewer than 5 recipes have complete data, show what's available

### Page layout and ordering
- Responsive grid: 3 columns desktop, 2 tablet, 1 mobile (matches dashboard grid pattern)
- Profession cards sorted by most profitable first (top recipe's median profit descending)
- Page header with title + summary stats (e.g. "8 professions • 142 recipes • 67 profitable")
- Show Blizzard profession icons on cards (stored in `professions.icon_url` from Phase 13 sync)

### Navigation
- Nav link labeled "Crafting" placed after Shuffles (last position): Dashboard → Watchlist → Shuffles → Crafting
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

</decisions>

<specifics>
## Specific Ideas

No specific requirements — open to standard approaches matching existing WoW dark theme with gold/amber accents.

</specifics>

<code_context>
## Existing Code Insights

### Reusable Assets
- `RecipeProfitAction`: Returns `median_profit`, `has_missing_prices`, per-tier profits — use for ranking and display
- `FormatsAuctionData` trait: `formatGold()` method converts copper to g/s/c display format
- `x-tier-pip` component: Quality tier display (T1/T2/T3) — could annotate recipes if needed
- `x-nav-link` component: Navigation links with active state via `$active` prop and WoW gold highlight
- Profession `icon_url` stored during Phase 13 sync — ready for card display

### Established Patterns
- Livewire Volt SFC: `#[Layout('layouts.app')]`, `#[Computed]` for reactive data
- Route pattern: `Volt::route('/path', 'pages.component-name')->middleware(['auth'])->name('route.name')`
- Card styling: `rounded-lg border border-gray-700/50 bg-wow-dark p-5 transition-colors`
- Section headers: `text-sm font-semibold uppercase tracking-wide text-wow-gold`
- Dashboard grid: responsive grid with Tailwind grid classes
- WoW theme: `bg-wow-dark`, `bg-wow-darker`, `text-wow-gold`, gold/amber accents

### Integration Points
- Navigation: add `x-nav-link` in `resources/views/livewire/layout/navigation.blade.php` (desktop + mobile)
- Routes: add Volt::route entries in `routes/web.php`
- Models: `Profession` hasMany `Recipe`, `Recipe` hasMany `RecipeReagent`, eager load with priceSnapshots
- `RecipeProfitAction` invoked per recipe to compute profits for ranking

</code_context>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 15-profession-overview-page-and-navigation*
*Context gathered: 2026-03-05*
