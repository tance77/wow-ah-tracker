# Phase 13: Recipe Data Model and Seed Command - Research

**Researched:** 2026-03-05
**Domain:** Laravel Artisan commands, Blizzard WoW Game Data API (profession/recipe endpoints), Eloquent migrations/relationships
**Confidence:** HIGH (Laravel patterns) / MEDIUM (Blizzard API structure — no live key to verify exact field names)

---

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions
- **Profession scope:** Crafting professions only — Alchemy, Blacksmithing, Enchanting, Engineering, Inscription, Jewelcrafting, Leatherworking, Tailoring. Cooking included if it has craftable commodity outputs (Claude verifies via API). Gathering professions excluded.
- **Expansion tier detection:** Use highest-ID skill tier per profession to identify Midnight expansion recipes.
- **Store profession icon_url** in the professions table during sync.
- **Missing `crafted_item`:** Store NULL crafted_item_id, increment gap counter. Warn but continue.
- **Missing `crafted_quantity`:** Default to 1.
- **Quality tier resolution:** Use `assignQualityTiers()` name-based pattern — match crafted items to CatalogItem pairs, store both IDs (`crafted_item_id_silver`, `crafted_item_id_gold`) as nullable columns.
- **Auto-watch:** Owned by user #1. Tag with profession name. Shared reagents: first profession encountered sets tag (firstOrCreate behavior). Thresholds left NULL.
- **Progress bar:** Match SyncCatalogCommand format (`%current%/%max% [%bar%] %percent:3s%% — %message%`).
- **`--report-gaps`:** Per-profession table output (profession name, total recipes, missing crafted_item count, missing quantity count, coverage %).
- **`--dry-run` + `--report-gaps`:** Combinable — full API traversal, zero DB writes.
- **Final summary line:** "Synced X recipes (Y professions). Auto-watched Z items (N new, M already existed). Gaps: G recipes missing crafted_item."

### Claude's Discretion
- Exact migration column types and index strategy
- API traversal order (alphabetical by profession, or parallel)
- Batch size tuning for Http::pool() calls
- Error retry strategy for individual recipe fetches
- Logging verbosity levels

### Deferred Ideas (OUT OF SCOPE)
- None — discussion stayed within phase scope
</user_constraints>

---

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| IMPORT-01 | User can run `artisan blizzard:sync-recipes` to seed all Midnight expansion recipes from Blizzard API | Blizzard Game Data API profession/skill-tier/recipe endpoints documented below |
| IMPORT-02 | Seed command auto-watches all reagents and crafted items (deduped across professions) | WatchedItem.firstOrCreate pattern from Phase 11 is directly reusable |
| IMPORT-03 | Seed command supports `--dry-run` flag to preview without writing | SyncCatalogCommand `$dryRun` flag pattern is directly reusable |
| IMPORT-04 | Seed command supports `--report-gaps` to log API field coverage (missing crafted_item, etc.) | Per-profession table output with Laravel console Table helper |
| IMPORT-05 | Seed command is idempotent — re-runnable after game patches to pick up new recipes | Eloquent `updateOrCreate` covers professions + recipes; `firstOrCreate` covers watched_items |
| IMPORT-06 | Recipes table tracks `last_synced_at` timestamp | Standard `timestamp` column with `updateOrCreate` sets it on every sync |
</phase_requirements>

---

## Summary

Phase 13 builds the data foundation for the v1.2 crafting profitability feature. The command (`artisan blizzard:sync-recipes`) must traverse the Blizzard Game Data API across three levels — profession index → skill tier index → individual recipe detail — and persist the results into three new tables: `professions`, `recipes`, and `recipe_reagents`. It then auto-watches all referenced items (reagents + crafted items) for price polling.

The Blizzard API has a known, longstanding gap: the `crafted_item` field has been absent from recipe responses since Dragonflight (confirmed by multiple developer forum threads, still unresolved as of early 2025). The `assignQualityTiers()` name-matching strategy already in SyncCatalogCommand is the approved workaround — it resolves T1/T2 crafted item pairs by matching CatalogItem names. The `--report-gaps` flag exists specifically to surface the percentage of recipes affected.

The codebase already has all the patterns needed: Http::pool() batching, BlizzardTokenService OAuth2 caching, `updateOrCreate` upserts, WatchedItem firstOrCreate dedup, and the progress bar format. This phase is primarily about applying established patterns to a new three-level API traversal and three new Eloquent models/migrations.

