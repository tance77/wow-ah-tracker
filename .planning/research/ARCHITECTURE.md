# Architecture Research

**Domain:** WoW AH Tracker — v1.2 Crafting Profitability Milestone
**Researched:** 2026-03-05
**Confidence:** HIGH for data model integration (codebase inspection); MEDIUM for Blizzard API endpoint chain (verified from official forum responses and community libraries, known API gaps documented)

## Context: What Already Exists

This is a subsequent-milestone document. v1.1 is shipped. The full existing architecture is:

- **Models:** `User`, `WatchedItem`, `CatalogItem`, `PriceSnapshot`, `IngestionMetadata`, `Shuffle`, `ShuffleStep`
- **Key relationships:** `User hasMany WatchedItem`, `WatchedItem belongsTo CatalogItem` (via `blizzard_item_id`), `CatalogItem hasMany PriceSnapshot`, `User hasMany Shuffle`, `Shuffle hasMany ShuffleStep`, `ShuffleStep belongsTo CatalogItem` (×2, input + output)
- **Livewire pages (Volt SFCs):** `pages.dashboard`, `pages.watchlist`, `pages.item-detail`, `pages.shuffles`, `pages.shuffle-detail`
- **Routes:** `/dashboard`, `/watchlist`, `/item/{watchedItem}`, `/shuffles`, `/shuffles/{shuffle}`
- **Background jobs:** `FetchCommodityDataJob` (hourly) → `DispatchPriceBatchesJob` → `AggregatePriceBatchJob`
- **Price storage:** BIGINT copper (not float), composite index `(catalog_item_id, polled_at)` on `price_snapshots`
- **Auto-watch system:** `WatchedItem.created_by_shuffle_id` FK — items added to shuffles become watched automatically
- **Catalog seeding:** `blizzard:sync-catalog` artisan command — fetches item details from static namespace; concurrent HTTP/pool batch pattern at 20 items per batch

The research below covers **only what is new or changed** for the v1.2 Crafting Profitability feature.

---

## System Overview: Crafting Integration

```
┌─────────────────────────────────────────────────────────────────────┐
│                     Existing (unchanged)                             │
│  Dashboard    Watchlist    Item Detail    Shuffles    Auth/Profile   │
├─────────────────────────────────────────────────────────────────────┤
│                     NEW: Crafting Section                            │
│  ┌──────────────────────┐   ┌────────────────────────────────────┐   │
│  │  pages.crafting      │   │  pages.crafting-profession         │   │
│  │  (profession index)  │   │  (detail — sortable recipe table)  │   │
│  │  - Cards per         │   │  - All recipes for one profession  │   │
│  │    profession        │   │  - Columns: recipe name, reagent   │   │
│  │  - Top N profitable  │   │    cost, T1 profit, T2 profit,     │   │
│  │    recipes each      │   │    median profit                   │   │
│  └──────────┬───────────┘   │  - Sortable by any column          │   │
│             │               └────────────────┬───────────────────┘   │
│             └────────────────────────────────┘                       │
├─────────────────────────────────────────────────────────────────────┤
│                     NEW Eloquent Models                              │
│  ┌────────────────┐  ┌───────────────────┐  ┌────────────────────┐   │
│  │  Profession    │  │  Recipe           │  │  RecipeReagent     │   │
│  │  id, name,     │  │  id, profession_  │  │  id, recipe_id,    │   │
│  │  blizzard_     │  │  id, blizzard_    │  │  blizzard_item_id, │   │
│  │  profession_id │  │  recipe_id,       │  │  quantity          │   │
│  └────────────────┘  │  crafted_item_id, │  └────────────────────┘   │
│                       │  crafted_qty      │                           │
│                       └───────────────────┘                           │
├─────────────────────────────────────────────────────────────────────┤
│                     Data Layer (new tables)                          │
│  ┌──────────────┐  ┌──────────────────────┐  ┌──────────────────┐   │
│  │  professions │  │  recipes             │  │  recipe_reagents │   │
│  │  + skill     │  │  + FK to professions │  │  + FK to recipes │   │
│  │  tier IDs    │  │  + FK to catalog     │  │  + blizzard      │   │
│  └──────────────┘  └──────────────────────┘  │  item_id + qty   │   │
│                                               └──────────────────┘   │
├─────────────────────────────────────────────────────────────────────┤
│                     Existing (used, not changed)                     │
│  CatalogItem ←── linked via blizzard_item_id (reagents + outputs)   │
│  PriceSnapshot ←── queried live for reagent cost + crafted price    │
│  WatchedItem ←── auto-created for recipe reagents (new path)        │
│  blizzard:sync-catalog command ←── separate from recipe seeding     │
└─────────────────────────────────────────────────────────────────────┘
```

