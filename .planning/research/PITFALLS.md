# Pitfalls Research

**Domain:** WoW AH Tracker — v1.2 Crafting Profitability (recipe-based profit calculator added to existing commodity price tracker)
**Researched:** 2026-03-05
**Confidence:** MEDIUM-HIGH. Blizzard API gaps verified via official developer forum threads. Midnight-specific quality tier behavior extrapolated from TWW patterns given API continuity. Integration pitfalls derived from actual codebase analysis.

---

## Critical Pitfalls

### Pitfall 1: Recipe Endpoint Does Not Include Crafted Item ID (Dragonflight Onward)

**What goes wrong:**
The Blizzard Game Data API `/data/wow/recipe/{recipeId}` endpoint stopped including a `crafted_item` field starting in Dragonflight. This was never restored. You query a recipe, receive reagent data (sometimes), but get no pointer to what item ID the recipe produces. Without the output item ID you cannot look up its price in the commodities feed — the entire profit calculation loop cannot close. This affects every single recipe in the Midnight profession set.

**Why it happens:**
Developers assume the recipe endpoint is a complete, self-contained contract. It was in Shadowlands. Since Dragonflight introduced quality tiers (each quality produces a distinct item ID), Blizzard changed the data model but did not update the API to express multiple output item IDs per recipe. The omission is not documented prominently; it surfaces only when you inspect an actual API response.

**How to avoid:**
Build the recipe seeder to source output item ID mappings from Wowhead's item database or a maintained community mapping file, in addition to the Blizzard API. Store per-quality output item IDs as explicit columns: `crafted_item_id_silver` and `crafted_item_id_gold` (Midnight uses two quality tiers). Accept that the Blizzard recipe endpoint contributes reagent metadata; the crafted item IDs must come from a secondary source. Do not attempt to derive these at runtime from API calls alone.

**Warning signs:**
- Recipe seeder produces rows with NULL `crafted_item_id_*` columns
- Profit calculations show "no price data" for crafted items even when matching prices exist in the commodity feed
- A test query to `/data/wow/recipe/{recipeId}` returns JSON without a `crafted_item` key

**Phase to address:** Recipe data seeding phase — the earliest data work. Must be resolved before any profit calculation can be built.

---

### Pitfall 2: Quality Tier Item IDs Are Not Derivable from the Item API

**What goes wrong:**
In Midnight, crafted consumables and reagents have two quality levels (Silver and Gold — Bronze was removed). Each quality level is a distinct `blizzard_item_id`. The Blizzard `/data/wow/item/{itemId}` endpoint returns near-identical responses for both quality variants of the same item — it does not expose which quality tier an item represents. You cannot query the item endpoint and determine programmatically that "item 228233 is Silver quality, item 228234 is Gold quality" for the same base item.

**Why it happens:**
Developers query the item endpoint expecting something equivalent to `quality_tier: 1` in the response. The field does not exist. This gap has been present throughout Dragonflight and The War Within, confirmed on Blizzard's developer forums as unresolved. Developers discover it mid-implementation when they try to build a quality-aware price display and find both item IDs return the same metadata.

**How to avoid:**
Seed `crafted_item_id_silver` and `crafted_item_id_gold` as explicit separate schema columns per recipe row. Source these mappings from Wowhead (each quality variant has its own Wowhead item page with a distinct item ID visible in the URL). Treat this as static seed data — not something that can be derived from the API at runtime. For reagent quality variants (Silver-quality herb vs Gold-quality herb used as inputs), apply the same pattern: explicit item ID per quality tier.

**Warning signs:**
- Schema has a single `crafted_item_id` column with no quality differentiation
- T1 and T2 profit rows show the same price (both pointing to the same item ID)
- Any code path that calls the item API to determine quality tier

**Phase to address:** Recipe data seeding phase. The schema must have quality-specific item ID columns before the first migration runs — retrofitting requires a migration and full re-seed.

---

### Pitfall 3: Modified Crafting Slot Reagent Quantities Not in API Response

