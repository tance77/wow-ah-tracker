# Phase 13: Recipe Data Model and Seed Command - Context

**Gathered:** 2026-03-05
**Status:** Ready for planning

<domain>
## Phase Boundary

Seed all Midnight expansion recipes from the Blizzard API into local database tables (professions, recipes, recipe_reagents) and auto-watch all reagents and crafted items for price polling. This phase delivers the data foundation — profit calculation, UI pages, and sorting are separate phases (14-16).

</domain>

<decisions>
## Implementation Decisions

### Profession scope
- Crafting professions only: Alchemy, Blacksmithing, Enchanting, Engineering, Inscription, Jewelcrafting, Leatherworking, Tailoring
- Cooking included if it has craftable commodity outputs (Claude verifies via API)
- Gathering professions excluded (Herbalism, Mining, Skinning) — no crafting recipes with reagent costs
- Use highest-ID skill tier per profession to identify Midnight expansion recipes (pre-decision from research)
- Store profession icon_url in the professions table during sync — saves Phase 15 from needing its own API calls

### Missing data strategy
- Recipes with missing `crafted_item` from API: store with NULL crafted_item_id, increment gap counter
- Missing `crafted_quantity`: default to 1 (covers 90%+ of recipes correctly)
- Gap gate: warn prominently but continue (no hard failure). Log gap percentage per profession
- Resolve quality tiers (T1/T2) during sync using existing `assignQualityTiers()` pattern — match crafted items by name to find CatalogItem pairs, store both IDs on recipe row

### Auto-watch behavior
- Auto-watched items owned by user #1 (single-user personal tool)
- Tag auto-watched items with the profession the recipe belongs to (leverages existing dashboard profession grouping)
- Shared reagents (used by multiple professions): first profession encountered sets the tag (firstOrCreate behavior)
- Buy/sell thresholds left NULL — consistent with shuffle auto-watch behavior (Phase 11)

### Command output style
- Progress bar matching SyncCatalogCommand pattern (bar with current/total, percentage, current recipe name)
- `--report-gaps` outputs per-profession table: profession name, total recipes, missing crafted_item count, missing quantity count, coverage %
- `--dry-run` and `--report-gaps` are combinable — full API traversal with gap report and zero DB writes
- Final summary: "Synced X recipes (Y professions). Auto-watched Z items (N new, M already existed). Gaps: G recipes missing crafted_item."

### Claude's Discretion
- Exact migration column types and index strategy
- API traversal order (alphabetical by profession, or parallel)
- Batch size tuning for Http::pool() calls
- Error retry strategy for individual recipe fetches
- Logging verbosity levels

</decisions>

<specifics>
## Specific Ideas

No specific requirements — open to standard approaches matching existing SyncCatalogCommand patterns.

</specifics>

<code_context>
## Existing Code Insights

### Reusable Assets
- `SyncCatalogCommand`: Established pattern for Blizzard API batch fetching with Http::pool(), progress bars, rate limit handling, --dry-run, --fresh flags, and streaming large responses
- `BlizzardTokenService`: OAuth2 token caching, ready to use
- `CatalogItem` model: Existing item catalog with blizzard_item_id, name, category, rarity, icon_url, quality_tier — recipes will reference these
- `WatchedItem` model: Has profession field, created_by_shuffle_id pattern for provenance tracking, firstOrCreate dedup pattern from Phase 11
- `assignQualityTiers()` method on SyncCatalogCommand: Groups items by name, assigns T1/T2 by blizzard_item_id order — reusable for crafted item tier resolution

### Established Patterns
- BIGINT UNSIGNED for copper prices (no floats)
- `updateOrCreate` for idempotent upserts
- Http::pool() with 20-item batches and 1s pause between batches
- Progress bar format: `%current%/%max% [%bar%] %percent:3s%% — %message%`
- `--dry-run` flag skips DB writes but runs full logic

### Integration Points
- New `professions` table referenced by `recipes` table
- `recipes` table references `catalog_items` via crafted_item_id columns
- `recipe_reagents` table references both `recipes` and `catalog_items`
- Auto-watch creates `watched_items` rows linked to user #1
- Price poller (existing 15-min schedule) picks up new watched items automatically

</code_context>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 13-recipe-data-model-and-seed-command*
*Context gathered: 2026-03-05*
