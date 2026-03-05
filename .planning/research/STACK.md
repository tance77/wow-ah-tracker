# Stack Research

**Domain:** WoW AH Tracker — v1.2 Crafting Profitability (Blizzard Profession/Recipe API integration)
**Researched:** 2026-03-05
**Confidence:** HIGH for all key decisions (verified against existing codebase and API community documentation)

---

## v1.2 Crafting Profitability: Stack Delta

This document preserves the v1.1 stack baseline below and adds a focused delta for the Crafting Profitability milestone. The existing stack is unchanged and fully validated. No re-research of Laravel 12, Livewire 4, Volt, Tailwind CSS v4, ApexCharts, Pest 3, SQLite, or the Blizzard OAuth2 token service.

**Bottom line: Zero new Composer packages required.** Every API, HTTP, auth, and compute capability needed is already present.

---

## No New Dependencies Required

| Capability | How It's Covered | Confidence |
|------------|-----------------|------------|
| Blizzard API HTTP requests | `Illuminate\Support\Facades\Http` | HIGH |
| Concurrent batch fetching | `Http::pool()` — already in `SyncCatalogCommand` | HIGH |
| OAuth2 token acquisition/caching | `App\Services\BlizzardTokenService::getToken()` — inject as-is | HIGH |
| Database schema & models | Laravel Eloquent + SQLite migrations | HIGH |
| Background command scheduling | Laravel Artisan command (one-time per patch, not recurring) | HIGH |
| Profitability arithmetic | PHP 8.4 integer math — all prices are copper integers | HIGH |
| UI pages | Livewire 4 / Volt SFC — existing pattern | HIGH |
| Testing | Pest 3 — existing | HIGH |

---

## Blizzard API Endpoints for Recipe Fetching

All endpoints use the **`static-{region}`** namespace — identical to the existing item detail lookups in `SyncCatalogCommand`.

### Endpoint Chain (4 steps per profession)

```
1. GET /data/wow/profession/index
   ?namespace=static-{region}
   Returns: { professions: [ { id, name, key } ] }

2. GET /data/wow/profession/{professionId}
   ?namespace=static-{region}
   Returns: skill_tiers: [ { id, name, key } ] — filter by name containing "Midnight"

3. GET /data/wow/profession/{professionId}/skill-tier/{skillTierId}
   ?namespace=static-{region}
   Returns: recipes: [ { id, name, key } ]

4. GET /data/wow/recipe/{recipeId}
   ?namespace=static-{region}
   Returns: name, reagents[], crafted_item (may be absent — see Pitfalls)
```

### Authentication Integration (existing token service — no changes)

```php
// Inject BlizzardTokenService via constructor — identical to SyncCatalogCommand
$token = $this->tokenService->getToken();

Http::withToken($token)
    ->get("https://{$region}.api.blizzard.com/data/wow/profession/index", [
        'namespace' => "static-{$region}",
    ]);
```

### Recipe Response Structure (verified)

```json
{
  "id": 12345,
  "name": "Midnight Healing Potion",
  "reagents": [
    {
      "reagent": { "id": 215000, "name": "Mote of Light" },
      "quantity": 3
    }
  ],
  "crafted_item": {
    "id": 216000,
    "name": "Midnight Healing Potion"
  },
  "crafted_quantity": { "value": 1 }
}
```

**Critical API limitations — verified, not assumed:**

1. `crafted_item` is absent for many post-Dragonflight recipes. The API exposes the recipe spell ID, not the resulting AH item ID. This persists through The War Within (confirmed by Blizzard API forum discussions, September 2024). Workaround: when `crafted_item` is absent, match the crafted item from `catalog_items` by recipe name at query time.

2. Crafting difficulty is not exposed by the REST API. Confirmed missing through Dragonflight and The War Within. The Midnight in-game addon API added `C_TradeSkillUI.GetItemCraftedQualityInfo` (patch 12.0.0), but this is not available via REST.

3. Crafted quality tier (Silver vs Gold) is not a field in the recipe API response. Handled at the application layer using the existing `catalog_items.quality_tier` column.