---

## Blizzard API Endpoint Chain for Recipe Data

This is the core integration challenge. Recipe data comes from the **static namespace** (not dynamic like commodities). It does not update frequently — expansion launch data is stable within a patch cycle.

### Full Call Chain

```
Step 1: Profession Index
  GET https://us.api.blizzard.com/data/wow/profession/index
      ?namespace=static-us&locale=en_US
  → Returns: array of {key, name, id} for all professions
  → Source of truth for blizzard_profession_id values

Step 2: Profession Detail (per profession)
  GET https://us.api.blizzard.com/data/wow/profession/{professionId}
      ?namespace=static-us&locale=en_US
  → Returns: profession details + skill_tiers[] array
  → Each skill_tier: {key.href, name ("Midnight Crafting"), id}
  → Filter skill_tiers to find the Midnight expansion tier by name match

Step 3: Skill Tier Detail (per profession, per expansion tier)
  GET https://us.api.blizzard.com/data/wow/profession/{professionId}/skill-tier/{skillTierId}
      ?namespace=static-us&locale=en_US
  → Returns: categories[] → each category has recipes[] array
  → Each recipe entry: {key.href, name, id}
  → This gives the complete list of recipe IDs for that profession + tier

Step 4: Recipe Detail (per recipe — bulk fetched via Http::pool)
  GET https://us.api.blizzard.com/data/wow/recipe/{recipeId}
      ?namespace=static-us&locale=en_US
  → Returns: id, name, crafted_item{id, name}, reagents[]{reagent{id,name}, quantity},
             crafted_quantity{value}, rank (nullable)
  → Reagents list is the primary data needed for cost calculation
```

### Known API Gaps and Mitigations

**Gap 1: `crafted_item` field absent for some expansion recipes**
- Dragonflight-era reports indicate some recipes lack `crafted_item` in the API response
- Mitigation: Store `crafted_blizzard_item_id` as nullable; skip profit calculation for recipes without it; flag them in the UI as "price unavailable"

**Gap 2: Reagents from NPC vendors missing from response**
- Some required reagents sold by NPCs are absent from the `reagents[]` array
- Mitigation: Calculate cost from AH reagents only; annotate recipes where cost is incomplete; this is acceptable for AH-focused profitability analysis

**Gap 3: `modified_crafting_slots` for optional quality reagents**
- Modern crafting uses optional reagents that affect output quality tier
- For v1.2 scope (two quality tiers, simple model), ignore optional reagents; use base `reagents[]` only

**Gap 4: Midnight expansion may use new skill tier naming**
- Skill tier name format changes each expansion (e.g., "Dragonflight Crafting", "The War Within Crafting")
- Mitigation: Identify Midnight tier by matching against known name patterns OR select the highest-ID skill tier per profession (most recent expansion tier always has the largest ID)

### Namespace Clarification

All recipe/profession endpoints use `static-{region}` namespace (e.g., `static-us`). This is the same namespace used by `SyncCatalogCommand` for item detail lookups — the existing `BlizzardTokenService` and `Http::pool` pattern works unchanged.