**What goes wrong:**
The Blizzard recipe endpoint exposes `modified_crafting_slots` for optional finishing reagents (gems, embellishments, optional enchanting materials that boost craft quality). These slots include the slot type name and ID but NOT the quantity required. A recipe might require 2 optional gems but the API only tells you a gem slot exists — not how many. Profit calculations that silently skip optional reagent costs will systematically overstate profitability for recipes that use optional reagents, which includes most high-value crafted gear in Midnight.

**Why it happens:**
This is a known, unresolved API bug reported on Blizzard's developer forums. Regular `reagents[]` entries correctly include quantities; the gap is specific to `modified_crafting_slots`. Developers testing with simple recipes never hit the gap; it surfaces only when testing recipes with optional reagents, which tend to be tested later.

**How to avoid:**
For the initial implementation, exclude optional reagent costs from the profit formula and show an explicit "optional reagents not included" label on affected recipes. Alternatively, hardcode known quantities for common optional reagents (typically 1 per craft slot; verify against Wowhead). Store a `has_optional_reagents` boolean on the recipe row so the UI can display a consistent disclaimer without querying the API. Never ship this silently — a missing cost that looks like a zero is worse than a visible disclaimer.

**Warning signs:**
- Profit figures for gem-socketed or embellished crafts appear significantly higher than CraftSim or TSM in-game reports
- `modified_crafting_slots` response has slot type names but no `quantity` key
- No `has_optional_reagents` column or UI disclaimer in the profession table

**Phase to address:** Profit calculation phase. Schema must accommodate nullable optional reagent quantities; UI must communicate when optional costs are excluded.

---

### Pitfall 4: Crafted Item Yield Quantity Not Reliably in Recipe Endpoint

**What goes wrong:**
Some recipes produce multiple items per craft — alchemy potions yield 5 or 10 depending on recipe rank; some cooking recipes yield stacks. The Blizzard recipe endpoint has a `crafted_item.quantity` field in the schema but the value is absent or zero for multi-yield recipes. If your profit formula treats all recipes as yielding 1 item, a potion recipe that actually yields 5 appears 5x less profitable than it is. Alchemy and cooking categories are most affected.

**Why it happens:**
This is a documented API omission (confirmed on Blizzard developer forums). Developers test with weapon or armor recipes (always yield 1) and miss the gap. The calculation appears correct for 95% of recipe types and only breaks visibly for consumable recipes.

**How to avoid:**
Seed a `yield_quantity` column per recipe. Source yield quantities from Wowhead during seeding, alongside the output item ID mapping. Default to 1 when unknown. Flag alchemy, cooking, and any recipe with "x5" or "x10" in its Wowhead description for manual review. Apply yield in the profit formula: `profit = (sell_price * 0.95 * yield_quantity) - reagent_cost`. Add a test asserting at least one alchemy recipe has `yield_quantity > 1`.

**Warning signs:**
- All recipes have `yield_quantity = 1` including potions and flasks
- No assertion or test on yield_quantity for alchemy/cooking recipe categories
- Profit calculator has no yield multiplier in the formula

**Phase to address:** Recipe data seeding phase. The yield column must exist before the profit formula is implemented.

---

### Pitfall 5: Auto-Watch Reagent Explosion Creates Duplicates and Queue Pressure

**What goes wrong:**
The existing system polls commodity prices for a small curated watchlist. Auto-watching all unique reagents across all Midnight recipes will insert a large batch of new `watched_items` rows at seeding time — estimated 50-150 distinct reagent item IDs across ~10 professions (many reagents are shared across professions). Two specific failure modes: (1) naive per-recipe-per-reagent inserts create duplicate `watched_items` rows for shared reagents, breaking the deduplication assumption the existing system relies on; (2) a sudden jump from a small watchlist to 150+ items increases `DispatchPriceBatchesJob` batch count and `CatalogItem::all()` load, which may interact unexpectedly with the `ShouldBeUnique` lock on `FetchCommodityDataJob`.

**Why it happens:**
The auto-watch pattern from Shuffles (v1.1) works well for 3-8 items per shuffle. Recipe auto-watch fires for every reagent across every seeded recipe simultaneously — a structurally identical pattern at 10-20x the volume. The uniqueness constraint on `(user_id, blizzard_item_id)` in `watched_items` prevents duplicate rows at the DB level but does not prevent duplicate insert attempts that silently fail and confuse the seeder's row count.

