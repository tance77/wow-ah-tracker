# Pitfalls Research

**Domain:** WoW AH Tracker — v1.1 Shuffles (conversion chain profit calculator added to existing price tracker)
**Researched:** 2026-03-04
**Confidence:** HIGH for integration/architectural pitfalls (derived from actual codebase analysis). MEDIUM for WoW-domain yield mechanics (community sources). LOW for Livewire 4-specific edge cases flagged as needing verification.

---

## Critical Pitfalls

### Pitfall 1: Floating-Point Profit Calculations on Copper Prices

**What goes wrong:**
The existing codebase correctly stores prices as `BIGINT` copper integers. The shuffle calculator introduces multi-step math: cost = (input_price * quantity), revenue = (output_price * yield_ratio * quantity). When yield ratios are decimals (e.g., 1.7 dust per prospect on average), developers reach for `float` multiplication. Even one float operation in the chain contaminates all downstream results with IEEE 754 rounding errors. Profit displayed to the user can be off by hundreds of copper — enough to flip a borderline shuffle from profitable to loss.

**Why it happens:**
Yield ratios are inherently fractional (prospecting 5 ore yields on average 1.4 gems). The natural instinct is to store these as `float` and multiply against copper prices. Nothing visually breaks — the error is sub-pixel on display — but the calculation logic is wrong.

**How to avoid:**
Keep all price arithmetic in integer copper until the final display conversion. For fractional yields, store the ratio as two integers: `yield_numerator` and `yield_denominator` (e.g., 7 and 5 for "1.4 per attempt"). Compute profit as: `(input_copper * yield_numerator) / yield_denominator` using integer division, rounding at the final step only. Never multiply copper prices by a PHP `float`. Use `intdiv()` or `(int) round()` at the display boundary, not mid-calculation.

**Warning signs:**
- Yield ratio stored as `FLOAT` or `DECIMAL` in migration
- Any code path that does `$copper_price * $float_ratio` mid-calculation
- Profit numbers with non-zero cent values (e.g., 12,450.37g) when inputs are whole-gold numbers

**Phase to address:**
Shuffles data model phase (migration design). The yield representation choice is irrecoverable once data is stored and referenced.

---

### Pitfall 2: Auto-Watch Orphaning Items When a Shuffle Is Deleted

**What goes wrong:**
The requirement specifies that items added to a shuffle are automatically added to the watchlist. When a user deletes a shuffle, the auto-watched items remain on the watchlist forever — even if the only reason they were watched was the shuffle. The user's watchlist silently accumulates orphan items. Conversely, if auto-watch cleanup IS implemented too aggressively, it removes items that the user also watches independently (for price monitoring unrelated to shuffles), destroying their tracking history.

**Why it happens:**
Auto-watch is implemented as a simple "add if not exists" on shuffle creation, with no tracking of why an item was added to the watch list. Without provenance tracking, cascade deletion cannot distinguish "auto-watched because of Shuffle A" from "manually added by user."

**How to avoid:**
Track auto-watch provenance with a `source` column or a separate pivot table (`shuffle_watched_items`) linking shuffle steps to the WatchedItem they created. On shuffle deletion, only remove auto-watched items that have no other referencing shuffles AND were not independently watched before the shuffle was created. Use a `created_by_shuffle_id` nullable FK on `watched_items`, or a many-to-many join table, rather than relying on heuristics.

**Warning signs:**
- Auto-watch implementation uses `firstOrCreate` without storing which shuffle triggered the creation
- No database relationship between `shuffles` table and `watched_items` table for auto-watch tracking
- Shuffle deletion does not query or update `watched_items`

**Phase to address:**
Shuffles data model and auto-watch implementation phase. Must be designed before any shuffle creation logic is written.

---

### Pitfall 3: Using Live Price Queries Inside the Profit Calculator (N+1 on Multi-Step Chains)

**What goes wrong:**
A conversion chain has N steps, each with input and output items. The profit calculator must fetch the "current price" for each item in the chain. A naive implementation queries the latest `PriceSnapshot` per item inside a loop — one query per item per step. A 3-step chain with 4 distinct items fires 4 separate queries. Wrapped in a Livewire reactive component that recalculates on quantity input, this fires on every keystroke. The page becomes sluggish immediately.

**Why it happens:**
The existing codebase fetches snapshots per-item in the item detail view (acceptable there — single item). Developers copy that pattern into shuffle calculation without considering that a chain touches multiple items simultaneously.

