# Phase 10: Shuffle CRUD and Navigation - Context

**Gathered:** 2026-03-04
**Status:** Ready for planning

<domain>
## Phase Boundary

Users can access a dedicated Shuffles section, view all their saved shuffles with profitability badges, and create, rename, or delete shuffles. Step editing, yield configuration, and batch calculation are separate phases (11 and 12).

</domain>

<decisions>
## Implementation Decisions

### Shuffles list layout
- Simple table/list layout — one row per shuffle, consistent with Watchlist page style
- Each row shows: shuffle name, step count, chain preview (input → output items), and profitability badge
- Clicking a shuffle row navigates to `/shuffles/{id}` detail page (consistent with watchlist item → item detail pattern)
- Empty state: brief explanation of what shuffles are (item conversion chains for profit tracking) plus a prominent "Create Shuffle" button

### Profitability badge
- Color dot + profit amount in gold/silver/copper format (green for profitable, red for unprofitable)
- Per-unit profit calculation (1 input through the chain) — includes 5% AH cut for realistic numbers
- Edge states: neutral gray badge with dash ("—") when shuffle has no steps or prices are unavailable
- Badge calculates live from latest price snapshots (no cached column — decided in Phase 9)

### Create/edit/delete flow
- **Create:** "New Shuffle" button creates a shuffle with a default name and immediately navigates to `/shuffles/{id}` detail page where user can rename and later add steps
- **Rename:** Inline edit on the list page — click the name to make it editable, press Enter or click away to save. Also editable on detail page
- **Delete:** Delete button with confirmation modal warning that steps will be deleted and auto-watched items not used by other shuffles will also be removed from the watchlist

### Navigation
- Nav order: Dashboard | Watchlist | Shuffles — appended after existing links
- Both desktop (`<x-nav-link>`) and mobile responsive (`<x-responsive-nav-link>`) menus updated
- Route: `/shuffles` for list, `/shuffles/{shuffle}` for detail

### Shuffle detail page (shell)
- Shell detail page created in Phase 10 at `/shuffles/{id}`
- Shows: shuffle name (editable), profitability badge, and placeholder section for step editor (Phase 11)
- Delete button accessible from detail page as well
- Back link to `/shuffles` list

### Claude's Discretion
- Exact Tailwind styling and spacing for shuffle rows
- Loading state patterns for the list
- Error handling for failed creates/deletes
- Detail page placeholder content and layout

</decisions>

<specifics>
## Specific Ideas

- Shuffles list should feel consistent with the existing Watchlist page — same dark WoW theme, gold accents, similar row density
- Chain preview in rows (e.g., "Ore → Gems → Rings") gives quick visual context without needing to click through

</specifics>

<code_context>
## Existing Code Insights

### Reusable Assets
- `nav-link.blade.php` / `responsive-nav-link.blade.php`: Existing nav components with WoW gold theme styling — reuse for Shuffles link
- `navigation.blade.php`: Livewire Volt SFC nav — add Shuffles link in both desktop and mobile sections
- `modal.blade.php`: Existing modal component — reuse for delete confirmation
- `Shuffle` / `ShuffleStep` models: Created in Phase 9 with relationships ready
- `FormatsAuctionData` concern: Has gold/silver/copper formatting — reuse for profit badge display
- `CatalogItem` model: Resolves item names from `blizzard_item_id` — used for chain preview text

### Established Patterns
- Volt SFC pages: All pages use `#[Layout('layouts.app')]` attribute on anonymous component classes
- Route definitions: `Volt::route()` with `->middleware(['auth'])->name()` chain in `web.php`
- Computed properties: `#[Computed]` attribute for derived data on Livewire components
- Item display: `catalogItem` relationship for resolving blizzard_item_id to display names

### Integration Points
- `routes/web.php`: Add `/shuffles` and `/shuffles/{shuffle}` routes
- `navigation.blade.php`: Add Shuffles nav link (desktop + mobile)
- `resources/views/livewire/pages/`: New `shuffles.blade.php` and `shuffle-detail.blade.php` Volt SFCs
- `Shuffle` model: Add profit calculation method (per-unit, with AH cut)

</code_context>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 10-shuffle-crud-navigation*
*Context gathered: 2026-03-04*