**How to avoid:**
Collect all unique reagent `blizzard_item_id` values across all recipes in a single set before inserting. Run one `upsert` or `firstOrCreate` per unique item ID — not per recipe-reagent pair. Add the `(user_id, blizzard_item_id)` unique constraint explicitly if not already enforced. Test the full seeding step against a staging database before production to confirm queue batch counts remain stable. Track reagent auto-watch provenance with `created_by_recipe_source = true` so they can be distinguished from manually-added items.

**Warning signs:**
- Auto-watch seeder loops over recipe reagents without deduplication before DB insert
- `watched_items` count after seeding is higher than the count of distinct reagent item IDs
- Queue batch count in `DispatchPriceBatchesJob` doubles or triples immediately after seeding

**Phase to address:** Auto-watch integration phase. Dedup logic must be validated before the recipe seeder runs against production data.

---

### Pitfall 6: AH Cut Not Applied to Crafted Item Sell Price

**What goes wrong:**
Commodity sales on the WoW Auction House incur a 5% transaction fee deducted from sale proceeds. A recipe output priced at 10,000g nets 9,500g. Profit calculations using raw commodity price as revenue will overstate profit by 5.26% (the inverse of the 5% fee). For thin-margin recipes near breakeven, this gap determines whether the craft is worth doing. The fee applies to the sell side only — buying reagents on the AH does not incur a buyer-side fee.

**Why it happens:**
The commodity endpoint returns the raw listed price — there is no `net_price` field. Developers build `profit = sell_price - reagent_cost` and omit the fee because it is a business rule, not a data field. The error is invisible in unit tests that use round numbers and only surfaces when comparing to in-game addon calculations.

**How to avoid:**
Define a named constant: `const AH_CUT_RATE = 0.05`. Apply it in the profit formula: `net_revenue = sell_price * (1 - AH_CUT_RATE)`. Display it explicitly in the UI as "After 5% AH cut." Do not apply the cut to reagent costs (the buyer of reagents does not pay the AH fee). Write a unit test: given sell_price=10000, reagent_cost=5000, assert profit=4500 not 5000.

**Warning signs:**
- Profit formula is `sell_price - reagent_cost` with no multiplier
- No reference to `0.95` or `AH_CUT` in calculation code
- Profit figures match sell price exactly when reagent cost is zero (should be 95% of sell price)

**Phase to address:** Profit calculation phase. Must be in the formula from day one — retrofitting after users establish baseline expectations causes confusion.

---

### Pitfall 7: Recipe Data Seeded as a Migration Prevents Future Resyncs

**What goes wrong:**
Midnight will receive content patches adding new profession recipes. If recipe data is seeded as a database migration (a one-time data file), re-running the sync after a patch requires either a new migration file or `migrate:fresh` — both are cumbersome and error-prone. Over time the recipe list falls behind and the tracker silently becomes incomplete. Each TWW content patch added 5-20+ new craftable items; Midnight will follow the same pattern.

**Why it happens:**
Database migrations are the obvious place to put initial data in a Laravel project — they run automatically and are tracked. Recipe seeding feels like a one-time setup operation. The distinction between schema migrations (must be immutable) and data seeds (must be re-runnable) is easy to collapse, especially when the initial implementation is the only time recipes need to be seeded.

**How to avoid:**
Build recipe sync as an Artisan command: `artisan craft:sync-recipes`. Design it as an idempotent upsert by `blizzard_recipe_id` — run it twice, get identical results. Do not put recipe data in a migration file. Add a `last_synced_at` timestamp to the `recipes` table so it is visible when data was last refreshed. Document the command in a brief `CRAFTING.md` operations note for use after patches.

**Warning signs:**
- Recipe data lives in a file named `2026_xx_seed_recipes.php` in `database/migrations/`
- No `artisan craft:sync-recipes` command exists
- Running the seeder twice creates duplicate recipe rows

**Phase to address:** Recipe data seeding phase — establish the command-based pattern before any data is written.

---