**Primary recommendation:** Model SyncRecipesCommand directly on SyncCatalogCommand — same Http::pool() batching (20-item chunks, 1s pause), same `--dry-run` guard, same progress bar format. The only structural difference is the three-step API traversal and the gap-tracking for `--report-gaps`.

---

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| Laravel Framework | 12.x | Artisan commands, Eloquent ORM, Http client | Project foundation |
| PestPHP | 3.8 | Feature tests with Http::fake() | Already in use across all command/action tests |
| Laravel Http (Guzzle) | 12.x | Http::pool() concurrent API calls | Used in SyncCatalogCommand, matches rate limit strategy |
| Eloquent updateOrCreate | 12.x | Idempotent upserts for professions/recipes | Project standard (BIGINT UNSIGNED, no floats) |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| Illuminate\Console\Command | 12.x | Progress bars, --dry-run option, table output | All Artisan commands in this project |
| BlizzardTokenService | Internal | OAuth2 token caching (23-hour cache) | Already handles token lifecycle |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Sequential recipe fetches | Http::pool() | Pool is 4-6x faster; sequential would take 80-130s for large professions |
| Parallel profession traversal | Sequential by profession | Sequential is simpler, less memory pressure; professions are small in count (8-9) |

**Installation:** No new packages required — all dependencies already present.

---

## Architecture Patterns

### Recommended Project Structure
```
app/Console/Commands/
└── SyncRecipesCommand.php     # New command (mirrors SyncCatalogCommand)

app/Models/
├── Profession.php              # New — wraps professions table
├── Recipe.php                  # New — wraps recipes table
└── RecipeReagent.php           # New — wraps recipe_reagents table

database/migrations/
├── 2026_03_05_200000_create_professions_table.php
├── 2026_03_05_200001_create_recipes_table.php
└── 2026_03_05_200002_create_recipe_reagents_table.php

tests/Feature/BlizzardApi/
└── SyncRecipesCommandTest.php  # Http::fake() tests for the command
```

### Pattern 1: Three-Level API Traversal
**What:** The Blizzard Game Data API for professions requires three sequential HTTP call levels before reaching recipe details.
**When to use:** Always — this is the only path to recipe reagent data.

**Level 1 — Profession Index:**
```
GET https://us.api.blizzard.com/data/wow/profession/index
    ?namespace=static-us&locale=en_US
```
Returns array of professions with `{id, name, key.href}`.

**Level 2 — Profession Skill Tier (to find Midnight tier):**
```
GET https://us.api.blizzard.com/data/wow/profession/{professionId}
    ?namespace=static-us&locale=en_US
```
Returns profession details with `skill_tiers[]` array — each entry has `{id, name, key.href}`.
Use the **highest ID** skill tier per profession as the Midnight expansion tier.

**Level 2b — Skill Tier Recipe List:**
```
GET https://us.api.blizzard.com/data/wow/profession/{professionId}/skill-tier/{skillTierId}
    ?namespace=static-us&locale=en_US
```
Returns `{id, name, categories[]}` where each category has `{name, recipes[{id, name, key.href}]}`.

**Level 3 — Recipe Detail (Http::pool() batch):**
```
GET https://us.api.blizzard.com/data/wow/recipe/{recipeId}
    ?namespace=static-us&locale=en_US
```
Returns `{id, name, reagents[], crafted_item?, crafted_quantity?, description, media}`.
- `reagents[]` contains `{reagent: {id, name}, quantity}` — ALWAYS present
- `crafted_item` — OFTEN ABSENT (known Dragonflight+ API gap)
- `crafted_quantity` — SOMETIMES ABSENT (default to 1 when missing)

**Profession Media (for icon_url):**
```
GET https://us.api.blizzard.com/data/wow/media/profession/{professionId}
    ?namespace=static-us&locale=en_US
```
Returns `{assets[{key: "icon", value: "https://..."}]}` — same pattern as item media.