**How to avoid:**
Collect all item IDs needed for the entire chain up front. Execute one query: `PriceSnapshot::whereIn('catalog_item_id', $allItemIds)->latestPerItem()`. Use a subquery or `PARTITION BY` window function to get the latest snapshot per item in a single round trip. Cache the result in a Livewire computed property so it does not re-query on every reactive update — only re-fetch when explicitly refreshed or on page mount.

**Warning signs:**
- `PriceSnapshot::where('catalog_item_id', $id)->latest()->first()` called inside a loop
- Livewire component without a computed property for chain prices
- Query count spikes with chain length (1-step chain: 2 queries; 3-step chain: 6 queries)

**Phase to address:**
Shuffles batch calculator UI phase. Must use a bulk-fetch pattern from day one — retrofitting after the loop pattern is established requires touching both the query logic and the Livewire component wiring.

---

### Pitfall 4: Storing Yield as a Single Average and Losing Min/Max Variance

**What goes wrong:**
WoW shuffles have probabilistic outputs. Prospecting yields 1-3 gems with some probability. Disenchanting yields variable dust/essence counts depending on item level. Storing only the average yield (e.g., `yield_ratio = 1.7`) makes the profit calculator look precise but hides meaningful variance. The user cannot distinguish between "this shuffle always yields 1.7 dust" (fixed, reliable) and "this shuffle yields 1 or 3 dust with equal probability" (high variance, risky at low quantities). Once data is stored as a single average, the variance information is gone.

**Why it happens:**
Averages are the obvious way to express "how much do I get per craft." The schema feels complete with a single yield column. Variance is treated as a UI concern, not a data concern.

**How to avoid:**
Store `yield_min` and `yield_max` (integers) alongside `yield_avg` (stored as a rational — see Pitfall 1). Display a range ("1.4–2.1 per batch") rather than a single number. The batch calculator should show a best-case and worst-case profit band, not just an average. Use `yield_min` and `yield_max` as integers (minimum and maximum items per conversion attempt), and let the UI compute the average display.

**Warning signs:**
- Schema has a single `yield` or `ratio` column with no min/max equivalent
- Batch calculator shows one profit number without a range
- The word "average" appears nowhere in the UI for yield-based outputs

**Phase to address:**
Shuffles data model phase (migration). Adding min/max columns after launch requires a migration AND a UI update AND re-entry of all existing shuffle data.

---

### Pitfall 5: Circular Reference in Multi-Step Chains (A → B → A)

**What goes wrong:**
The chain editor allows a user to define steps where any item can be an output of one step and an input of another. Nothing prevents a user (or a bug) from creating a cycle: Step 1 output is "Draconite Ore", Step 2 input is "Draconite Ore", Step 2 output feeds back into Step 1. The profit calculator enters an infinite loop or blows the stack when traversing the chain graph.

**Why it happens:**
Chain steps stored as ordered records feel linear by design, but if the same item ID appears as both input and output across steps, the logical graph is cyclic. Validation is often skipped because "users wouldn't do that."

**How to avoid:**
Before saving a shuffle, traverse the step graph and detect cycles. Simple approach for the ordered-steps model: collect all `output_item_id` values; assert none appear as `input_item_id` in any earlier step. For a general graph validator, use DFS with a visited set. Block saving with a user-facing error: "Step 3 creates a cycle — Draconite Ore is already an input in Step 1." Add a database constraint where possible (enforce ordering via `step_order`, and validate no backward references at the application layer).

**Warning signs:**
- No cycle-detection logic in shuffle save/update
- Chain traversal uses recursion without a visited-set guard
- UI allows selecting the same item as both input of step N and output of step N+1 without warning

**Phase to address:**
Shuffles CRUD and chain editor phase. Validation must be part of the initial save action, not a post-launch safety net.

---

### Pitfall 6: Blizzard AH Cut Not Factored Into Profit

**What goes wrong:**
The Blizzard Auction House charges a 5% listing fee on successful sales (for commodities). A shuffle that shows "12,000g profit" before the cut actually yields ~11,400g. For high-volume shuffles or thin-margin chains, this 5% gap is the difference between profit and loss. Calculators that omit the AH cut systematically overstate profit, causing users to execute shuffles that lose money in practice.

**Why it happens:**
Developers build the calculator using raw price data (which is what the API returns — the buy price, not the post-sale-fee price). The AH cut is a business rule, not a data field, so it gets forgotten until users notice their actual gold is less than predicted.

**How to avoid:**
Apply a configurable `sell_efficiency` multiplier (default 0.95) to every output item's sell price before computing net profit. Make this explicit in the UI: display "Revenue (after 5% AH cut): X" and "Cost: Y" as separate line items before showing profit. Do not hardcode 0.95 — store it as a named constant (`AH_CUT = 0.05`) so it can be updated if Blizzard changes the fee structure.