### Pitfall 8: N+1 Serial API Call Pattern for Recipe Seeding

**What goes wrong:**
Fetching all Midnight recipes from the Blizzard API requires navigating three nested layers: profession list → skill tier list → recipe index → individual recipe detail. For 10 professions with ~40-80 recipes each, a full sync requires 400-800 individual recipe detail API calls. Done sequentially in a foreach loop, each synchronous `Http::get()` call waits for a response before the next begins. At 200ms average API latency, 600 calls takes ~120 seconds. The command appears to hang, and developers restart it mid-run — causing partial seeding.

**Why it happens:**
The first two API layers (profession list and skill tier list) return small payloads and are fast. Developers write a simple nested foreach and don't realize the recipe detail layer is the bottleneck. The sequential pattern works fine when tested against a single profession with 5 recipes during development.

**How to avoid:**
Use `Http::pool()` for the recipe detail layer. After collecting all recipe IDs from the index (fast), dispatch all recipe detail requests as a concurrent pool with a concurrency limit of 20-50. This reduces wall-clock time from ~120s to ~10s for 600 requests. Add a progress bar via `$this->withProgressBar()` in the Artisan command so the command never appears frozen. Throttle to stay well under the 36K/hr Blizzard API cap (600 requests is under 2% of the hourly budget — not a concern).

**Warning signs:**
- Recipe seeder makes one `Http::get()` call per recipe ID inside a sequential loop
- No concurrency or pooling in the recipe fetch implementation
- `craft:sync-recipes` command runs for 2+ minutes with no output

**Phase to address:** Recipe data seeding phase. The concurrent fetch pattern must be designed upfront — sequential implementation will appear functional on partial test sets but fail at full scale.

---

### Pitfall 9: Gear/Equipment Output Items Are Realm-AH, Not Commodities

**What goes wrong:**
The existing system uses the commodities endpoint (`/auctions/commodities`) — the correct approach for crafting materials. Some Midnight crafted items (notably gear with secondary stats) are sold on the realm-specific AH, not the commodities AH. These items will never appear in the commodity price feed regardless of polling frequency. If the tracker treats a missing commodity price as "item not yet priced" rather than "item is not a commodity," it will permanently show "no price data" for craftable gear pieces, silently making them look like untrackable recipes.

**Why it happens:**
The item-vs-commodity distinction is invisible to the tracker — both look like `blizzard_item_id` integers. The commodities feed returns prices for stackable, non-unique goods. Crafted gear (armor, weapons with specific stats) is non-stackable and appears on per-realm AH, outside the commodities endpoint scope.

**How to avoid:**
During recipe seeding, flag each output item as `is_commodity: bool` based on whether the item is stackable and generic (potions, enchants, gems, cloth, ore — commodities) versus gear with stats (not a commodity). For non-commodity items, display "realm AH — not tracked" rather than "no price data." This sets correct expectations without implying a data collection failure. In v1.2 scope, consider skipping profit calculation for non-commodity output items entirely rather than showing misleading zeros.

**Warning signs:**
- All seeded recipe output items show "no price data" despite the commodity feed running normally
- The tracker makes no distinction between "item not yet priced" and "item is not a commodity"
- Gear recipes (plate armor, weapons) are included in the profession table with blank profit columns

**Phase to address:** Recipe data seeding phase. The `is_commodity` flag must be set during seeding before the UI is built to display profit data.

---

## Technical Debt Patterns

| Shortcut | Immediate Benefit | Long-term Cost | When Acceptable |
|----------|-------------------|----------------|-----------------|
| Seed recipes as a DB migration | Simple, runs with `php artisan migrate` | Cannot resync after patches without a new migration; stale data accumulates | Never — use an Artisan command from day one |
| Single `crafted_item_id` column per recipe | Simpler schema | Cannot store per-quality item IDs; T1/T2 profit collapses to one number | Never — per-quality columns cost nothing upfront |
| Omit AH cut from profit formula | Simpler calculation | Overstates profit by 5.26%; thin-margin crafts appear falsely profitable | Never — always apply 0.95 multiplier |
| Use hardcoded recipe data with no sync command | No API complexity at seeding time | Goes stale after first content patch; no recovery path | Acceptable as seed baseline only if sync command also exists |
| Skip optional reagent costs silently | Simpler launch | Overstates profit for embellished/gem recipes with no visible disclaimer | Never — always flag optional reagents as excluded in UI |
| Watch reagents per-recipe without dedup | Simple loop | Duplicate watched_items; unpredictable price snapshot behavior | Never — deduplicate to unique item IDs before insert |
| Sequential recipe API fetch | Simple to write | Full sync takes 2+ minutes; appears frozen; partial runs leave inconsistent state | Never for production — use Http::pool() |