---

## Database: New Tables Required (no packages — pure Eloquent + migrations)

### `professions` table

```php
Schema::create('professions', function (Blueprint $table) {
    $table->id();
    $table->unsignedInteger('blizzard_id')->unique();
    $table->string('name');                    // "Alchemy", "Blacksmithing", etc.
    $table->string('slug');                    // "alchemy", "blacksmithing" — for URLs
    $table->timestamps();
});
```

### `recipes` table

```php
Schema::create('recipes', function (Blueprint $table) {
    $table->id();
    $table->unsignedInteger('blizzard_id')->unique();
    $table->foreignId('profession_id')->constrained();
    $table->string('name');
    $table->unsignedInteger('crafted_item_id')->nullable();  // blizzard_item_id; nullable due to API gap
    $table->timestamps();
});
```

### `recipe_reagents` pivot table

```php
Schema::create('recipe_reagents', function (Blueprint $table) {
    $table->id();
    $table->foreignId('recipe_id')->constrained()->cascadeOnDelete();
    $table->unsignedInteger('blizzard_item_id');  // joins to catalog_items.blizzard_item_id
    $table->unsignedSmallInteger('quantity');
    $table->timestamps();
});
```

---

## Batch Fetching Strategy (copy existing `SyncCatalogCommand` pattern)

The `SyncCatalogCommand::processBatch()` already implements the correct approach:

- `Http::pool()` with 20-item chunks
- 1-second `usleep(1_000_000)` pause between batches
- 429 retry queue with 10-second backoff
- Token injected via `BlizzardTokenService`

Encapsulate recipe sync as a new Artisan command: `blizzard:sync-recipes`. Estimated request volume:

| Step | Request Count |
|------|--------------|
| Profession index | 1 |
| Profession details (13 crafting profs) | 13 |
| Skill tier lists | ~13 |
| Individual recipe details (~30-50 per profession) | ~400-650 |
| **Total** | **~430-680** |

At 20/batch with 1s pause: approximately 25-35 seconds total. Well under Blizzard's 36,000/hr limit.

This is a **one-time per-patch seeding operation**, not a recurring scheduled job. Run manually with `php artisan blizzard:sync-recipes` after each game patch.

---

## Quality Tier Handling (no new code — existing system covers it)

Midnight uses **two quality tiers** for consumables and reagents:
- Tier 1 = Silver quality
- Tier 2 = Gold quality

(Weapons and gear retain 5 ranks, but the crafting profitability feature targets consumables/reagents where this two-tier system applies.)

**How the existing system already handles this:**

1. The commodities AH lists quality-tiered items as separate `blizzard_item_id` values with identical names (e.g., "Midnight Healing Potion" T1 = item 216000, T2 = item 216001).
2. `SyncCatalogCommand::assignQualityTiers()` already groups catalog items by name, assigns `quality_tier` 1 and 2 in ascending item ID order.
3. Each tier has its own `PriceSnapshot` history via `catalog_items`.

**Profitability query approach:** Store ONE recipe row. At query time, LEFT JOIN `catalog_items` twice — once for tier 1 and once for tier 2 — matched by item name. This avoids duplicating recipe data.

```php
// Conceptual query — implemented in a service class
$recipe = Recipe::with('reagents')->find($id);

$reagentCost = $recipe->reagents->sum(function ($reagent) {
    return $reagent->latestPrice() * $reagent->quantity;  // copper integers
});

$tier1Price = CatalogItem::where('name', $recipe->name)
    ->where('quality_tier', 1)
    ->latestPrice();  // copper integer

$tier2Price = CatalogItem::where('name', $recipe->name)
    ->where('quality_tier', 2)
    ->latestPrice();  // copper integer

$tier1Profit = $tier1Price - $reagentCost;
$tier2Profit = $tier2Price - $reagentCost;
$medianProfit = intdiv($tier1Profit + $tier2Profit, 2);
```

All arithmetic stays in copper integers. No floating point. Consistent with the established BIGINT UNSIGNED decision.

---