**Warning signs:**
- Profit formula uses `output_price * quantity` without any deduction
- No reference to "5%" or "AH cut" anywhere in the calculator code or comments
- Calculator profit figures consistently exceed what users report earning in-game

**Phase to address:**
Shuffles batch calculator UI phase. Must be in the profit formula from day one.

---

### Pitfall 7: Auto-Watch Adding Items Already Watched (Threshold Collision)

**What goes wrong:**
The auto-watch feature adds items used in a shuffle to the watchlist. If the user already watches that item with custom buy/sell thresholds, the auto-watch upsert can overwrite or reset those thresholds. The user loses their carefully configured alerts silently.

**Why it happens:**
`firstOrCreate` or `updateOrCreate` is used for auto-watch without checking whether a manual entry already exists. The "create" half correctly adds new items; the "update" half silently clobbers existing user-configured thresholds.

**How to avoid:**
Auto-watch must use `firstOrCreate` only — never `updateOrCreate`. If a `WatchedItem` already exists for that `blizzard_item_id`, leave it completely unchanged. Log that the item was already watched (no-op). Only create a new `WatchedItem` if none exists, using null thresholds (the shuffle calculator uses live prices, not threshold alerts). Add an integration test that: (1) manually sets a threshold, (2) creates a shuffle using that item, (3) asserts the threshold is unchanged.

**Warning signs:**
- Auto-watch uses `updateOrCreate` instead of `firstOrCreate`
- No test covering "auto-watch does not overwrite existing thresholds"
- Thresholds set to null after a shuffle is added for a previously-watched item

**Phase to address:**
Auto-watch implementation phase. Include the regression test as a mandatory deliverable before the feature is marked complete.

---

## Technical Debt Patterns

| Shortcut | Immediate Benefit | Long-term Cost | When Acceptable |
|----------|-------------------|----------------|-----------------|
| Store yield as a single float average | Simpler schema, one column | Cannot show profit variance band; float precision errors on price math; impossible to reconstruct min/max later | Never — min/max integers cost nothing upfront |
| Hardcode AH cut as 0.95 magic number | Faster to write | Invisible when Blizzard changes fee; can't be configured without a code change | Never — use a named constant at minimum |
| Recalculate profit on every Livewire render without caching prices | No explicit caching logic needed | N+1 queries on every reactive update; sluggish UI at 3+ step chains | Never — computed properties are the Livewire-idiomatic solution |
| Skip cycle detection and trust users | Less validation code | First user to create a cycle causes an infinite loop or 500 error | Never — one validation function prevents a class of bugs permanently |
| Auto-watch with updateOrCreate | Handles create and update in one call | Silently destroys user-configured thresholds | Never — firstOrCreate only for auto-watch |
| Reuse existing WatchedItem model for shuffle items without provenance tracking | No new table | Cannot clean up auto-watched items on shuffle delete; watchlist accumulates orphans | Never — provenance is required for correct cleanup semantics |

---

## Integration Gotchas

These apply specifically to the v1.1 Shuffles feature integrating with the existing v1.0 system.

| Integration Point | Common Mistake | Correct Approach |
|-------------------|----------------|------------------|
| Price fetching for shuffles | Query `PriceSnapshot` per step in a loop, reusing the item-detail page pattern | Bulk-fetch all item prices in one query using `whereIn`; cache in Livewire computed property |
| Auto-watch and existing WatchedItem | `updateOrCreate` that overwrites thresholds | `firstOrCreate` only; treat existing entry as user-owned, never modify it |
| Copper price math with float yields | Multiply `$price_copper * $float_ratio` | Store yield as integer numerator/denominator; compute `intdiv($price * $numerator, $denominator)` |
| Blizzard API prices in shuffle context | Use `min_price` as cost basis for inputs | Use `median_price` as cost basis (same as dashboard); min_price distorts shuffle profitability |
| Livewire reactive quantity input | Re-query database on every quantity change | Separate price-fetch (on mount / manual refresh) from profit-calculation (pure PHP math on cached prices) |
| Shuffle deletion and watched items | Delete shuffle row, leave watched_items untouched | Check provenance; remove auto-created WatchedItems that are exclusively shuffle-owned |

---

## Performance Traps