---

## Integration Gotchas

| Integration | Common Mistake | Correct Approach |
|-------------|----------------|------------------|
| Blizzard recipe endpoint | Assume `crafted_item.id` is always present | Treat as absent; resolve output item IDs from Wowhead mapping during seeding |
| Blizzard recipe endpoint | Assume `reagents[]` is always complete | Verify reagent count against Wowhead; flag recipes where API count differs |
| Blizzard item endpoint | Query item ID to determine quality tier | Accept quality cannot be derived from item API; maintain explicit quality ID mapping |
| Commodities price feed | Use raw `unit_price` as sell revenue | Multiply by 0.95 for AH cut before computing profit |
| Commodities price feed | Assume all output item IDs appear in commodities | Gear with stats is realm-AH, never in commodities — flag with `is_commodity = false` |
| Auto-watch seeder | Insert one WatchedItem per recipe-reagent pair | Deduplicate to unique item IDs first; use `firstOrCreate` on `(user_id, blizzard_item_id)` |
| Blizzard OAuth token | Instantiate a new token client in the sync command | Reuse `BlizzardTokenService` — already handles caching and 23-hour refresh |
| Recipe yield quantity | Assume all recipes yield 1 item | Source `yield_quantity` from Wowhead; flag alchemy and cooking for multi-yield review |

---

## Performance Traps

| Trap | Symptoms | Prevention | When It Breaks |
|------|----------|------------|----------------|
| Loading all price snapshots per reagent for profit calculation | Slow profession page load | Use only the latest snapshot per item (`polled_at DESC LIMIT 1`) | At 50+ reagents × months of 15-min snapshots ≈ 140K+ rows per reagent |
| No index on `recipes.profession_id` | Slow profession page filter query | Add index at migration time before any data loads | Noticeable at 500+ recipe rows |
| Eager-loading reagents without the catalog item join | N+1 in the profession detail page | Use `with(['reagents.catalogItem'])` on the Recipe query | First page load with 40+ recipes per profession |
| Sequential recipe sync (no Http::pool) | Seeder runs 2+ minutes; appears frozen | Use Http::pool() with concurrency limit for recipe detail fetch layer | Any full sync run over 50 recipes |
| Inserting watched_items one-by-one in a loop | Queue lock contention with FetchCommodityDataJob | Batch-insert after dedup; run outside queue process during seeding | When seeder runs while queue worker holds SQLite write lock |

---

## Security Mistakes

| Mistake | Risk | Prevention |
|---------|------|------------|
| Logging the Blizzard API token value during recipe sync | Credential leak in application logs | Log token acquisition event at DEBUG level; never log the token string itself — existing `BlizzardTokenService` pattern already handles this correctly |
| Storing recipe data from Wowhead scraping without validation | Malformed item IDs corrupt recipe table | Validate all item IDs are positive integers; validate reagent quantities > 0 before insert; reject rows that fail validation with a logged warning |
| No idempotency guard on sync command | Double-run during a patch creates duplicate recipes | Upsert by `blizzard_recipe_id`; add unique constraint on `recipes.blizzard_recipe_id` |

---

## UX Pitfalls