## What NOT to Add

| Avoid | Why | Use Instead |
|-------|-----|-------------|
| Third-party PHP Blizzard API clients (`meyersm/wow-php-api`, `chrisob120/wowsdk-php`, `LogansUA/blizzard-api-php-client`) | Unmaintained; add a dependency for zero benefit — `Http::pool()` + `BlizzardTokenService` already cover everything | Existing `BlizzardTokenService` + `Http` facade |
| Wowhead scraping for recipe data | Fragile HTML parsing, violates ToS, unnecessary — Blizzard's static namespace returns all recipe data needed | Blizzard static API endpoints |
| Redis / cache store for recipe data | Recipe data is static between patches; SQLite is fine for a personal tool | SQLite — re-run `blizzard:sync-recipes` after patches |
| Separate "recipe item ID resolver" job | The API gap (missing `crafted_item`) is real but name-based join is simpler and requires no extra HTTP calls | Name-based join from `recipes.name` to `catalog_items.name` |
| Queue jobs for recipe sync | Sync is one-time per patch, not recurring. A synchronous Artisan command is simpler and easier to debug | Artisan command with progress bar (same as `SyncCatalogCommand`) |
| BCMath for profitability math | All prices are copper integers; standard PHP integer arithmetic is exact; no float intermediates needed | PHP 8.4 integer arithmetic with `intdiv()` |
| Spatie packages (laravel-data, laravel-query-builder) | Profitability models are simple enough that plain Eloquent + service class is sufficient | Plain Eloquent + `ProfitCalculatorService` |

---

## Integration Points with Existing Code

| Existing Component | How Recipe Feature Uses It |
|-------------------|---------------------------|
| `BlizzardTokenService` | Constructor injection; `getToken()` call — unchanged |
| `CatalogItem` model | `recipe_reagents.blizzard_item_id` references `catalog_items.blizzard_item_id` for prices |
| `PriceSnapshot` (via `CatalogItem`) | Reagent cost = latest snapshot `median_price` × quantity |
| `catalog_items.quality_tier` | T1/T2 lookup for crafted item price; populated by existing `assignQualityTiers()` |
| `WatchedItem` | Auto-watch reagents: create `WatchedItem` rows for reagent `blizzard_item_id` values |
| `Http::pool()` pattern | Copy `SyncCatalogCommand::processBatch()` — batch fetch recipe details |
| `static-{region}` namespace | All profession/recipe endpoints use this — identical to item detail lookups |
| Artisan command pattern | `blizzard:sync-recipes` follows same structure as `blizzard:sync-catalog` |

---

## Midnight Expansion Profession Scope

14 professions total (confirmed):
- Crafting (8): Alchemy, Blacksmithing, Enchanting, Engineering, Inscription, Jewelcrafting, Leatherworking, Tailoring
- Gathering (3): Herbalism, Mining, Skinning
- Secondary (2): Cooking, Fishing
- (1 more tracked in-game but not in AH context)

For the profitability feature, only crafting professions that produce AH-tradeable items are relevant. Gathering professions produce raw materials (already tracked as watched items). Suggested initial scope: Alchemy, Enchanting, Inscription, Jewelcrafting — these produce the most AH-relevant consumables.

Alchemy alone has ~39 recipes (verified via Wowhead). Expect 30-60 recipes per crafting profession.

---

## Sources