---

## New Models Required

### Profession

Represents a single crafting profession (Alchemy, Blacksmithing, etc.) with the IDs needed to navigate the Blizzard API.

```php
// app/Models/Profession.php
class Profession extends Model
{
    protected $fillable = [
        'name',                    // "Alchemy"
        'blizzard_profession_id',  // from profession/index
        'blizzard_skill_tier_id',  // from profession/{id} skill_tiers — Midnight tier
        'icon_url',                // optional, from profession media endpoint
    ];

    public function recipes(): HasMany
    {
        return $this->hasMany(Recipe::class);
    }
}
```

**Migration schema:**
```php
Schema::create('professions', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->unsignedBigInteger('blizzard_profession_id')->unique();
    $table->unsignedBigInteger('blizzard_skill_tier_id')->nullable(); // null until seeded
    $table->string('icon_url')->nullable();
    $table->timestamps();
});
```

### Recipe

Represents one craftable recipe within a profession. References the CatalogItem it produces and the profession it belongs to.

```php
// app/Models/Recipe.php
class Recipe extends Model
{
    protected $fillable = [
        'profession_id',           // FK to professions table
        'blizzard_recipe_id',      // from recipe endpoint
        'name',                    // recipe name (e.g., "Craft: Void-Touched Cloth")
        'crafted_blizzard_item_id', // item produced — nullable if API gap
        'crafted_quantity',        // how many produced per craft
    ];

    protected $casts = [
        'blizzard_recipe_id'       => 'integer',
        'crafted_blizzard_item_id' => 'integer',
        'crafted_quantity'         => 'integer',
    ];

    public function profession(): BelongsTo
    {
        return $this->belongsTo(Profession::class);
    }

    public function reagents(): HasMany
    {
        return $this->hasMany(RecipeReagent::class);
    }

    public function craftedCatalogItem(): BelongsTo
    {
        // Join to CatalogItem via blizzard_item_id (same pattern as WatchedItem/ShuffleStep)
        return $this->belongsTo(CatalogItem::class, 'crafted_blizzard_item_id', 'blizzard_item_id');
    }
}
```

**Migration schema:**
```php
Schema::create('recipes', function (Blueprint $table) {
    $table->id();
    $table->foreignId('profession_id')->constrained()->cascadeOnDelete();
    $table->unsignedBigInteger('blizzard_recipe_id')->unique();
    $table->string('name');
    $table->unsignedBigInteger('crafted_blizzard_item_id')->nullable();
    $table->unsignedInteger('crafted_quantity')->default(1);
    $table->timestamps();

    $table->index('crafted_blizzard_item_id');
});
```

### RecipeReagent

Pivot-like table storing each ingredient required by a recipe. Uses `blizzard_item_id` to link to `CatalogItem` (same FK convention as `WatchedItem` and `ShuffleStep`).

```php
// app/Models/RecipeReagent.php
class RecipeReagent extends Model
{
    protected $fillable = [
        'recipe_id',
        'blizzard_item_id',  // reagent's item ID — joins to catalog_items
        'quantity',
    ];

    protected $casts = [
        'blizzard_item_id' => 'integer',
        'quantity'         => 'integer',
    ];

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    public function catalogItem(): BelongsTo
    {
        // Same convention: FK on blizzard_item_id, references blizzard_item_id on catalog_items
        return $this->belongsTo(CatalogItem::class, 'blizzard_item_id', 'blizzard_item_id');
    }
}
```

**Migration schema:**
```php
Schema::create('recipe_reagents', function (Blueprint $table) {
    $table->id();
    $table->foreignId('recipe_id')->constrained()->cascadeOnDelete();
    $table->unsignedBigInteger('blizzard_item_id');
    $table->unsignedInteger('quantity');
    $table->timestamps();

    $table->unique(['recipe_id', 'blizzard_item_id']);
    $table->index('blizzard_item_id'); // for reagent → catalog joins
});
```

