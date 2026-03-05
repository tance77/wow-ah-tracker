# Phase 9: Data Foundation - Context

**Gathered:** 2026-03-04
**Status:** Ready for planning

<domain>
## Phase Boundary

Schema, models, and factories for shuffles and shuffle steps. Creates the `shuffles` and `shuffle_steps` tables, Eloquent models with relationships, and factories for test seeding. Pure infrastructure — no UI, no API endpoints, no business logic beyond model relationships and cascade behavior.

</domain>

<decisions>
## Implementation Decisions

### Auto-watch provenance
- Add nullable `created_by_shuffle_id` FK on `watched_items` table to track which items were auto-watched by shuffles
- Null value means manually added; non-null means auto-watched by that shuffle
- When a shuffle is deleted, remove auto-watched items ONLY if no other shuffle uses them (orphan cleanup)
- Manually-watched items (created_by_shuffle_id = null) are never removed by shuffle deletion, even if a shuffle also references the same item
- Existing thresholds on already-watched items are preserved when auto-watching (firstOrCreate behavior)

### Shuffle schema
- `shuffles` table: id, user_id (FK), name (string), timestamps
- Name only — no description, notes, tags, or active/inactive toggle
- No cached profit column — profitability calculated on the fly from live prices
- No limit on number of shuffles per user

### Shuffle step schema
- `shuffle_steps` table: id, shuffle_id (FK), input_blizzard_item_id, output_blizzard_item_id, output_qty_min (unsigned integer), output_qty_max (unsigned integer), sort_order (unsigned integer), timestamps
- Steps reference items via `blizzard_item_id` (not watched_item FK) — decoupled from watchlist
- Item names resolved via catalog_items relationship, not denormalized on steps
- No limit on steps per chain
- Default yield values for new steps: output_qty_min = 1, output_qty_max = 1 (1:1 fixed ratio)

### Cascade behavior
- Deleting a shuffle cascade-deletes all its steps
- Deleting a shuffle triggers orphan cleanup for auto-watched items (remove if no other shuffle uses them)

### Yield columns (pre-decided)
- Integer columns only: `output_qty_min` and `output_qty_max` — no floats for quantities
- Both columns in Phase 9 migration even though min/max UI ships in Phase 11

### Claude's Discretion
- Migration column ordering and index strategy
- Factory fake data ranges and realistic WoW item IDs
- Test structure and assertion patterns

</decisions>

<specifics>
## Specific Ideas

No specific requirements — open to standard Laravel patterns for models, migrations, and factories.

</specifics>

<code_context>
## Existing Code Insights

### Reusable Assets
- `WatchedItem` model: Has `blizzard_item_id`, `user_id`, relationships to User and CatalogItem — shuffle steps will reference the same `blizzard_item_id` pattern
- `WatchedItemFactory`: Pattern for factory definitions with faker data
- `CatalogItem` model: Stores item metadata (name, icon_url, quality_tier, rarity) keyed by `blizzard_item_id` — steps will join here for display names
- `User` model: Already has `watchedItems()` relationship — needs new `shuffles()` relationship

### Established Patterns
- `declare(strict_types=1)` on all PHP files
- `HasFactory` trait on all models
- `$fillable` arrays for mass assignment
- `$casts` arrays for type casting
- `unsignedBigInteger` for Blizzard item IDs
- `foreignId()->constrained()` for FK relationships
- Pest 3 for testing

### Integration Points
- `watched_items` table gets new nullable `created_by_shuffle_id` FK column (migration)
- `User` model gets new `shuffles()` HasMany relationship
- `Shuffle` model gets `steps()` HasMany ordered by `sort_order`
- `ShuffleStep` model gets `inputCatalogItem()` and `outputCatalogItem()` BelongsTo relationships via blizzard_item_id

</code_context>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 09-data-foundation*
*Context gathered: 2026-03-04*