| Pitfall | User Impact | Better Approach |
|---------|-------------|-----------------|
| Showing profit without noting AH cut | User follows calculator, earns less gold than predicted — erodes trust | Always show "(after 5% AH cut)" label on the revenue or profit figure |
| Displaying "N/A" for all missing-price recipes without distinction | User cannot tell if an item is untracked, not a commodity, or simply not yet priced | Show distinct states: "Tracking — no snapshot yet," "Not a commodity (realm AH)," and actual zero-profit |
| Showing median profit without explaining it | User confused why median differs from T1 or T2 | Lead with per-tier profit (Silver / Gold); show median as a secondary "blended estimate" with a tooltip |
| Sorting by raw gold profit without volume context | High-profit recipes may have near-zero AH volume — unsellable in practice | Add a volume indicator or tooltip noting "profit is theoretical; depends on actual AH volume" |
| No "recipe data last synced" indicator | User sees stale data post-patch and loses confidence without understanding why | Show "Recipe data last synced: X days ago" with a callout if > 14 days old |
| Showing optional reagent cost as zero rather than excluded | User assumes recipe is cheaper than it is | Show "optional reagents excluded from cost" label; never silently zero out a cost |

---

## "Looks Done But Isn't" Checklist

- [ ] **AH cut applied:** Verify profit formula contains `* 0.95` or equivalent — check that a sell_price of 10000 with zero reagent cost yields profit of 9500, not 10000
- [ ] **Quality tier item IDs seeded:** Verify both Silver and Gold item IDs are populated for quality-tiered recipes — check for NULL in `crafted_item_id_silver` or `crafted_item_id_gold`
- [ ] **Yield quantity for consumables:** Verify at least one alchemy or cooking recipe has `yield_quantity > 1` — spot-check a potion recipe profit against CraftSim in-game
- [ ] **Reagent deduplication:** Verify that shared reagents (e.g., Midnight Ore used across Blacksmithing and Jewelcrafting) produce exactly one `watched_items` row — run `WatchedItem::groupBy('blizzard_item_id')->havingRaw('COUNT(*) > 1')->count()` and assert zero
- [ ] **Optional reagents flagged:** Verify recipes with `modified_crafting_slots` have `has_optional_reagents = true` and that the UI shows a disclaimer — check at least one embellishment recipe
- [ ] **Sync command idempotency:** Verify `artisan craft:sync-recipes` can be run twice without creating duplicate recipe rows — run it twice and assert `Recipe::count()` is unchanged on second run
- [ ] **Non-commodity items handled:** Verify gear recipes with `is_commodity = false` show "realm AH — not tracked" rather than blank profit — check at least one Blacksmithing armor recipe
- [ ] **Recipe sync performance:** Verify full sync completes in under 60 seconds — time it against all Midnight professions; sequential implementation will fail this threshold

---

## Recovery Strategies

| Pitfall | Recovery Cost | Recovery Steps |
|---------|---------------|----------------|
| Schema missing per-quality item ID columns | HIGH | New migration to add columns; re-run recipe seeder to populate them; existing profit rows show null until re-seeded |
| AH cut omitted from profit formula | LOW | Fix the formula constant; profit recalculates at display time; no stored profit values in schema |
| Recipe data seeded as migration (not command) | MEDIUM | Extract to an Artisan command class; mark original migration as non-destructive data-only run; document sync command for future patches |
| Duplicate watched_items from reagent seeding | LOW | One-time dedup query to remove duplicate rows; add unique constraint to prevent recurrence; verify snapshot FK integrity |
| Optional reagent quantities silently missing | LOW | Add `has_optional_reagents` flag; UI renders disclaimer; quantities can be hardcoded later from Wowhead |
| Gear recipes showing as "no price" without explanation | LOW | Add `is_commodity` flag to recipe rows via migration; UI logic branches on this field |
| Sequential recipe sync timing out at full scale | MEDIUM | Refactor fetch layer to use `Http::pool()`; no schema change needed; re-run sync to populate any recipes missed by timeout |

---

## Pitfall-to-Phase Mapping