---

## Integration with Existing Models

### CatalogItem (unchanged schema — extended usage)

`CatalogItem` is the bridge between recipe data and price data. It is referenced by:

1. `Recipe.crafted_blizzard_item_id` → the item being sold
2. `RecipeReagent.blizzard_item_id` → each ingredient being purchased

No schema changes to `CatalogItem`. The existing `blizzard_item_id` unique constraint is the join key. The existing `priceSnapshots()` relationship provides live median prices.

**Key constraint:** Recipe data can only show live profit if the crafted item AND all reagents exist in `catalog_items` with recent `price_snapshots`. The seeding command must ensure both sides are present.

### PriceSnapshot (unchanged — queried live)

Profit is never stored. It is calculated at render time:

```
reagent_cost_copper = sum(reagent.quantity * catalogItem.latestMedianPrice for each reagent)
crafted_value_copper = craftedItem.latestMedianPrice * crafted_quantity * 0.95  (5% AH cut)
profit_copper = crafted_value_copper - reagent_cost_copper
```

The `0.95` AH cut is applied to the sell side only — this matches the existing `Shuffle::profitPerUnit()` calculation pattern.

### WatchedItem (auto-watch for reagents)

When recipes are seeded, all reagent `blizzard_item_id` values must be added as `WatchedItem` rows so the 15-minute price polling includes them. This is the same auto-watch pattern used by Shuffles.

```
Recipe seeding (artisan command)
    ↓
For each RecipeReagent:
    WatchedItem::firstOrCreate(
        ['blizzard_item_id' => $reagent->blizzard_item_id],
        ['name' => $catalogItem->name, 'buy_threshold' => null, 'sell_threshold' => null,
         'created_by_shuffle_id' => null]
    )
For each Recipe:
    WatchedItem::firstOrCreate(
        ['blizzard_item_id' => $recipe->crafted_blizzard_item_id],
        [...same...]
    )
```

The crafted items also need watching — we need their sell price too.

**Note:** `created_by_shuffle_id` stays null for recipe-auto-watched items. Consider adding a `created_by_recipe_id` FK for proper orphan cleanup, or accept that recipe-seeded watch items persist indefinitely (acceptable for a personal tool).

---

## Recipe Data Fetch Strategy: Seed vs Periodic Refresh

### Decision: One-Time Seed with Manual Re-Run

Recipe data belongs to the **static namespace** — Blizzard only updates it when patches change profession recipes. This is categorically different from commodity prices (dynamic, changes every 15 minutes).

**Recommended approach:**

```
blizzard:seed-recipes artisan command (runs once at setup, re-run after patches)
    ↓
Step 1: Fetch profession index → upsert professions table
Step 2: For each profession, fetch detail → find Midnight skill tier → store blizzard_skill_tier_id
Step 3: For each profession's skill tier, fetch recipe list → collect all recipe IDs
Step 4: For each recipe ID (Http::pool batches of 20, matching SyncCatalogCommand pattern):
    a. Fetch recipe detail → upsert recipes table
    b. Upsert recipe_reagents rows
    c. Ensure all item IDs exist in catalog_items (via SyncCatalogCommand style lookups)
    d. Ensure all item IDs exist as WatchedItem rows (auto-watch)
```

**Why not periodic refresh:**
- Static namespace data changes only with game patches (weeks/months, not minutes)
- Scheduling this hourly or daily wastes API calls and adds complexity
- A manual artisan command gives explicit control: run it when a new patch drops

**Why not database seeder:**
- Recipe data requires live Blizzard API calls — unsuitable for `db:seed`
- Artisan command provides progress output, retry logic, and `--dry-run` flags
- Mirrors the existing `blizzard:sync-catalog` command design exactly

**Re-run safety:** All upserts use `updateOrCreate` on `blizzard_recipe_id`. Re-running the command after a patch is safe — new recipes are added, unchanged recipes are touched but not duplicated.