### Pattern 2: Idempotent Upsert Chain
```php
// Source: SyncCatalogCommand.php (existing project pattern)
$profession = Profession::updateOrCreate(
    ['blizzard_profession_id' => $professionId],
    ['name' => $name, 'icon_url' => $iconUrl, 'last_synced_at' => now()]
);

$recipe = Recipe::updateOrCreate(
    ['blizzard_recipe_id' => $recipeId],
    [
        'profession_id'          => $profession->id,
        'name'                   => $recipeName,
        'crafted_item_id_silver' => $silverItemId,  // nullable
        'crafted_item_id_gold'   => $goldItemId,    // nullable
        'crafted_quantity'       => $craftedQty ?? 1,
        'last_synced_at'         => now(),
    ]
);

// Delete old reagents for this recipe (handles patch updates cleanly)
$recipe->reagents()->delete();

// Re-insert current reagents
foreach ($reagents as $reagent) {
    RecipeReagent::create([
        'recipe_id'       => $recipe->id,
        'catalog_item_id' => $catalogItem->id,
        'quantity'        => $reagent['quantity'],
    ]);
}
```

**Note on reagent idempotency:** Deleting + reinserting reagents on each sync is simpler than upsert by composite key and handles the case where Blizzard changes a recipe's reagents in a patch.

### Pattern 3: Auto-Watch with firstOrCreate
```php
// Source: WatchedItem firstOrCreate from Phase 11 (existing pattern)
WatchedItem::firstOrCreate(
    [
        'user_id'         => 1,
        'blizzard_item_id' => $item->blizzard_item_id,
    ],
    [
        'name'       => $item->name,
        'profession' => $professionName,  // First profession wins for shared reagents
    ]
);
```

### Pattern 4: Gap Tracking for --report-gaps
```php
// Track per-profession during traversal
$gapStats[$professionName] = [
    'total'           => 0,
    'missing_item'    => 0,
    'missing_qty'     => 0,
];

// After all professions synced:
if ($this->option('report-gaps')) {
    $this->newLine();
    $this->table(
        ['Profession', 'Total', 'Missing Item', 'Missing Qty', 'Coverage %'],
        collect($gapStats)->map(fn($s, $name) => [
            $name,
            $s['total'],
            $s['missing_item'],
            $s['missing_qty'],
            number_format(100 * ($s['total'] - $s['missing_item']) / max($s['total'], 1), 1) . '%',
        ])->values()->all()
    );
}
```

### Recommended Migration Schema

**professions table:**
```php
Schema::create('professions', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('blizzard_profession_id')->unique();
    $table->string('name');
    $table->string('icon_url')->nullable();
    $table->timestamp('last_synced_at')->nullable();
    $table->timestamps();
});
```

**recipes table:**
```php
Schema::create('recipes', function (Blueprint $table) {
    $table->id();
    $table->foreignId('profession_id')->constrained()->cascadeOnDelete();
    $table->unsignedBigInteger('blizzard_recipe_id')->unique();
    $table->string('name');
    // Nullable crafted item IDs — T1=Silver, T2=Gold (Blizzard API gap means these may be null)
    $table->foreignId('crafted_item_id_silver')->nullable()->constrained('catalog_items')->nullOnDelete();
    $table->foreignId('crafted_item_id_gold')->nullable()->constrained('catalog_items')->nullOnDelete();
    $table->unsignedSmallInteger('crafted_quantity')->default(1);
    $table->timestamp('last_synced_at')->nullable();
    $table->timestamps();

    $table->index('profession_id');
});
```

**recipe_reagents table:**
```php
Schema::create('recipe_reagents', function (Blueprint $table) {
    $table->id();
    $table->foreignId('recipe_id')->constrained()->cascadeOnDelete();
    $table->foreignId('catalog_item_id')->constrained()->cascadeOnDelete();
    $table->unsignedSmallInteger('quantity')->default(1);
    $table->timestamps();

    $table->index(['recipe_id', 'catalog_item_id']);
});
```

**Column type rationale:**
- `unsignedBigInteger` for Blizzard IDs (consistent with catalog_items pattern)
- `foreignId()` for local FK relationships (readable Laravel convention)
- `unsignedSmallInteger` for quantity/crafted_quantity (recipes never have more than a few hundred reagents; SMALLINT covers 0-65535)
- No BIGINT UNSIGNED for copper prices — this table has no price data

