# Phase 3: Item Watchlist Management - Context

**Gathered:** 2026-03-01
**Status:** Ready for planning

<domain>
## Phase Boundary

Each logged-in user can maintain their own independent watchlist of commodity items with per-item buy and sell thresholds, managed through a dedicated admin page. Covers CRUD operations for watched items and threshold editing. Blizzard API integration (live search, price fetching) is Phase 4 — this phase uses a static item catalog.

</domain>

<decisions>
## Implementation Decisions

### Item search & add flow
- Static catalog of common WoW crafting materials (herbs, ores, cloth, leather, enchanting mats, gems) seeded into the database
- Primary add flow: searchable dropdown (combobox pattern) filtering the catalog
- Fallback: manual Blizzard item ID entry for power users who know the exact ID
- Catalog is the curated source — no Blizzard API dependency in this phase

### Watchlist display & layout
- Table layout: dense, scannable rows
- Columns: Item Name, Buy Threshold (%), Sell Threshold (%), Remove button
- Blizzard Item ID shown as secondary text (subtitle or tooltip), not a full column
- Empty state: centered "No items on your watchlist yet" message with prominent "Add your first item" button that focuses the search dropdown
- Instant remove — click remove button, item disappears immediately, no confirmation modal

### Threshold editing UX
- Inline editing: click threshold value in the table row to make it editable
- Save on Enter or blur, auto-persists via Livewire wire:model
- Default thresholds: 10% for both buy and sell when adding a new item
- Validation: 1-100% range enforced
- Save feedback: subtle green checkmark or flash on the cell after save, disappears after ~1 second

### Where it lives in the app
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

</decisions>

<specifics>
## Specific Ideas

- Catalog should focus on crafting materials — the items that fluctuate most and are most useful for buy/sell decisions
- Power users get a manual ID fallback so they're not limited to the curated catalog
- Inline editing should feel fast and direct — no modals, no separate edit pages

</specifics>

<code_context>
## Existing Code Insights

### Reusable Assets
- `WatchedItem` model: Already has `user_id`, `blizzard_item_id`, `name`, `buy_threshold`, `sell_threshold` with casts and relationships
- `WatchedItemFactory`: Ready for testing with faker data
- `PriceSnapshot` model: Has `belongsTo(WatchedItem)` relationship already wired
- WoW dark theme: `bg-wow-dark`, `bg-wow-darker`, `text-wow-gold`, `text-wow-gold-light`, `border-gray-700/50` pattern established across auth views

### Established Patterns
- Livewire/Volt stack with Breeze for interactive components
- App layout (`x-app-layout`) with named header slot and content area
- Navigation component at `livewire/layout/navigation.blade.php` with responsive mobile menu
- Auth middleware on routes (`->middleware(['auth'])`)

### Integration Points
- `routes/web.php`: Add `/watchlist` route with auth middleware
- `livewire/layout/navigation.blade.php`: Add "Watchlist" nav link
- `dashboard.blade.php`: Add item count summary (currently placeholder)
- `WatchedItem` model: Add user scoping (already has `user()` relationship)

</code_context>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 03-item-watchlist-management*
*Context gathered: 2026-03-01*