---

## New Livewire Components

### `pages.crafting` (profession overview)

Volt SFC at `resources/views/livewire/pages/crafting.blade.php`.

**Responsibilities:**
- Display one card per profession
- Each card shows the top 3-5 most profitable recipes (by median profit across T1/T2)
- Quick-access link to the full profession detail page

**Data loading:** Eager-load recipes with their reagents and the latest price snapshots for all related items. Profit calculation happens in PHP, not in the view. Consider a `CraftingProfitService` if the calculation logic is complex enough to reuse across both pages.

### `pages.crafting-profession` (profession detail)

Volt SFC at `resources/views/livewire/pages/crafting-profession.blade.php`.

**Responsibilities:**
- Full sortable recipe table for one profession
- Columns: recipe name, reagent cost (copper formatted), T1 profit, T2 profit, median profit
- Sort state managed as Livewire public properties (`$sortBy`, `$sortDir`)
- Rows with missing price data shown with "—" placeholder

**Route:** `GET /crafting/{profession}` with model binding resolving `Profession` by slug or ID.

---

## Profit Calculation Data Flow

```
User loads /crafting/{profession}
    ↓
Volt component mounts, loads Profession with eager-loaded chain:
    profession → recipes → reagents → reagent.catalogItem.latestPriceSnapshot
                        → craftedCatalogItem.latestPriceSnapshot
    ↓
For each Recipe:
    reagent_cost = sum(reagent.quantity * reagent.catalogItem.latestSnapshot.median_price)
    crafted_value = craftedCatalogItem.latestSnapshot.median_price * crafted_quantity * 0.95
    profit = crafted_value - reagent_cost  (integer copper throughout)
    ↓
Two quality tiers (T1 / T2) = two separate Recipe rows per crafted item name
    (CatalogItem.quality_tier already handles this via the existing tier assignment system)
    Median profit = (T1_profit + T2_profit) / 2 (integer division is fine)
    ↓
Sort recipes by selected column in PHP (collection sort, not DB ORDER BY)
    ↓
Display: formatGold() converts copper to g/s/c at render time only
```

**Where the calculation lives:** A dedicated `App\Actions\RecipeProfitAction.php` — takes a `Recipe` model with eager-loaded relationships, returns `['reagent_cost' => int, 'crafted_value' => int, 'profit' => int]`. This mirrors the existing `PriceAggregateAction` pattern and makes the calculation independently testable.

---

## New File Inventory

### New Files

| Path | Type | Purpose |
|------|------|---------|
| `app/Models/Profession.php` | Model | Crafting profession with API IDs |
| `app/Models/Recipe.php` | Model | One craftable recipe |
| `app/Models/RecipeReagent.php` | Model | One ingredient in a recipe |
| `database/migrations/..._create_professions_table.php` | Migration | `professions` table |
| `database/migrations/..._create_recipes_table.php` | Migration | `recipes` table |
| `database/migrations/..._create_recipe_reagents_table.php` | Migration | `recipe_reagents` table |
| `app/Console/Commands/SeedRecipesCommand.php` | Artisan command | Fetches + stores all Midnight recipes |
| `app/Actions/RecipeProfitAction.php` | Action | Calculates per-recipe profit |
| `resources/views/livewire/pages/crafting.blade.php` | Volt SFC | Profession overview |
| `resources/views/livewire/pages/crafting-profession.blade.php` | Volt SFC | Per-profession recipe table |
| `database/factories/ProfessionFactory.php` | Factory | Test seeding |
| `database/factories/RecipeFactory.php` | Factory | Test seeding |
| `database/factories/RecipeReagentFactory.php` | Factory | Test seeding |

### Modified Files

| Path | Change |
|------|--------|
| `resources/views/livewire/layout/navigation.blade.php` | Add "Crafting" nav link |
| `routes/web.php` | Add two new Volt routes for crafting pages |