| Trap | Symptoms | Prevention | When It Breaks |
|------|----------|------------|----------------|
| Per-item price queries inside chain traversal | Sluggish shuffle page; query count = 2x chain step count | Bulk `whereIn` fetch for all item IDs in chain; cache in Livewire computed property | Immediately at 3+ step chains with reactive quantity input |
| Eager-loading full PriceSnapshot history for shuffle items | Memory spike on shuffle load; slow initial page render | Only load the single latest snapshot per item (one subquery), not the full history | After 30+ days of data accumulation — even for a few items, this is thousands of rows |
| No index on `(catalog_item_id, polled_at)` for "latest snapshot" query | Shuffle price refresh slow even for 2-item chains | Confirm composite index exists from v1.0 schema; query planner uses it for ORDER BY + LIMIT 1 | At 10K+ snapshot rows per item (~3 months of data) |
| Profit recalculation on every Livewire property update | Input lag when typing quantity; excess server round-trips | Use `wire:model.lazy` or `wire:model.blur` for quantity input to debounce recalculation | Immediately — every keystroke fires a server request without debounce |

---

## Security Mistakes

These are specific to the shuffles feature. General security (auth, .env) is covered in v1.0 pitfalls.

| Mistake | Risk | Prevention |
|---------|------|------------|
| No authorization check on shuffle CRUD | Any authenticated user modifies any shuffle (not relevant now since single-user, but bad habit) | Scope all shuffle queries to `auth()->user()` from day one — `Shuffle::where('user_id', auth()->id())` on every query |
| Accepting unvalidated `step_order` from form input | User can POST arbitrary step orderings, causing chain traversal to misbehave | Enforce server-side ordering — recompute step order from position in the submitted steps array, never trust client-supplied order integers |
| Storing item IDs from user input without verifying they exist in catalog | Shuffle references non-existent item; calculator silently returns zero profit | Validate each `blizzard_item_id` exists in `catalog_items` before saving a shuffle step |

---

## UX Pitfalls

| Pitfall | User Impact | Better Approach |
|---------|-------------|-----------------|
| Single profit number without variance | User doesn't know if a 10-batch trial will be profitable or a loss | Show profit range (best case with max yield, worst case with min yield) alongside average |
| Showing profit without labeling AH cut | User expects 12,000g, gets 11,400g, blames the tool | Label "after AH cut" explicitly on revenue line; show gross and net separately |
| No price freshness indicator on shuffle calculator | User runs a shuffle based on prices from 90 minutes ago | Show "Prices as of X minutes ago" with a manual refresh button on the shuffle calculator page |
| Auto-watch silently adding items to watchlist | User confused why watchlist grew after adding a shuffle | Show a notification: "3 items added to watchlist for price tracking" when a shuffle is saved |
| Deleting a shuffle silently removes items from watchlist | User loses price history for items they wanted to keep tracking | Show confirmation: "This will remove [items] from your watchlist. Keep them?" |
| Profit displayed in raw copper | "450000 copper profit" is meaningless | Always display profit in gold with 2 decimal places: "+45.00g" with color coding (green/red) |

---

## "Looks Done But Isn't" Checklist

- [ ] **AH cut applied:** Profit formula deducts 5% from sell-side revenue — verify by checking a known shuffle against hand-calculated expected profit
- [ ] **Yield stored as min/max/avg:** Migration has `yield_min`, `yield_max` columns (not a single float) — verify with `DESCRIBE shuffle_steps`
- [ ] **Copper math stays integer:** No float multiplication of copper values in profit calculation — verify by searching for `$.*price.*\*.*0\.` patterns in shuffle calculator code
- [ ] **Cycle detection runs on save:** Creating a self-referencing chain returns a validation error — verify with a test that saves Step 1 output = Step 2 input = Step 1 input
- [ ] **Auto-watch uses firstOrCreate:** Existing WatchedItem thresholds are unchanged after adding a shuffle — verify with integration test
- [ ] **Provenance tracked:** Deleting a shuffle removes only its auto-created WatchedItems — verify an item watched before the shuffle remains on watchlist after shuffle deletion
- [ ] **Bulk price fetch:** Shuffle calculator uses one query for all item prices, not N queries — verify with query log or Telescope during a 3-step chain load
- [ ] **Median price used as cost basis:** Shuffle profit uses `median_price`, not `min_price`, for input cost — verify by checking which snapshot column is referenced in calculator logic
- [ ] **Quantity input debounced:** Changing the batch quantity does not fire a server request on every keystroke — verify with browser network tab

---

## Recovery Strategies