| Pitfall | Prevention Phase | Verification |
|---------|------------------|--------------|
| Recipe endpoint missing crafted item ID | Recipe data seeding (schema + seeder) | Zero rows with NULL `crafted_item_id_silver` after full sync |
| Quality tier item IDs not in item API | Recipe data seeding (schema + seeder) | Both Silver and Gold item IDs populated for quality-tiered recipes |
| Modified slot reagent quantities missing | Profit calculation phase | `has_optional_reagents` flag present; UI disclaimer renders for affected recipes |
| Crafted item yield quantity not in API | Recipe data seeding (schema + seeder) | At least one alchemy recipe has `yield_quantity > 1` |
| Auto-watch reagent dedup failure | Auto-watch integration | No duplicate `blizzard_item_id` rows in `watched_items` after seeding |
| AH cut not applied | Profit calculation phase | Unit test: sell_price=10000, reagent_cost=5000 → profit=4500 |
| Recipe data goes stale after patches | Recipe data seeding (command design) | `artisan craft:sync-recipes` exists, is idempotent, completes without error |
| N+1 serial API call pattern | Recipe data seeding (fetch implementation) | Full sync completes in under 60 seconds using Http::pool() |
| Gear output items are realm-AH not commodities | Recipe data seeding (schema + seeder) | Gear recipes have `is_commodity = false`; UI shows "realm AH — not tracked" |

---

## Sources

- Blizzard Developer Forum — "Dragonflight profession recipes crafted item id" (confirmed `crafted_item` field absent from Dragonflight recipe endpoint onward; community GitHub mapping referenced): https://us.forums.blizzard.com/en/blizzard/t/dragonflight-profession-recipes-crafted-item-id/37444 — MEDIUM confidence
- Blizzard Developer Forum — "Reagent quality missing in game data API WoW retail" (confirmed quality tier not exposed in item API; each tier is a distinct item ID requiring manual mapping): https://us.forums.blizzard.com/en/blizzard/t/reagent-quality-missing-in-game-data-api-wow-retail/49998 — MEDIUM confidence
- Blizzard Developer Forum — "Help with reagent quality from API" (confirmed each reagent quality has its own item ID; must be mapped manually via Wowhead): https://us.forums.blizzard.com/en/blizzard/t/help-with-reagent-quality-from-api/51961 — HIGH confidence
- Blizzard Developer Forum — "Commodities API - Item Rank/Quality" (confirmed quality/rank not exposed throughout Dragonflight; manual mapping required): https://us.forums.blizzard.com/en/blizzard/t/commodities-api-item-rankquality/51895 — HIGH confidence
- Blizzard Developer Forum — "Missing modified_crafting_slots quantity in recipe endpoint" (confirmed `modified_crafting_slots` returns slot types without quantity; unresolved bug): https://us.forums.blizzard.com/en/blizzard/t/missing-modifiedcraftingslots-quantity-in-recipe-endpoint/49170 — MEDIUM confidence
- Blizzard Developer Forum — "[BUG] Professions API" (confirmed `crafted_item.quantity` absent for multi-yield recipes; cooking/alchemy affected): https://us.forums.blizzard.com/en/blizzard/t/bug-professions-api/6234 — MEDIUM confidence
- Blizzard Developer Forum — "Profession Recipe API Incorrect Data" (confirmed reagent list incomplete for some recipe IDs): https://us.forums.blizzard.com/en/blizzard/t/profession-recipe-api-incorrect-data/12071 — MEDIUM confidence
- WoW Professions — Midnight Alchemy Guide (confirmed two quality tiers in Midnight; Bronze removed; Silver/Gold only): https://www.wow-professions.com/midnight/alchemy-guide — MEDIUM confidence
- WoW Professions — Midnight Blacksmithing Guide (confirmed Bronze quality removed; reagent quality simplified to Silver/Gold): https://www.wow-professions.com/midnight/blacksmithing-guide — MEDIUM confidence
- Blizzard Blue Tracker — "Applications, Rate Limits & Throttling" (confirmed credit-based rate limit system; 36K/hr is community-documented cap): https://www.bluetracker.gg/wow/topic/us-en/8796351117-applications-rate-limits-throttling/ — MEDIUM confidence
- Codebase analysis — existing `FetchCommodityDataJob`, `DispatchPriceBatchesJob`, `BlizzardTokenService`, `WatchedItem` model: local source — HIGH confidence

---

*Pitfalls research for: WoW AH Tracker v1.2 — Crafting Profitability (recipe-based profit calculator added to existing price tracker)*
*Researched: 2026-03-05*