---

## Build Order

Dependencies determine this order. Each step unblocks the next.

```
1. Migrations + Models
   professions → recipes → recipe_reagents
   Profession → Recipe → RecipeReagent models
   (No existing model changes required)

2. Factories
   ProfessionFactory → RecipeFactory → RecipeReagentFactory
   (Needed before feature tests can run)

3. SeedRecipesCommand (artisan command)
   This is the highest-risk step — validates the full Blizzard API call chain.
   Build and test this BEFORE building any UI, so recipe data is in DB for development.
   Steps: profession index → skill tier detection → recipe list → recipe detail (Http::pool)
   Include: --dry-run flag, progress bar, resume capability (same as SyncCatalogCommand)

4. RecipeProfitAction
   Pure calculation logic, no HTTP, easily testable with factories.
   Build and unit-test before integrating into Livewire.

5. Crafting Overview Page (pages.crafting)
   Profession cards with top recipes. Validates: route, data loading, profit display.

6. Navigation Link
   Add "Crafting" to navigation.blade.php — active on /crafting/*.

7. Profession Detail Page (pages.crafting-profession)
   Full recipe table with sort. Validates: sorting, all columns, missing-price handling.

8. Auto-Watch Verification
   Confirm seeded reagent items appear in watchlist and receive price snapshots.
```

**Critical path:** Step 3 (SeedRecipesCommand) must succeed before step 4 or 5. Without real recipe data in the database, all downstream UI work uses factories that may not reflect actual Midnight recipe complexity (multi-reagent, quality tiers, etc.).

---

## Relationship Map

```
Profession
 └── hasMany Recipe
          ├── belongsTo Profession
          ├── hasMany RecipeReagent
          │        ├── belongsTo Recipe
          │        └── belongsTo CatalogItem (via blizzard_item_id) ← price lookup
          └── belongsTo CatalogItem (as craftedCatalogItem, via crafted_blizzard_item_id) ← price lookup

CatalogItem (existing — unchanged)
 └── hasMany PriceSnapshot ← queried for both reagent cost and crafted item sell price

WatchedItem (existing — gains recipe-seeded entries)
 └── auto-created for all reagents + crafted items during seed command
```

---

## Anti-Patterns to Avoid

### Anti-Pattern 1: Storing Calculated Profit in the Database

**What people do:** Add `t1_profit_copper`, `t2_profit_copper` columns to the `recipes` table and update them on a schedule.

**Why it's wrong:** Profit depends on live `median_price` from `PriceSnapshot`, which updates every 15 minutes. Cached profit values go stale immediately. The recalculation schedule must be coordinated with the price polling schedule, creating unnecessary coupling.

**Do this instead:** Calculate profit at render time by joining to the latest price snapshots. The query is fast because `(catalog_item_id, polled_at)` is already indexed. Never persist derived values that have live dependencies.

### Anti-Pattern 2: Storing Recipe Data in CatalogItem

**What people do:** Add recipe-related columns (`is_craftable`, `recipe_id`, `reagent_ids`) to the existing `catalog_items` table to avoid new tables.

**Why it's wrong:** `CatalogItem` represents market-traded items (anything that appears in the auction house commodities feed). Recipes are a separate concept — not all catalog items are craftable outputs, and recipes have multiple reagents that cannot be represented as columns. The data shapes are different.

**Do this instead:** Create separate `professions`, `recipes`, and `recipe_reagents` tables. Link them to `catalog_items` via `blizzard_item_id` for price lookups, but keep the schemas distinct.

### Anti-Pattern 3: Per-Recipe API Polling on a Schedule

**What people do:** Add recipe data to the 15-minute job to keep it "fresh."

**Why it's wrong:** Recipe data lives in Blizzard's static namespace — it only changes with game patches. Polling it every 15 minutes (or even daily) wastes API quota, adds job complexity, and serves no benefit since the data doesn't change.