| Pitfall | Recovery Cost | Recovery Steps |
|---------|---------------|----------------|
| Yield stored as float (data already entered) | MEDIUM | Add `yield_min`/`yield_max` integer columns via migration; backfill from `yield_avg` as best estimate (both min=max=round(avg)); prompt user to correct ranges manually |
| Float used in price math (calculation bug) | LOW | Pure code fix — no stored data affected; update profit formula to integer arithmetic; add regression test |
| Auto-watch overwrote thresholds | MEDIUM | No automatic recovery — thresholds are gone; surface a "your thresholds may have been reset" notice; add restoration UI where user can re-enter |
| Cycle created, calculator errored | LOW | Add cycle detection to save; clean up the offending shuffle step; calculator is purely computational so no stored results to fix |
| AH cut missing from existing profit history | LOW | Pure display/calculation bug — no stored profit values exist (profit is calculated live); fix formula and profit numbers correct on next page load |
| Orphan WatchedItems after shuffle deletions | MEDIUM | Write a one-time cleanup query to remove WatchedItems where `created_by_shuffle_id IS NOT NULL` and the shuffle no longer exists; add provenance tracking going forward |

---

## Pitfall-to-Phase Mapping

| Pitfall | Prevention Phase | Verification |
|---------|------------------|--------------|
| Float price math in profit calculator | Shuffles data model (migration design) | No `FLOAT`/`DECIMAL` columns for price or yield; profit formula uses integer arithmetic only |
| Auto-watch orphaning / threshold collision | Auto-watch implementation | Integration test: add shuffle, verify existing threshold unchanged; delete shuffle, verify pre-existing items remain |
| N+1 price queries in calculator | Shuffles batch calculator UI | Query log shows single `whereIn` fetch per calculator load, not per-step |
| Single yield average (no variance) | Shuffles data model (migration design) | Schema has `yield_min` and `yield_max` integer columns alongside average representation |
| Circular chain references | Shuffles CRUD / chain editor | Validation test: cyclic chain save returns error, does not insert |
| AH cut omitted from profit | Shuffles batch calculator UI | Hand-verify: 100 units × 1,000g output price = 95,000g revenue, not 100,000g |
| min_price used instead of median_price | Shuffles batch calculator UI | Code review confirms `median_price` column used for all input cost calculations |
| Quantity input triggering per-keystroke queries | Shuffles batch calculator UI | Network tab shows requests fire on blur/submit, not on every character |

---

## Sources

- Codebase analysis: `/Users/lancethompson/Github/wow-ah-tracker/app/Models/` — confirmed BIGINT copper storage pattern, WatchedItem/CatalogItem/PriceSnapshot relationships
- Codebase analysis: `/Users/lancethompson/Github/wow-ah-tracker/.planning/PROJECT.md` — confirmed auto-watch requirement, batch calculator requirement, min/max yield requirement
- [Livewire Reactive Properties — Official Docs](https://livewire.laravel.com/docs/4.x/attribute-reactive) — confirmed reactive props send full data on every parent update; performance implications
- [Livewire Nesting — Official Docs](https://livewire.laravel.com/docs/4.x/nesting) — confirmed max 2-level nesting recommendation to avoid DOM diffing issues
- [Livewire Best Practices — michael-rubel/livewire-best-practices](https://github.com/michael-rubel/livewire-best-practices) — confirmed large Eloquent model passing pitfall, non-deferred model binding as query killer
- [The Lazy Goldmaker — MoP Beta Enchanting Shuffle Test](https://thelazygoldmaker.com/i-tested-the-enchanting-shuffles-on-the-mop-beta-so-you-dont-have-to) — confirmed variable yield output (e.g., 1.7 dust per bracer average), confirmed variance exists in shuffle outputs
- [Wowpedia — Prospecting](https://wowpedia.fandom.com/wiki/Prospecting) — confirmed 5-ore input, probabilistic outputs, crowdsourced yield averages from Wowhead
- [Blizzard AH Cut — Warmane Forum thread](https://forum.warmane.com/showthread.php?t=318786) — confirmed 5% AH cut on commodity sales (MEDIUM confidence — community source, consistent with official AH docs)
- [WoW Money — WoWWiki Archive](https://wowwiki-archive.fandom.com/wiki/Money) — confirmed copper integer nature; pre-4.0 signed 32-bit overflow as historical validation of integer-only approach
- [Ten Common Database Design Mistakes — Simple Talk / Redgate](https://www.red-gate.com/simple-talk/databases/sql-server/database-administration-sql-server/ten-common-database-design-mistakes/) — confirmed mixing derived metrics with raw values as design mistake; view-chaining breaks on upstream errors
- [Laravel Eloquent Polymorphic Relationships](https://laravel.com/docs/12.x/eloquent-relationships) — confirmed polymorphic relations do not enforce foreign key constraints at DB level; dedicated tables preferred for data integrity in conversion step chains

---
*Pitfalls research for: WoW AH Tracker v1.1 Shuffles — conversion chain profit tracking added to existing price tracker*
*Researched: 2026-03-04*