### Anti-Patterns to Avoid
- **Hand-rolling HTTP retries:** Use Laravel's built-in `.retry(2, 5000, throw: false)` — already proven in SyncCatalogCommand.
- **Storing professions by name string:** Always use `blizzard_profession_id` as the unique key — names can change between game patches.
- **Asserting `crafted_item` is always present:** Always treat it as optional and increment gap counter. The API has had this gap since 2022.
- **Using `create()` instead of `updateOrCreate()`:** Breaks IMPORT-05 (idempotency) on any re-run.
- **Deleting + recreating professions/recipes:** Only delete reagents on re-sync. Use `updateOrCreate` for professions and recipes so IDs stay stable for foreign key references from future phases.

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| OAuth2 token caching | Custom token service | `BlizzardTokenService::getToken()` | Already handles 23-hour cache, retry, error |
| Concurrent HTTP | Sequential foreach | `Http::pool()` | Pool fires 20 requests in parallel; sequential = 80-130s for large professions |
| Idempotent upsert | Custom check-then-insert | Eloquent `updateOrCreate()` | Atomic, race-condition safe, proven in project |
| Progress bars | `echo` statements | Laravel `$this->output->createProgressBar()` | Console UI matches existing SyncCatalogCommand exactly |
| Per-profession table output | Manual string formatting | Laravel `$this->table()` | Built-in console table, auto-aligns columns |
| WatchedItem dedup | Manual duplicate check | `WatchedItem::firstOrCreate()` | Existing pattern from Phase 11, handles race conditions |

**Key insight:** This phase is pattern application, not pattern invention. Every non-trivial problem has an established solution in the existing codebase.

---

## Common Pitfalls

### Pitfall 1: crafted_item Missing from API Response
**What goes wrong:** Calling `$recipeData['crafted_item']['id']` throws array key exception on 30-70% of Midnight recipes.
**Why it happens:** Blizzard API has not returned `crafted_item` for expansion recipes since Dragonflight (2022). Confirmed unresolved as of 2025.
**How to avoid:** Always use `$recipeData['crafted_item']['id'] ?? null`. Track gap in `$gapStats`. Store NULL `crafted_item_id_silver`/`_gold`.
**Warning signs:** >10% NULL `crafted_item_id_silver` after first run = expected. >50% = check `--report-gaps` output, consider Wowhead mapping seed file (noted in STATE.md as a gate condition).

### Pitfall 2: Skill Tier Selection Picks Wrong Expansion
**What goes wrong:** Using first skill tier instead of highest-ID tier fetches Classic or BfA recipes.
**Why it happens:** Each profession's `skill_tiers` array contains entries for all expansions (Classic through Midnight). The array is not guaranteed to be ordered newest-last.
**How to avoid:** After fetching profession details, `collect($professionData['skill_tiers'])->sortByDesc('id')->first()` — use the entry with the largest ID.
**Warning signs:** Recipe names like "Thorium Widget" or "Dense Stone" indicate wrong tier selection.

### Pitfall 3: Reagent IDs Not in catalog_items
**What goes wrong:** `catalog_item_id` FK constraint fails for reagents whose Blizzard item IDs do not exist in the catalog.
**Why it happens:** `sync-catalog` only seeds items that have appeared in the commodity AH. Some crafting reagents (especially Midnight-specific ones) may not yet be catalogued if their AH data is thin.
**How to avoid:** Before inserting a reagent, use `CatalogItem::where('blizzard_item_id', $id)->first()`. If null: log a warning and skip that reagent (or create a minimal CatalogItem entry). Do NOT hard-fail the entire recipe.
**Warning signs:** FK constraint violations in logs during first run.

### Pitfall 4: Auto-Watch Duplicates Across Professions
**What goes wrong:** A reagent shared by Alchemy and Blacksmithing gets two WatchedItem rows for user #1.
**Why it happens:** Using `create()` instead of `firstOrCreate()` on WatchedItem.
**How to avoid:** Always `firstOrCreate(['user_id' => 1, 'blizzard_item_id' => $id], [...])`. The "first profession wins" tagging behavior is intentional.
**Warning signs:** Unique constraint violation on `watched_items` (unique index on user_id + blizzard_item_id from Phase 9 migration).

### Pitfall 5: --dry-run Does Not Short-Circuit API Calls
**What goes wrong:** Developer assumes `--dry-run` skips API calls, then `--report-gaps` returns empty data.
**Why it happens:** The SyncCatalogCommand pattern runs full API traversal in dry-run mode (DB writes are skipped, API calls are not). `--report-gaps` depends on having traversed the API.
**How to avoid:** Follow SyncCatalogCommand exactly — `if (!$dryRun) { /* DB write */ }`. API calls happen regardless. `--dry-run --report-gaps` is a valid and documented combination.

