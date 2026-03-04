---
phase: quick-5
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - database/migrations/2026_03_04_000000_add_profession_to_watched_items.php
  - app/Models/WatchedItem.php
  - resources/views/livewire/pages/watchlist.blade.php
  - resources/views/livewire/pages/dashboard.blade.php
autonomous: true
requirements: [QUICK-5]
must_haves:
  truths:
    - "User can assign a profession to any watched item via dropdown on the watchlist page"
    - "Dashboard groups items by profession in collapsible sections"
    - "Items without a profession appear in an Ungrouped section at the bottom"
    - "Within each profession group, items are sorted by signal status, then magnitude, then name"
  artifacts:
    - path: "database/migrations/2026_03_04_000000_add_profession_to_watched_items.php"
      provides: "Adds nullable profession column to watched_items table"
    - path: "app/Models/WatchedItem.php"
      provides: "PROFESSIONS constant, profession in fillable/casts"
    - path: "resources/views/livewire/pages/watchlist.blade.php"
      provides: "Profession dropdown column in watchlist table"
    - path: "resources/views/livewire/pages/dashboard.blade.php"
      provides: "Collapsible profession-grouped sections"
  key_links:
    - from: "resources/views/livewire/pages/watchlist.blade.php"
      to: "app/Models/WatchedItem.php"
      via: "updateProfession Livewire method"
      pattern: "updateProfession"
    - from: "resources/views/livewire/pages/dashboard.blade.php"
      to: "watchedItems computed property"
      via: "groupBy profession in computed property"
      pattern: "groupBy.*profession"
---

<objective>
Add profession grouping to the dashboard with manual tagging on the watchlist page.

Purpose: Let users organize tracked items by WoW profession so they can quickly scan relevant crafting material prices grouped by their profession of interest.
Output: Migration adding profession column, profession dropdown on watchlist, collapsible profession-grouped sections on dashboard.
</objective>

<execution_context>
@/Users/lancethompson/.claude/get-shit-done/workflows/execute-plan.md
@/Users/lancethompson/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@.planning/STATE.md
@app/Models/WatchedItem.php
@resources/views/livewire/pages/watchlist.blade.php
@resources/views/livewire/pages/dashboard.blade.php
</context>

<tasks>

<task type="auto">
  <name>Task 1: Add profession column and update model + watchlist UI</name>
  <files>
    database/migrations/2026_03_04_000000_add_profession_to_watched_items.php
    app/Models/WatchedItem.php
    resources/views/livewire/pages/watchlist.blade.php
  </files>
  <action>
1. Create migration `2026_03_04_000000_add_profession_to_watched_items.php`:
   - Add nullable string column `profession` to `watched_items` table after `sell_threshold`
   - Add index on `profession` column