**Do this instead:** Seed recipe data once via `blizzard:seed-recipes` artisan command. Re-run only after patches. The command should be idempotent (upsert, not insert) so re-running is safe.

### Anti-Pattern 4: Using profession Name Matching to Find the Midnight Skill Tier

**What people do:** Filter `skill_tiers[]` by name == "Midnight Crafting" to find the right tier.

**Why it's wrong:** Blizzard changes the name format each expansion. "Midnight Crafting" may not be the exact string returned — it could be "Midnight" or include localizations.

**Do this instead:** Select the skill tier with the highest `id` value. Each expansion's crafting tier gets a new, higher ID. The current expansion's tier always has the max ID. This is robust to name changes and localizations.

---

## Integration Points Summary

| Touch Point | New or Modified | Notes |
|-------------|-----------------|-------|
| `professions` table | NEW | Seeded once from API |
| `recipes` table | NEW | One row per recipe; FK to professions |
| `recipe_reagents` table | NEW | One row per reagent per recipe |
| `Profession` model | NEW | Simple — relationships only |
| `Recipe` model | NEW | Bridge to CatalogItem for prices |
| `RecipeReagent` model | NEW | Bridge to CatalogItem for reagent prices |
| `SeedRecipesCommand` | NEW | Artisan command for recipe seeding |
| `RecipeProfitAction` | NEW | Calculation logic, independently testable |
| `pages.crafting` Volt SFC | NEW | Profession overview |
| `pages.crafting-profession` Volt SFC | NEW | Per-profession recipe table |
| Navigation blade | MODIFIED | Add "Crafting" nav link |
| `routes/web.php` | MODIFIED | Add 2 new Volt routes |
| `CatalogItem` | UNCHANGED schema | Joined via `blizzard_item_id` for price data |
| `PriceSnapshot` | UNCHANGED | Queried live for all profit calculations |
| `WatchedItem` | UNCHANGED schema, new rows | Recipe seed command creates auto-watch entries |
| `BlizzardTokenService` | UNCHANGED | Reused by SeedRecipesCommand as-is |
| Background jobs (price polling) | UNCHANGED | Auto-watched reagents flow through existing pipeline |
| `blizzard:sync-catalog` | UNCHANGED | May need to run first to ensure reagent CatalogItem rows exist |

---

## Sources

- Codebase inspection (direct, HIGH confidence): `/app/Models/`, `/app/Actions/`, `/app/Console/Commands/SyncCatalogCommand.php`, `/app/Services/BlizzardTokenService.php`, `/database/migrations/`
- Blizzard API profession endpoint chain (MEDIUM confidence): Confirmed via Ruby `blizzard_api` gem documentation (rubydoc.info, June 2025), Blizzard community forums (profession-recipe-api-incorrect-data thread), blizzard.js wiki
- Recipe API response structure (MEDIUM confidence): Confirmed from Blizzard forum post (profession-recipe-api-incorrect-data, 2020/ongoing) — `id`, `name`, `crafted_item`, `reagents[]{reagent{id,name}, quantity}`, `crafted_quantity{value}`
- Known API gaps (MEDIUM confidence): Forum discussions confirm `crafted_item` sometimes absent for expansion recipes; NPC vendor reagents missing from `reagents[]` array
- Midnight expansion quality tiers (HIGH confidence): Wowhead news article (2025) confirms Midnight reduces consumable quality to 2 tiers (down from 3 in TWW)
- Skill tier ID selection strategy (MEDIUM confidence): Inferred from API structure and known per-expansion tier pattern; highest ID = current expansion is standard community practice
- Static vs dynamic namespace distinction (HIGH confidence): Matches existing `SyncCatalogCommand` which uses `static-{region}` for item lookups — same namespace required for recipe endpoints

---

*Architecture research for: WoW AH Tracker v1.2 Crafting Profitability integration*
*Researched: 2026-03-05*