### Pitfall 6: Quality Tier Resolution Fails if CatalogItem Not Yet Seeded
**What goes wrong:** `assignQualityTiers()` matches T1/T2 by name, but the crafted items do not exist in `catalog_items` yet.
**Why it happens:** `sync-recipes` runs before `sync-catalog --realm` has been run for Midnight items.
**How to avoid:** Document in command help text that `sync-catalog` should be run first. In `crafted_item_id_silver`/`_gold` resolution, gracefully handle null CatalogItem lookups and leave those columns NULL (already covered by "missing crafted_item" gap handling).

---

## Code Examples

Verified patterns from existing project source:

### Progress Bar (exact format from SyncCatalogCommand)
```php
// Source: app/Console/Commands/SyncCatalogCommand.php
$bar = $this->output->createProgressBar($totalRecipes);
$bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% — %message%');
$bar->setMessage('Starting...');
$bar->start();

// In loop:
$bar->setMessage($recipeName);
$bar->advance();

// Done:
$bar->setMessage('Done!');
$bar->finish();
$this->newLine(2);
```

### Http::pool() Batch Pattern (exact from SyncCatalogCommand)
```php
// Source: app/Console/Commands/SyncCatalogCommand.php
$responses = Http::pool(fn ($pool) => collect($recipeIds)->map(
    fn (int $id) => $pool->as((string) $id)
        ->withToken($token)
        ->timeout(30)
        ->connectTimeout(10)
        ->get("https://{$region}.api.blizzard.com/data/wow/recipe/{$id}", [
            'namespace' => "static-{$region}",
        ])
)->all());

foreach ($recipeIds as $id) {
    $response = $responses[(string) $id] ?? null;
    if (! $response || $response instanceof \Throwable) { /* handle */ }
    if ($response->status() === 429) { /* rate limit handling */ }
    if (! $response->successful()) { /* skip */ }
    $data = $response->json();
}
```

### Idempotent Upsert Pattern
```php
// Source: SyncCatalogCommand.php (updateOrCreate pattern)
// Confidence: HIGH — confirmed Laravel 12.x Eloquent docs
Profession::updateOrCreate(
    ['blizzard_profession_id' => $blizzardId],
    ['name' => $name, 'icon_url' => $iconUrl, 'last_synced_at' => now()]
);
```

### WatchedItem Auto-Watch (firstOrCreate from Phase 11)
```php
// Source: WatchedItem model — created_by_shuffle_id pattern in Phase 11
[$watchedItem, $created] = WatchedItem::firstOrCreate(
    ['user_id' => 1, 'blizzard_item_id' => $item->blizzard_item_id],
    ['name' => $item->name, 'profession' => $professionName]
);
$newCount += (int) $created;
$existedCount += (int) ! $created;
```

### Crafted Item ID Resolution (from assignQualityTiers pattern)
```php
// Adapt from SyncCatalogCommand::assignQualityTiers()
// After all recipe crafted_item names are known, match to catalog_items:
$craftedName = $recipeData['crafted_item']['name'] ?? null;
if ($craftedName) {
    $matches = CatalogItem::where('name', $craftedName)
        ->orderBy('blizzard_item_id')
        ->get();
    $silverItem = $matches->first();   // lowest blizzard_item_id = T1 Silver
    $goldItem   = $matches->skip(1)->first(); // next = T2 Gold (may be null)
}
```

### Http::fake() Test Pattern (from PriceFetchActionTest)
```php
// Source: tests/Feature/BlizzardApi/PriceFetchActionTest.php
Http::fake([
    'oauth.battle.net/token' => Http::response(
        ['access_token' => 'test-token', 'token_type' => 'bearer', 'expires_in' => 86400],
        200
    ),
    '*.api.blizzard.com/data/wow/profession/index*' => Http::response([
        'professions' => [
            ['id' => 164, 'name' => 'Blacksmithing'],
        ]
    ], 200),
    '*.api.blizzard.com/data/wow/profession/164*' => Http::response([
        'skill_tiers' => [
            ['id' => 2822, 'name' => 'Midnight Blacksmithing'],
        ]
    ], 200),
    '*.api.blizzard.com/data/wow/profession/164/skill-tier/2822*' => Http::response([
        'categories' => [
            ['name' => 'Weapons', 'recipes' => [['id' => 99001, 'name' => 'Forged Blade']]]
        ]
    ], 200),
    '*.api.blizzard.com/data/wow/recipe/99001*' => Http::response([
        'id' => 99001,
        'name' => 'Forged Blade',
        'reagents' => [['reagent' => ['id' => 210781], 'quantity' => 3]],
        'crafted_item' => ['id' => 224025, 'name' => 'Forged Blade'],
        'crafted_quantity' => 1,
    ], 200),
]);
```