2. Update `app/Models/WatchedItem.php`:
   - Add `'profession'` to `$fillable` array
   - Add a public const PROFESSIONS array with these values (alphabetical): Alchemy, Blacksmithing, Cooking, Enchanting, Engineering, Fishing, Herbalism, Inscription, Jewelcrafting, Leatherworking, Mining, Skinning, Tailoring
   - No cast needed (it's already a string)

3. Run `php artisan migrate` to apply the migration.

4. Update `resources/views/livewire/pages/watchlist.blade.php`:
   - Add `updateProfession(int $id, ?string $value): void` method to the component PHP section. It should find the user's watched item by ID and update the `profession` field. If value is empty string, set to null.
   - Add a "Profession" column header between "Item Name" and "Buy Threshold (%)" in the table header
   - Add a table cell with a `<select>` dropdown for each item row, positioned between the item name cell and buy threshold cell
   - The select should have: empty option "-- None --" (value=""), then one option per WatchedItem::PROFESSIONS entry
   - Wire the select with `wire:change="updateProfession({{ $item->id }}, $event.target.value)"`
   - Style the select to match existing inputs: `rounded-md border border-gray-600 bg-wow-darker px-2 py-1 text-sm text-gray-100 focus:border-wow-gold focus:ring-wow-gold`
   - Pre-select the current profession value using `:selected` or just standard HTML selected attribute
  </action>
  <verify>
    <automated>cd /Users/lancethompson/Github/wow-ah-tracker && php artisan migrate --force 2>&1 && php artisan tinker --execute="echo App\Models\WatchedItem::PROFESSIONS[0];" 2>&1</automated>
  </verify>
  <done>Migration applied, WatchedItem model has PROFESSIONS constant and profession in fillable, watchlist page shows profession dropdown per item row</done>
</task>

<task type="auto">
  <name>Task 2: Add collapsible profession-grouped sections to dashboard</name>
  <files>resources/views/livewire/pages/dashboard.blade.php</files>
  <action>
1. Update the `watchedItems()` computed property in the dashboard component to return a Collection grouped by profession:
   - Keep the existing query and signal computation logic exactly as-is
   - After computing signals and sorting (the existing sortBy that sorts by signal status first, then magnitude, then name), group the sorted collection by profession
   - Create a new computed property `groupedWatchedItems()` that:
     a. Takes `$this->watchedItems` (already sorted by signal/magnitude/name)
     b. Groups by `profession` field using `->groupBy('profession')`
     c. Reorders the groups: named professions alphabetically first, then null/empty ("Ungrouped") last
     d. Returns the grouped collection

2. Update the Blade template to render grouped sections instead of a flat list:
   - Wrap the existing grid/list rendering in a `@foreach ($this->groupedWatchedItems as $profession => $items)` loop
   - For each group, render a collapsible section header using Alpine.js `x-data="{ open: true }"`:
     - Section header: profession name (or "Ungrouped" if key is empty/null), styled as a bar with:
       - Background: `bg-wow-dark/80 border border-gray-700/50 rounded-lg`
       - Text: `text-wow-gold font-semibold text-sm uppercase tracking-wide`
       - Item count badge: `text-xs text-gray-400 ml-2` showing "(N items)"
       - Chevron icon on the right that rotates when collapsed (use `x-bind:class="{ 'rotate-180': open }"` on an SVG chevron-down)
       - Click handler: `@click="open = !open"` with `cursor-pointer`
       - Add `mb-2 mt-4` spacing (except first group which should have `mt-0`)
     - Section body: wrap existing grid or list content in `x-show="open"` with `x-collapse` for smooth animation (Alpine collapse plugin is likely available; if not, use `x-transition` instead)
   - For grid view: each group gets its own grid container with the existing grid classes
   - For list view: each group gets its own table with the existing table structure (header row + body)
   - Remove loading skeletons from inside groups (keep one set at the outer level if desired, or remove entirely)

3. Important: The existing sort order (signal status first, magnitude, name) MUST be preserved within each group. The grouping happens AFTER sorting so items within each profession section maintain their signal-priority order.

4. If there are no items at all, the existing empty state should still display (no change needed there).
  </action>
  <verify>
    <automated>cd /Users/lancethompson/Github/wow-ah-tracker && php artisan view:cache 2>&1 && echo "Views compile OK"</automated>
  </verify>
  <done>Dashboard displays items grouped by profession in collapsible sections. Named professions appear alphabetically, "Ungrouped" appears last. Within each section, items sorted by signal status then magnitude then name. Sections can be collapsed/expanded by clicking the header.</done>
</task>

</tasks>

<verification>
- Visit watchlist page: profession dropdown visible for each item, can assign and change professions
- Visit dashboard: items grouped under profession section headers, collapsible via click
- Items with no profession appear under "Ungrouped" at the bottom
- Signal-based sort order preserved within each group
- Both grid and list views work with grouping
</verification>

<success_criteria>
- Profession column exists in database (nullable string)
- Watchlist page has profession dropdown per item
- Dashboard groups items by profession in collapsible sections
- Ungrouped items appear at the bottom
- Sort order within groups: signal status > magnitude > name
</success_criteria>

<output>
After completion, create `.planning/quick/5-add-profession-grouping-to-dashboard-wit/5-SUMMARY.md`
</output>