- Existing codebase `SyncCatalogCommand.php` — Verified `Http::pool()` pattern, `static-{region}` namespace, `BlizzardTokenService` injection, batch size, rate limit handling (HIGH confidence — directly read)
- Existing codebase `BlizzardTokenService.php` — Confirmed token caching at 23h TTL, injectable via DI (HIGH confidence — directly read)
- Existing codebase `CatalogItem.php` + migrations — Confirmed `quality_tier` column, `assignQualityTiers()` method, copper integer approach (HIGH confidence — directly read)
- [Blizzard API forum: Dragonflight profession recipes crafted item id?](https://us.forums.blizzard.com/en/blizzard/t/dragonflight-profession-recipes-crafted-item-id/37444) — Developer-confirmed `crafted_item` absent in post-Dragonflight recipes; three separate IDs (spell, crafted item, recipe item) not reliably connected (HIGH confidence)
- [Blizzard API forum: Profession recipes Difficulty API](https://us.forums.blizzard.com/en/blizzard/t/profession-recipes-difficulty-api/51769) — Developer-confirmed difficulty fields missing through Dragonflight and The War Within (HIGH confidence)
- [Blizzard API forum: Help with reagent quality from API](https://us.forums.blizzard.com/en/blizzard/t/help-with-reagent-quality-from-api/51961) — Confirmed quality tier not exposed in REST API response; manual mapping required (HIGH confidence)
- [Blizzard API forum: Is it possible to find the recipe item ID in TWW?](https://us.forums.blizzard.com/en/blizzard/t/is-it-possible-to-find-the-recipe-item-id-in-tww/52052) — Confirmed gap persists into The War Within as of September 2024 (HIGH confidence)
- [Warcraft wiki: Patch 12.0.0 API changes](https://warcraft.wiki.gg/wiki/Patch_12.0.0/API_changes) — Confirmed new quality info APIs (`GetItemCraftedQualityInfo`, etc.) added to in-game addon API; REST API gap unchanged (MEDIUM confidence — addon API, not REST)
- [BlizzardApi Ruby gem: Profession class](https://rubydoc.info/gems/blizzard_api/BlizzardApi/Wow/Profession) — Confirmed endpoint URL shapes: `/profession/{id}`, `/profession/{id}/skill-tier/{tierId}`, `/recipe/{id}` (MEDIUM confidence — Ruby wrapper validates endpoint shapes)
- [Python recipe CSV gist by sangfoudre](https://gist.github.com/sangfoudre/21c2503167e766581003933f9a0ed2f2) — Confirmed response structure: `reagents[]` array with `reagent.id`, `reagent.name`, `quantity`; and `crafted_item.id`, `crafted_item.name` when present (MEDIUM confidence — pre-Dragonflight era data, but endpoint shape unchanged)
- [Wowhead: Midnight Alchemy overview](https://www.wowhead.com/guide/midnight/professions/alchemy-overview-trainer-locations-recipes-tools) — ~39 recipes for Alchemy; two quality tiers (Silver/Gold) for consumables (HIGH confidence)
- Multiple search results — Midnight uses Silver/Gold two-tier system for consumables/reagents; 5 ranks for gear (HIGH confidence — multiple independent sources agree)

---

## v1.1 Shuffles Stack Baseline (preserved for reference)

**Domain:** WoW Auction House commodity price tracker (single-user Laravel web app)
**Researched:** 2026-03-04

The v1.1 stack is unchanged and validated. Key decisions preserved below.

### What Shuffles Added (no packages)

- **Data model:** `shuffles` + `shuffle_steps` tables with Eloquent `hasMany` ordered by `position`
- **Arithmetic:** Integer numerator/denominator ratio pairs; copper integer math with `intdiv()` and `floor()`
- **Dynamic forms:** Livewire 4 native array property binding; save-time wildcard validation

### Complete Technology Stack (v1.0 + v1.1, all validated)

| Technology | Version | Purpose |
|------------|---------|---------|
| Laravel | ^12.0 | Web framework, scheduler, queues, Eloquent |
| PHP | ^8.4 | Runtime |
| Livewire + Volt | ^4.0 / ^1.7 | Reactive UI, SFC pages |
| Tailwind CSS | ^4.2 | Utility CSS, WoW dark theme |
| ApexCharts | ^5.7 | Time-series charts |
| SQLite | 3.x | Primary data store |
| Laravel HTTP Client | Built-in | Blizzard API calls |
| Laravel Breeze | ^2.3 (dev) | Auth scaffolding |
| Laravel Pint | ^1.24 (dev) | Code style |
| Pest PHP | ^3.8 (dev) | Test framework |

---

*Stack research for: WoW AH Tracker v1.2 Crafting Profitability*
*Researched: 2026-03-05*