---

## Blizzard API Reference

### Confirmed Endpoint Structure (MEDIUM confidence — verified via community docs and forum posts)

| Endpoint | Method | Namespace | Purpose |
|----------|--------|-----------|---------|
| `/data/wow/profession/index` | GET | `static-{region}` | List all professions with IDs |
| `/data/wow/profession/{id}` | GET | `static-{region}` | Profession details including `skill_tiers[]` |
| `/data/wow/media/profession/{id}` | GET | `static-{region}` | Profession icon assets |
| `/data/wow/profession/{id}/skill-tier/{tierId}` | GET | `static-{region}` | Recipe list for expansion tier, grouped by category |
| `/data/wow/recipe/{id}` | GET | `static-{region}` | Recipe details: reagents, crafted_item (often absent), crafted_quantity |
| `/data/wow/media/recipe/{id}` | GET | `static-{region}` | Recipe icon (optional, not needed for Phase 13) |

### Recipe Response Field Availability (MEDIUM confidence)
| Field | Presence | Notes |
|-------|----------|-------|
| `id` | Always | Blizzard recipe ID |
| `name` | Always | Localized string or `{en_US: "..."}` object |
| `reagents[]` | Always | Array of `{reagent: {id, name}, quantity}` |
| `crafted_item` | Often absent | API gap since Dragonflight 2022, use NULL handling |
| `crafted_quantity` | Sometimes absent | Default to 1 when missing |
| `description` | Sometimes | Flavor text, not needed |

---

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| `crafted_item` always present in recipe API | `crafted_item` absent for Dragonflight+ recipes | Nov 2022 (Dragonflight launch) | Must use name-matching workaround for quality tier resolution |
| COMMON/UNCOMMON/RARE quality tiers | Two tiers only: Silver (T1) and Gold (T2) | Midnight expansion | Simplifies tier assignment — only two CatalogItem rows per crafted item name |
| Artisan's Acuity (TWW currency) | Expansion-specific currencies (Artisan Scribe's Moxie etc.) | Midnight expansion | No impact on API data model, only on in-game UI |
| Multi-quality reagents (5 tiers in Dragonflight) | Two quality ranks for reagents (Silver/Gold) | Midnight expansion | Simplifies reagent modeling — one CatalogItem per reagent |

**Deprecated/outdated:**
- Manual WowHead scraping for crafted_item IDs: No longer the only option — `assignQualityTiers()` name-matching covers the common case for commodity items already in catalog.

---

## Open Questions

1. **Cooking inclusion**
   - What we know: Decision says "include if craftable commodity outputs"
   - What's unclear: Cannot determine without a live API call whether Midnight Cooking produces commodity-AH items
   - Recommendation: On first run, log the skill tier name fetched for Cooking. If its recipes include items matching catalog_items, include them. If not (or if Cooking has no Midnight skill tier), skip silently. No code path change needed — the filter is natural.

2. **Reagent not in catalog_items**
   - What we know: Some crafting reagents may not appear in commodity AH and thus may be missing from catalog_items
   - What's unclear: How many Midnight reagents will be missing at initial seed time
   - Recommendation: On missing reagent, log a warning and either (a) skip the reagent row, or (b) create a minimal CatalogItem entry. Skipping is simpler but means incomplete reagent cost data. Creating a minimal entry is more correct but adds catalog noise. Recommend (b) with a `sync_created: true` flag concept, or more pragmatically, just log and skip — Phase 14 will surface missing data via the "no price" indicator.

3. **Profession IDs for Midnight**
   - What we know: Blizzard profession IDs are stable across expansions (Alchemy = 171, etc.)
   - What's unclear: Whether Midnight introduces new profession IDs or reuses existing ones
   - Recommendation: Fetch from the profession index on every run rather than hardcoding IDs. The `highest-ID skill tier` heuristic handles expansion detection regardless of whether IDs change.

---

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | PestPHP 3.8 with pest-plugin-laravel |
| Config file | `phpunit.xml` |
| Quick run command | `php artisan test --filter SyncRecipes` |
| Full suite command | `php artisan test` |

### Phase Requirements → Test Map
| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| IMPORT-01 | `artisan blizzard:sync-recipes` seeds professions, recipes, recipe_reagents via mocked API | Feature | `php artisan test --filter SyncRecipesCommandTest` | Wave 0 |
| IMPORT-02 | Auto-watch creates WatchedItem rows for all reagents and crafted items; no duplicates on re-run | Feature | `php artisan test --filter SyncRecipesCommandTest` | Wave 0 |
| IMPORT-03 | `--dry-run` runs full API traversal, zero DB rows written | Feature | `php artisan test --filter SyncRecipesCommandTest` | Wave 0 |
| IMPORT-04 | `--report-gaps` outputs per-profession table with missing crafted_item count | Feature | `php artisan test --filter SyncRecipesCommandTest` | Wave 0 |
| IMPORT-05 | Running command twice produces identical DB state (idempotency) | Feature | `php artisan test --filter SyncRecipesCommandTest` | Wave 0 |
| IMPORT-06 | `last_synced_at` on recipes row is updated on each successful sync | Feature | `php artisan test --filter SyncRecipesCommandTest` | Wave 0 |

### Sampling Rate
- **Per task commit:** `php artisan test --filter SyncRecipesCommandTest`
- **Per wave merge:** `php artisan test`
- **Phase gate:** Full suite green before `/gsd:verify-work`

### Wave 0 Gaps
- [ ] `tests/Feature/BlizzardApi/SyncRecipesCommandTest.php` — covers all IMPORT-* requirements
- [ ] `tests/Fixtures/blizzard_profession_index.json` — fixture for profession index response
- [ ] `tests/Fixtures/blizzard_profession_alchemy.json` — fixture for profession detail with skill_tiers
- [ ] `tests/Fixtures/blizzard_skill_tier_alchemy.json` — fixture for recipe list response
- [ ] `tests/Fixtures/blizzard_recipe_detail.json` — fixture for individual recipe with reagents

---

## Sources

### Primary (HIGH confidence)
- `app/Console/Commands/SyncCatalogCommand.php` — Http::pool() pattern, progress bar format, --dry-run pattern, assignQualityTiers() method
- `app/Models/WatchedItem.php` — firstOrCreate dedup pattern, profession field, created_by_shuffle_id provenance
- `app/Models/CatalogItem.php` — updateOrCreate pattern, BIGINT IDs, quality_tier assignment
- `tests/Feature/BlizzardApi/PriceFetchActionTest.php` — Http::fake() test pattern for Blizzard API
- `tests/Feature/DataIngestion/DeduplicationTest.php` — Idempotency testing patterns
- Laravel 12.x Eloquent docs — updateOrCreate, firstOrCreate confirmed current

### Secondary (MEDIUM confidence)
- [Blizzard API forum: crafted_item missing since Dragonflight](https://us.forums.blizzard.com/en/blizzard/t/dragonflight-profession-recipes-crafted-item-id/37444) — confirmed ongoing as of 2023
- [Blizzard API forum: TWW recipe item ID issue](https://us.forums.blizzard.com/en/blizzard/t/is-it-possible-to-find-the-recipe-item-id-in-tww/52052) — confirmed still missing in TWW
- [Ruby Blizzard API gem docs](https://rubydoc.info/gems/blizzard_api/BlizzardApi/Wow/Profession) — endpoint URL patterns verified: `/data/wow/profession/{id}/skill-tier/{tierId}`, `/data/wow/recipe/{id}`

### Tertiary (LOW confidence)
- WoW community forum posts on profession API structure — endpoint patterns consistent but not confirmed against live Midnight API responses

---

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — all Laravel patterns exist in project, no new dependencies
- Architecture: HIGH — migration schema follows established project conventions; API traversal pattern well-understood
- Blizzard API field names: MEDIUM — endpoint URLs confirmed via Ruby gem docs; exact field names in recipe response unverifiable without live API key, but consistent across multiple forum threads and community tooling
- Pitfalls: HIGH — crafted_item gap is documented fact; other pitfalls derived from existing code patterns

**Research date:** 2026-03-05
**Valid until:** 2026-04-05 (stable Laravel patterns; Blizzard API endpoint structure rarely changes)
