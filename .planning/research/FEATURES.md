# Feature Research

**Domain:** WoW Auction House commodity price tracker (personal tool)
**Researched:** 2026-03-01 (v1.0) / 2026-03-04 (v1.1 Shuffles milestone appended)
**Confidence:** MEDIUM — based on live competitor analysis of TSM, Booty Bay Broker, WoW Price Hub, Undermine Exchange, Oribos Exchange, Saddlebag Exchange. No official "user expectations" survey; judgments drawn from feature prevalence across multiple tools.

---

## Feature Landscape

### Table Stakes (Users Expect These)

Features present in every WoW AH tracker. Absence makes the tool feel broken or useless.

| Feature | Why Expected | Complexity | Notes |
|---------|--------------|------------|-------|
| Current price display (min/avg/median) | Every tracker shows at least min price. Min price alone is misleading; median/avg adds signal | LOW | Commodities API returns quantity+unitPrice per listing — need to compute median and weighted avg from raw listings array |
| Price history chart (line over time) | WoW Price Hub, Booty Bay Broker, WoWAuctions, TSM all show time-series charts. This is the core value | MEDIUM | Store snapshot per polling cycle; chart with Chart.js or similar |
| Multi-metric view per item | All trackers show min, avg, and volume together. Single-metric view feels incomplete | LOW | Display min price, avg price, volume, and listing count per snapshot |
| Item search / lookup | Even this personal tool will want to find items by name, not just ID | LOW | Since scope is 6-7 curated items, a simple dropdown/list suffices over full search |
| Polling on schedule | Every tracker fetches fresh data regularly. Stale data defeats the purpose | MEDIUM | Laravel scheduler + queue worker already planned; 15-min interval matches AH update cadence |
| Watched item management (add/remove) | Even personal tools need to update the item list without code changes | LOW | Admin UI with simple CRUD; ~6-7 items currently |
| Data persistence / history | Price without history is just a number. Trend is the value | MEDIUM | Store every polling snapshot per item; grows ~6-7 rows per 15-min cycle |

### Differentiators (Competitive Advantage)

Features that go beyond baseline tracking and deliver the "spot opportunities before the market corrects" value this tool promises.

| Feature | Value Proposition | Complexity | Notes |
|---------|-------------------|------------|-------|
| Visual buy/sell signal indicators | TSM shows heatmaps (red/green); Saddlebag shows spike alerts. Visual flags make action obvious without analysis | MEDIUM | Compare current price to rolling N-day average; flag if >X% below avg (buy) or above avg (sell). Thresholds configurable per item |
| Percent-from-average label on chart | Booty Bay and WoW Price Hub show raw price; knowing "15% below 7-day avg" is more actionable than the raw gold amount | LOW | Compute at render time from stored history |
| Volume / supply tracking | All listings are quantity-weighted. Low volume + low price = scarcity spike incoming. Saddlebag explicitly tracks "Commodity Shortages BEFORE they happen" | MEDIUM | Store total_quantity alongside price metrics per snapshot. Chart quantity alongside price |
| Configurable spike/dip threshold per item | Saddlebag alerts when price crosses a user-set threshold. Different materials have different volatility baselines | LOW | Simple per-item config fields: buy_threshold (% below avg), sell_threshold (% above avg). Store in watched_items table |
| Multi-timeframe chart toggle | WoW Price Hub and WoWAuctions let users switch between 24h, 7d, 30d views. Essential for separating noise from trend | LOW | Filter query by date range; pass range param to chart component |
| Dashboard summary card per item | TSM has a dashboard overview. Seeing all 6-7 items at a glance with current price + trend arrow is more useful than navigating per-item | MEDIUM | Overview grid: item name, current price, 7d trend direction, buy/sell signal badge |

### Anti-Features (Commonly Requested, Often Problematic)

Features that seem valuable but add complexity disproportionate to this tool's scope and purpose.

| Feature | Why Requested | Why Problematic | Alternative |
|---------|---------------|-----------------|-------------|
| Email / Discord / push notifications for price events | Users want to act immediately when a dip happens; they don't want to stare at the dashboard | Requires background job that evaluates thresholds every cycle, external service integration (SMTP/Discord webhook), notification deduplication logic. Out-of-scope for v1 per PROJECT.md | Build accurate visual signals on dashboard first; if dashboard-watching becomes the bottleneck, add Discord webhook in v1.x using a simple HTTP POST — no full notification system needed |
| Crafting profit calculator | TSM's killer feature; very popular in the community | Requires tracking both material cost AND crafted item sell price + volume. Doubles the data model complexity. Out of scope per PROJECT.md (crafting insights are downstream value, not the core) | The price data this tool collects naturally enables this later — design the schema to support it without building it now |
| Cross-realm / cross-region comparison | Saddlebag Exchange's differentiator for server transfer arbitrage | Commodities are region-wide (US Retail) by API design — there is no realm difference for commodities. Adding Classic or EU is a separate data pipeline | Explicitly document in UI that data is US Retail region-wide |
| Full AH item database (all items, not curated) | Undermine Exchange and WoW Price Hub track 100k+ items | Massive storage, slow queries without careful indexing, and unclear value for a personal crafting material tool. The value is the curation, not the breadth | Keep the watched-item model; make adding items easy via admin UI |
| Real-time WebSocket price streaming | Sounds impressive; some tools show "live" data | Blizzard API updates commodities data roughly every 15-60 minutes. Polling more often returns the same data and wastes rate limit budget | 15-minute polling is the correct cadence; websocket adds latency theater without real freshness |
| Mobile app / native push | Players want price alerts while away from computer | Scope is explicitly web-only per PROJECT.md. Native push requires app store submission, separate codebase, certificate management | Responsive Tailwind layout ensures mobile browser works reasonably |

---

## Feature Dependencies

```
[Blizzard API poller (scheduled job)]
    └──required by──> [Price history storage]
                          └──required by──> [Price history chart]
                          └──required by──> [Buy/sell signal indicators]
                          └──required by──> [Percent-from-average label]
                          └──required by──> [Volume / supply tracking chart]

[Watched item management (admin CRUD)]
    └──required by──> [Blizzard API poller] (needs item IDs to filter)
    └──required by──> [Dashboard summary cards]

[Simple auth (single user login)]
    └──required by──> [Admin UI for item management]
    └──required by──> [Dashboard] (protects personal data)

[Multi-metric snapshot storage (min/avg/median/volume)]
    └──enables──> [Buy/sell signal indicators]
    └──enables──> [Multi-timeframe chart toggle]
    └──enables──> [Configurable threshold per item] (thresholds need avg to compare against)

[Configurable spike/dip threshold per item]
    └──enhances──> [Buy/sell signal indicators]
```

### Dependency Notes

- **Poller requires Watched item management:** The job must know which item IDs to look for in the commodities response. A hard-coded list works for v1 bootstrap, but admin UI is needed before thresholds are configurable.
- **Charts require history storage:** You cannot chart what was not stored. Schema must store every snapshot from day one, not just the latest value.
- **Buy/sell signals require rolling average:** Must compute N-day average from stored snapshots. Signals without historical context are meaningless.
- **Volume tracking is same complexity as price tracking:** The commodities response provides total quantity naturally — store it with zero extra API calls. Skipping it now means a data gap impossible to backfill.

---

## MVP Definition

### Launch With (v1)

Minimum viable product — validates whether price-over-time visibility changes buying/selling behavior.

- [ ] Blizzard API poller (15-min schedule) — without this, nothing else works
- [ ] Price history storage (min, avg, median, volume per snapshot) — store volume from day one; can't backfill
- [ ] Watched item management (admin UI, simple CRUD) — avoid hard-coding item IDs
- [ ] Single-user auth (simple login) — protects admin UI
- [ ] Dashboard with price history charts (line, 7-day default) — core value delivery
- [ ] Multi-timeframe toggle (24h / 7d / 30d) — essential for separating noise from trend
- [ ] Buy/sell signal indicators (% from rolling average, configurable threshold) — the "spot the opportunity" feature

### Add After Validation (v1.x)

Add when the dashboard is being used and a specific limitation emerges.

- [ ] Volume / supply trend chart overlay — add when price signals feel noisy without supply context
- [ ] Per-item threshold configuration in UI — add when defaults feel wrong for specific items
- [ ] Discord webhook alert for threshold breach — add when dashboard-checking becomes the bottleneck

### Future Consideration (v2+)

Defer until clear evidence this tool is the bottleneck.

- [ ] Crafting profit calculator — requires tracking crafted item sell prices separately
- [ ] Additional item categories (gear, pets, mounts) — different data shape, different API endpoints
- [ ] Multi-user support — requires auth overhaul, data isolation

---

## Feature Prioritization Matrix

| Feature | User Value | Implementation Cost | Priority |
|---------|------------|---------------------|----------|
| Blizzard API poller (scheduled) | HIGH | MEDIUM | P1 |
| Price history storage (min/avg/median/volume) | HIGH | LOW | P1 |
| Watched item admin CRUD | HIGH | LOW | P1 |
| Single-user auth | HIGH | LOW | P1 |
| Price history line chart | HIGH | MEDIUM | P1 |
| Buy/sell signal indicator (% from avg) | HIGH | LOW | P1 |
| Multi-timeframe chart toggle | MEDIUM | LOW | P1 |
| Dashboard summary card per item | MEDIUM | LOW | P1 |
| Volume / supply chart overlay | MEDIUM | LOW | P2 |
| Per-item threshold config in UI | MEDIUM | LOW | P2 |
| Percent-from-average label on chart | MEDIUM | LOW | P2 |
| Discord webhook alerts | MEDIUM | MEDIUM | P2 |
| Crafting profit calculator | HIGH | HIGH | P3 |
| Email notifications | LOW | HIGH | P3 |
| Full item database (all items) | LOW | HIGH | P3 |

**Priority key:**
- P1: Must have for launch
- P2: Should have, add when possible
- P3: Nice to have, future consideration

---

## Competitor Feature Analysis

| Feature | TSM | Booty Bay Broker | Undermine Exchange / Oribos | Saddlebag Exchange | Our Approach |
|---------|-----|------------------|-----------------------------|--------------------|--------------|
| Price history chart | Yes (heatmap + line) | Yes (line chart) | Yes (OHLC + line) | Yes (weekly view) | Line chart, configurable timeframe |
| Volume / quantity tracking | Yes | Yes | Yes | Yes (shortage prediction) | Store volume per snapshot from day 1 |
| Buy/sell signals | Yes (heatmap color) | Watchlist only | Not explicit | Yes (price spike alerts) | % from rolling average + visual badge |
| Configurable thresholds | Yes (complex operations) | No | No | Yes (user-set price targets) | Simple per-item threshold config |
| Multi-region support | Yes (NA/EU/KR/TW) | Yes (NA/EU) | Yes | Yes | US Retail only (out of scope) |
| Item watchlist / curation | Yes (groups) | Yes | No (all items) | Yes | Admin-managed item list |
| Notifications / alerts | No (in-app only) | No | No | Yes (price spike alerts) | V1.x: Discord webhook |
| Crafting profit calc | Yes (core feature) | No | No | Partial | Out of scope v1 |
| Auth / personal dashboard | Dashboard (account) | No | No | Account-based | Single-user simple auth |
| Real-time updates | Yes (TSM scan) | 1-2hr polling | Semi-weekly updates | Varies | 15-min polling (matches AH cadence) |

---

## Sources

- [TradeSkillMaster](https://tradeskillmaster.com/) — dashboard and feature overview (MEDIUM confidence — marketing page, not exhaustive)
- [Booty Bay Broker](https://bootybaybroker.com/) — feature list from live page (MEDIUM confidence)
- [WoW Price Hub](https://wowpricehub.com/) — features from live page (MEDIUM confidence)
- [Saddlebag Exchange](https://saddlebagexchange.com/wow) — feature list from live page (MEDIUM confidence)
- [Oribos Exchange on CurseForge](https://www.curseforge.com/wow/addons/oribos-exchange) — addon description (MEDIUM confidence)
- [Undermine Exchange](https://undermine.exchange/) — maintenance mode at time of research; features inferred from search results (LOW confidence)
- [WoWAuctions.net](https://www.wowauctions.net/) — feature list from search result snippet (LOW confidence — not directly fetched)
- [Grahran's WoW Gold — Undermine Exchange](https://grahranswowgold.com/undermine-exchange/) — historical feature descriptions (LOW confidence — CSS-heavy page, content not extracted)
- [GitHub: AHNotifier](https://github.com/ninthwalker/AHNotifier) — confirms notification pattern exists in ecosystem (MEDIUM confidence)

---

*Feature research for: WoW AH Commodity Price Tracker*
*Researched: 2026-03-01*

---
---

# Feature Research — v1.1 Shuffles Milestone

**Domain:** WoW item conversion chain ("shuffle") profit tracker — added to existing AH Tracker app
**Researched:** 2026-03-04
**Confidence:** HIGH for core mechanics (community shuffle patterns are well-documented across guides, spreadsheets, and addons); MEDIUM for differentiators (gap analysis based on absence of dedicated web tools in the ecosystem)

---

## Background: What Is a WoW Shuffle?

A "shuffle" is a multi-step material conversion chain. Players buy cheap raw inputs from the AH, process them through one or more crafting steps, and sell the outputs. Common examples:

- **Ore prospecting shuffle:** Buy Ghost Iron Ore → Prospect (5 ore = ~1-2 gems average) → Cut gems → Sell on AH
- **Enchanting shuffle:** Buy cheap cloth → Craft armor piece → Disenchant → Sell enchanting mats
- **Herb milling shuffle:** Buy BFA herbs → Mill (5 herbs = pigments) → Craft inks → Sell or craft glyphs
- **Transmute shuffle:** Buy cheap metal → Transmute → Sell rare metal

Profitability: `(sum of output item values × 0.95 AH cut) - sum of input costs > 0`

The WoW community runs these via Google Sheets with TSM data exports. No dedicated web app with saved chains and live prices exists publicly — this is the gap the Shuffles feature fills.

---

## Feature Landscape — Shuffles

### Table Stakes (Users Expect These)

Features a shuffle section needs to feel complete and useful. Missing any of these means the section should not ship.

| Feature | Why Expected | Complexity | Notes |
|---------|--------------|------------|-------|
| Save named conversion chains | A shuffle you run repeatedly must persist. Re-entering ratios each visit defeats the purpose | LOW | DB model: Shuffle (name) hasMany ShuffleStep (input_item, output_item, ratio, sort_order) |
| Multi-step chain definition (A → B → C) | Real shuffles have 2-4 steps: buy ore → prospect → cut gems → AH, or craft → disenchant | MEDIUM | Each step has one input and one or more outputs with a conversion ratio per output |
| Fixed yield ratio per step | Every shuffle has a known average yield (e.g., 5 ore → 1.4 uncommon gems average) | LOW | Stored as decimal (e.g., 0.28 gems per ore). User enters from personal data or community averages |
| Batch calculator: enter input quantity, see per-step outputs | Spreadsheets universally implement this. "I have 200 stacks of ore, what do I get and is it worth it?" | LOW | Multiplication of input qty × ratio per step; cascades through chain |
| Profit summary: total cost in, total value out, net profit | The single go/no-go number. Without it there is no point running the calculator | LOW | Net = (output value sum × 0.95) - input cost total. Uses live AH prices from existing PriceSnapshot |
| Per-step cost and value breakdown | Users need to see where value is created — which step generates or destroys margin | LOW | Line item per step: input cost consumed, output value produced, step margin |
| Live price integration | Using static prices makes this a spreadsheet, not an app. Existing polling infrastructure makes this free | LOW | Use latest PriceSnapshot.median_price for each item; staleness flag if snapshot > 1 hour old |
| Auto-watch items in a shuffle | Items in a chain must have WatchedItem records or prices will never be polled | MEDIUM | On shuffle save/update: for each referenced CatalogItem, ensure WatchedItem exists (create if not) |
| Shuffles navigation section / index page | Users run multiple shuffles. They need a list to pick from | LOW | Index route under /shuffles showing all saved shuffles with current profitability status |

### Differentiators (Competitive Advantage)

Features that go beyond what spreadsheets offer, making the web app worth maintaining.

| Feature | Value Proposition | Complexity | Notes |
|---------|-------------------|------------|-------|
| Profitability status badge per shuffle (green/red) | Instant visual signal matching the existing dashboard buy/sell indicator UX. No mental math required | LOW | Computed at render: net profit > 0 → "Profitable" (gold/green), else "Unprofitable" (red). Optional configurable margin threshold |
| Break-even input price display | "What is the max I can pay for ore before this shuffle loses money?" — the most common real-world question shuffle runners ask | MEDIUM | Reverse-calculate: max_input_price = (output_value × 0.95) / input_quantity_per_unit. Derived from same formula |
| Price staleness warning | Surfaces when the latest snapshot is old so user knows profit calc confidence | LOW | Flag if PriceSnapshot.polled_at > 1 hour ago. Existing polled_at field covers this |
| Yield range (min/max per step) | Prospecting and milling are probabilistic. Showing best-case / worst-case profit conveys risk without simulation | MEDIUM | Add min_yield and max_yield columns alongside ratio on ShuffleStep; display best/worst profit rows in calculator |
| Per-output "sell on AH vs vendor" toggle | Some outputs are only worth vendoring (e.g., uncommon gems below vendor price threshold). AH cut should not be applied to vendored items | MEDIUM | Per ShuffleOutput: sell_method enum (ah / vendor), vendor_price_copper field. Omit AH cut for vendor items |

### Anti-Features (Commonly Requested, Often Problematic)

| Feature | Why Requested | Why Problematic | Alternative |
|---------|---------------|-----------------|-------------|
| Full recipe-based crafting calculator (nested reagent trees) | Natural extension of shuffles; TSM and CraftSim do this | Scope explosion. WoW recipes have nested sub-recipes, quality tiers, profession specialization bonuses, Inspiration procs. CraftSim is a very large, complex addon specifically for this. PROJECT.md explicitly defers this (ADVN-01) | Shuffles are conversion chains with known average ratios only. No sub-recipe recursion. The line: a shuffle step has one input and one or more outputs with a user-entered ratio. |
| Historical profitability chart over time | Shows how shuffle profit has trended as input/output prices shifted | Requires storing calculated profit results over time, not just prices. New data type and storage overhead. | Existing per-item price trend charts on the dashboard already show input/output price history. If ore got 20% cheaper over 7 days, that's visible there. |
| Automated profit alerts when a shuffle becomes profitable | Get notified immediately when the margin turns positive | Email/push infrastructure not present. The 15-min polling cadence means at worst 15 minutes of lag without alerts. | Shuffle index page shows live profitability status on each visit. Bookmark the shuffles page. |
| Monte Carlo simulation for probabilistic yields | Research shows prospecting variance requires thousands of samples to converge — correct simulation matters | Far exceeds the complexity warranted for a personal tool. Users don't need statistical rigor, they need a practical answer. | Use average yield with min/max range to communicate uncertainty without simulation |
| Multi-region or realm-specific pricing | Different servers have different prices; shuffle profitability varies | App is hardcoded US commodities (region-wide by Blizzard API design). Realm-specific prices are a different API endpoint and data pipeline. PROJECT.md excludes this. | Out of scope by constraint |

---

## Feature Dependencies — Shuffles

```
[Shuffle CRUD — save named chains with steps]
    └──requires──> [CatalogItem exists for each referenced item]
    └──requires──> [ShuffleStep model — input, output(s), ratio, sort_order]

[Auto-watch items on shuffle save]
    └──requires──> [Shuffle CRUD]
    └──creates──>  [WatchedItem records]
                       └──enables──> [PriceSnapshot polling by scheduler]

[Batch profit calculator]
    └──requires──> [Shuffle CRUD — saved chain with steps]
    └──requires──> [PriceSnapshot data — at least one snapshot per chain item]
    └──requires──> [Auto-watch — ensures prices are being polled]

[Profit summary (total cost in, value out, net)]
    └──requires──> [Batch profit calculator logic]
    └──requires──> [5% AH cut applied to output values]

[Profitability status badge]
    └──requires──> [Profit summary — net profit value]

[Break-even input price]
    └──requires──> [Profit summary — output value total]
    └──requires──> [Known input quantity per step]

[Yield range (min/max)]
    └──enhances──> [Batch profit calculator — adds best/worst case rows]
    └──requires──> [min_yield, max_yield columns on ShuffleStep]

[Per-output vendor vs AH toggle]
    └──enhances──> [Profit summary — corrects AH cut for vendored items]
    └──requires──> [vendor_price_copper field on ShuffleOutput or ShuffleStep]
```

### Dependency Notes

- **Auto-watch must fire on shuffle save, not lazily:** If a user saves a shuffle but prices don't exist yet, the calculator shows "unknown price" and is useless. Creating WatchedItem records immediately on shuffle save ensures prices arrive within 15 minutes.
- **Break-even is free once profit summary exists:** It's the inverse of the same formula. Should be implemented in the same pass.
- **Yield range is optional at v1:** The batch calculator works with a single average ratio. Min/max adds a second display row. Defer to v1.x unless stakeholder wants it at launch.
- **No dependency on existing `profession` grouping:** WatchedItems for shuffle inputs may or may not have a profession set. Auto-watch should set profession = null or derive it from the shuffle context.

---

## MVP Definition — Shuffles (v1.1)

This is a subsequent milestone on an existing app. MVP means minimum to ship the Shuffles section.

### Launch With (v1.1)

- [ ] Shuffle index page — list of saved shuffles with name and current profitability badge
- [ ] Shuffle create/edit form — name the chain, add/reorder steps (input item, output item(s), yield ratio)
- [ ] Shuffle delete
- [ ] Batch calculator — input quantity field, per-step yield breakdown, profit summary
- [ ] Profit summary — total cost in, total value out (with 5% AH cut), net profit
- [ ] Auto-watch — create WatchedItem records for all chain items on shuffle save
- [ ] Profitability status badge (profitable / unprofitable at current prices)
- [ ] Price staleness warning (flag if snapshot > 1 hour old)

### Add After Validation (v1.x)

- [ ] Break-even input price — natural follow-on; implement once calculator is validated
- [ ] Yield range (min/max per step) — adds risk visibility; defer until average ratios are in use and variance matters to the user
- [ ] Per-output vendor vs AH toggle — useful when some outputs (low-quality gems) are worth more at vendor than AH

### Future Consideration (v2+)

- [ ] Historical profitability trend (requires storing calc results over time)
- [ ] Full recipe-based crafting calculator (PROJECT.md deferred as ADVN-01 — CraftSim territory)

---

## Feature Prioritization Matrix — Shuffles

| Feature | User Value | Implementation Cost | Priority |
|---------|------------|---------------------|----------|
| Save named chains (Shuffle CRUD) | HIGH | LOW | P1 |
| Multi-step chain definition | HIGH | MEDIUM | P1 |
| Batch profit calculator | HIGH | LOW | P1 |
| Profit summary with AH cut | HIGH | LOW | P1 |
| Auto-watch items | HIGH | MEDIUM | P1 |
| Per-step cost/value breakdown | HIGH | LOW | P1 |
| Profitability status badge | HIGH | LOW | P1 |
| Price staleness warning | MEDIUM | LOW | P1 |
| Shuffles index page | HIGH | LOW | P1 |
| Break-even input price | MEDIUM | MEDIUM | P2 |
| Yield range (min/max) | MEDIUM | LOW | P2 |
| Per-output vendor vs AH toggle | MEDIUM | LOW | P2 |
| Historical profitability chart | LOW | HIGH | P3 |
| Full recipe calculator | LOW | HIGH | P3 (out of scope) |

**Priority key:**
- P1: Must have for v1.1 launch
- P2: Should have, add when possible
- P3: Nice to have / deferred

---

## Integration with Existing App

The Shuffles feature is additive — it connects to existing infrastructure without modifying it.

| Existing Piece | How Shuffles Use It |
|----------------|---------------------|
| `CatalogItem` model | Input and output items are CatalogItems. Users select from catalog when building a chain |
| `WatchedItem` model | Auto-watch creates WatchedItem records for chain items so prices get polled |
| `PriceSnapshot` model | Latest snapshot's median_price drives the live cost and value calculations in the batch calculator |
| Blizzard API poller (scheduler) | Already running every 15 min — newly auto-watched items get prices within one poll cycle |
| Auth (Breeze, single-user) | No changes — Shuffles live behind existing auth middleware |
| Tailwind CSS v4 / WoW dark theme | Same design tokens: gold/amber for profitable, red for unprofitable; consistent with buy/sell signal colors |
| Navigation layout | Add "Shuffles" nav entry alongside dashboard and watchlist |

**AH Cut Note:** The existing price dashboard does not model AH cut (it shows raw prices). The shuffle calculator is the first place AH cut (5%) is applied. Commodity AH has no deposit fee — only the 5% sale cut matters here.

---

## Competitor / Ecosystem Feature Analysis — Shuffles

No dedicated web app for shuffle profit tracking with live prices and saved chains exists publicly. Community tools:

| Feature | Google Sheets (community) | CraftSim (in-game addon) | ProspectMate (in-game addon) | This App |
|---------|--------------------------|--------------------------|-------------------------------|----------|
| Multi-step chains | Manual column setup per shuffle | Recipe-based, not shuffle-focused | Tracks yields during active play | Named, saved, reusable chains |
| Live prices | TSM API export (manual, periodic) | TSM in-game data (real-time in-game) | Requires Auctionator addon | Blizzard API 15-min polling, web UI |
| Batch calculator | Yes — core spreadsheet feature | Crafting queue, not batch input | Per-session yield only | Yes, with input quantity field |
| Saved/named shuffles | Copy a new sheet per shuffle | Not applicable | Not applicable | Yes, persistent DB |
| Break-even price | Manual formula | No | No | Planned P2 |
| Yield range | Some sheets include it | Not applicable | Tracks actuals over time | Planned P2 |
| Web-based (no addon required) | Requires Google account | No (in-game) | No (in-game) | Yes |
| Profitability badge | Cell color coding | No | No | Yes |

**Gap this feature fills:** Persistent, web-based, live-price-integrated shuffle tracker with saved chains. None of the existing tools combine all three.

---

## Sources

- [The Lazy Goldmaker — Enchanting Shuffle](https://thelazygoldmaker.com/the-enchanting-shuffle-is-goldmaking-that-anyone-can-get-into) (MEDIUM confidence — content verified via fetch)
- [The Lazy Goldmaker — Mathematics of Prospecting](https://thelazygoldmaker.com/the-mathematics-of-goldmaking-prospecting) (MEDIUM confidence — content verified via fetch)
- [Mozzletoff — BFA Inscription Milling Shuffle](https://gunnydelight.github.io/mozzletoff-wow-goldfarm-site/bfa-inscription-milling-shuffle.html) (MEDIUM confidence)
- [Mozzletoff — MOP Ore Shuffle](https://gunnydelight.github.io/mozzletoff-wow-goldfarm-site/mop-ore-shuffle.html) (MEDIUM confidence)
- [CraftSim — Wowhead News](https://www.wowhead.com/news/calculate-your-profession-crafts-and-profit-with-craftsim-346538) (MEDIUM confidence)
- [ProspectMate Addon — CurseForge](https://www.curseforge.com/wow/addons/prospectmate) (LOW confidence — 403 on direct fetch; description from search result)
- [Wowpedia — Prospecting mechanics](https://wowpedia.fandom.com/wiki/Prospecting) (HIGH confidence)
- [WoW Forums — AH cut](https://us.forums.blizzard.com/en/wow/t/what-does-the-ah-take/346603) (HIGH confidence — official Blizzard forum)
- [The Lazy Goldmaker — Shadowlands Profession Spreadsheet](https://thelazygoldmaker.com/shadowlands-profession-spreadsheet) (MEDIUM confidence — content verified via fetch)
- PROJECT.md — existing app requirements, constraints, and out-of-scope list (HIGH confidence)

---

*Feature research for: WoW AH Tracker — v1.1 Shuffles milestone*
*Researched: 2026-03-04*

---
---

# Feature Research — v1.2 Crafting Profitability Milestone

**Domain:** WoW crafting profitability calculator — recipe-based profit by profession, added to existing AH Tracker app
**Researched:** 2026-03-05
**Confidence:** MEDIUM — Midnight expansion launched recently; quality tier mechanics verified via multiple community guides (HIGH confidence); Blizzard API structure verified via RubyDoc and forum threads (MEDIUM confidence); API limitations around quality-tier crafted item pricing are a known gap (LOW confidence for that specific area).

---

## Background: How Crafting Profitability Works in WoW

A crafting profit calculator answers: "If I buy the reagents on the AH and sell the crafted item, what is my margin?"

The core formula is simple:

```
profit = (crafted_item_sell_price × 0.95) - sum(reagent_qty × reagent_price)
```

The complexity comes from:

1. **Reagents are commodities** — prices are region-wide and polled reliably by the existing system.
2. **Crafted items may or may not be commodities** — consumables (flasks, potions, food, enchants) are commodities and have region-wide AH prices. Gear and weapons are non-commodities, sold on connected-realm AH, which is a different API endpoint.
3. **Quality tiers** — in Midnight, crafted consumables and reagents have two quality tiers (Silver = Tier 1, Gold = Tier 2). Each tier produces a distinct item ID with a different AH price.
4. **Concentration mechanic** — players spend a regenerating resource (capped at 1,000; restores ~10/hr) to force Gold-quality output. This makes Gold-quality crafts time-gated rather than freely repeatable.
5. **5% AH cut** — same as shuffles. No deposit fee on commodity AH.

---

## Midnight Quality Tier System (HIGH confidence)

Midnight simplified crafting quality from three tiers (in The War Within) to two:

| Tier | Name | Also Called | Characteristics |
|------|------|-------------|-----------------|
| Tier 1 | Silver | "at least Silver" | Baseline output, always achievable without Concentration |
| Tier 2 | Gold | "Gold quality" | Premium tier, requires Concentration or high Skill; better stats |

Key facts (verified across multiple sources):

- **Consumables and reagents**: Only Silver and Gold. Always get at least Silver. Concentration immediately elevates to Gold.
- **Weapons and armor**: Still have 5 quality ranks (not relevant for this milestone — PROJECT.md scope is consumables/crafting materials).
- **Distinct item IDs per tier**: Silver-quality and Gold-quality versions of the same recipe produce different item IDs. This means different AH listings and different prices.
- **Price spread**: Gold quality commands a premium due to higher stat values. Serious raiders and M+ players specifically seek Gold quality. Silver sells to alts and casual players.
- **Concentration is a scarce resource**: ~4 days to fully regenerate from zero. This limits the volume of Gold-quality crafts per week and is the primary lever for premium pricing.

---

## What the Blizzard API Exposes for Professions (MEDIUM confidence)

### Available Endpoints

Based on RubyDoc for `BlizzardApi::Wow::Profession` and Blizzard forum threads:

| Endpoint | Path | Returns |
|----------|------|---------|
| Profession index | `GET /data/wow/profession/index` | List of all professions with IDs |
| Profession detail | `GET /data/wow/profession/{id}` | Profession name, description, skill tiers |
| Profession skill tier | `GET /data/wow/profession/{id}/skill-tier/{tier_id}` | Recipes in this tier (list of recipe IDs + names) |
| Recipe detail | `GET /data/wow/recipe/{id}` | Recipe name, reagents array, crafted_item, crafted_quantity |
| Modified crafting index | `GET /data/wow/modified-crafting/` | Optional reagent slot types |
| Modified crafting slot types | `GET /data/wow/modified-crafting/reagent-slot-type/` | Slot type definitions for quality reagents |

### Recipe Response Structure (MEDIUM confidence)

```json
{
  "id": 12345,
  "name": "Recipe: Midnight Flask of Power",
  "reagents": [
    { "reagent": { "id": 111111, "name": "Quel'dorei Petal" }, "quantity": 3 },
    { "reagent": { "id": 222222, "name": "Twilight Dust" }, "quantity": 1 }
  ],
  "crafted_item": { "id": 99999, "name": "Midnight Flask of Power" },
  "crafted_quantity": { "value": 1 }
}
```

### Known API Limitations (LOW confidence — requires empirical validation)

- **Quality-tier item IDs**: The recipe endpoint's `crafted_item` field returns one item ID. For quality-aware recipes, the Silver and Gold output are separate item IDs. The API may not expose both IDs directly from the recipe endpoint — additional mapping via modified crafting slot data or Wowhead may be needed.
- **modified_crafting_slots**: For Dragonflight/TWW-era recipes, quality reagents appear under `modified_crafting_slots` rather than `reagents`. Quantities in this field have been reported as sometimes zero (known bug). Midnight may have changed this — needs empirical testing.
- **Crafted item prices**: Crafted items are NOT in the commodities endpoint if they are non-commodity (realm-specific) items. For consumables (flasks, potions, food, enchants), which ARE commodities, prices appear in the existing commodities API.

**Practical implication**: Reagent costs (commodities) are easy. Crafted item prices for consumables are also fetchable via the same commodities API. Crafted item prices for gear/weapons require a connected-realm AH API call — out of scope for this milestone.

---

## Feature Landscape — Crafting Profitability

### Table Stakes (Users Expect These)

Features a crafting profitability section needs to not feel broken. These mirror what TSM, CraftSim, and WowCrafters all provide.

| Feature | Why Expected | Complexity | Notes |
|---------|--------------|------------|-------|
| Profession overview page | Every crafting tool groups recipes by profession. No one wants a flat list of 500 recipes | LOW | Index page: /crafting — cards for each Midnight crafting profession with best profit teaser |
| Per-profession recipe table | All tools show the full recipe list for a profession with sortable columns | MEDIUM | Detail page: /crafting/{profession} — table of all recipes with reagent cost, sell price, profit per craft |
| Reagent cost column (live prices) | Prices must come from live AH data, not static numbers. The whole point is live margins | LOW | Sum of (reagent_qty × latest PriceSnapshot.median_price) per recipe. Reuse existing polling infrastructure |
| Crafted item sell price column (live prices) | Without this there is no profit calc. Must show current AH price for the crafted item | MEDIUM | Crafted consumables (flasks, potions) are commodities — fetchable via existing commodities poller. Crafted item must have a WatchedItem record |
| Profit per craft column | Core number. Sell price minus reagent cost minus 5% AH cut | LOW | (sell_price × 0.95) - reagent_cost. Displayed in gold/red for positive/negative margin |
| Table sorting (by profit, by name) | Every table tool lets you sort by most profitable. Default sort by profit descending | LOW | Livewire sortable columns; default: profit desc |
| Auto-watch reagents | Reagent prices must be polled or the calc has no data. Mirroring the v1.1 shuffle auto-watch pattern | MEDIUM | On recipe import: for each reagent item_id, ensure WatchedItem exists. Same pattern as shuffle auto-watch |
| Tier 1 (Silver) profit shown | Baseline profit without spending Concentration. This is the floor | LOW | Tier 1 crafted item has its own item ID and AH price — track separately |
| Tier 2 (Gold) profit shown | Premium profit using Concentration. This is the ceiling, but time-gated | LOW | Tier 2 crafted item has its own item ID and AH price — track separately |

### Differentiators (Competitive Advantage)

Features that go beyond what existing tools offer, given this app has live AH prices already integrated.

| Feature | Value Proposition | Complexity | Notes |
|---------|-------------------|------------|-------|
| Median profit across both tiers | "What's the blended average if I craft some Silver, some Gold?" — directly specified in PROJECT.md | LOW | Simple: (tier1_profit + tier2_profit) / 2. Useful when Concentration supply is limited |
| Profession overview with top-N profitable recipes | At-a-glance "what should I craft today across all professions" — no existing web tool does this cleanly | MEDIUM | Overview page shows top 3 recipes per profession by profit descending, with colored margin badge |
| Staleness indicator on prices | Same as shuffles — surfaces when price data is stale so user knows calc confidence | LOW | Flag recipes where any reagent or crafted item snapshot is > 1 hour old |
| Crafting cost breakdown (per-reagent line items) | Shows which reagent drives cost so you can shop around | LOW | Recipe detail/hover: show each reagent with qty, unit price, subtotal |
| Missing price warning | If a reagent has no WatchedItem/snapshot, show the recipe as "incomplete" rather than silently wrong | LOW | Check all reagent item IDs for active WatchedItem records; flag missing ones |

### Anti-Features (Commonly Requested, Often Problematic)

| Feature | Why Requested | Why Problematic | Alternative |
|---------|---------------|-----------------|-------------|
| Gear / weapon crafting profit | Gear has 5 quality ranks and sells on connected-realm AH, not commodities API | Connected-realm AH requires a different endpoint, different polling strategy, realm-specific data. Doubles infrastructure scope. Crafted gear prices fluctuate dramatically and are server-specific | Scope to consumables and commodity crafts only. Document this limit in the UI. Gear is PROJECT.md out-of-scope already |
| Crafting simulation (Concentration optimizer) | CraftSim's core feature — "which recipes should I use my 1,000 Concentration points on?" | Requires knowing each recipe's skill, specialization bonuses, inspiration proc rates, and concentration cost per quality tier. This is a full simulation engine, not a price lookup | Show profit per quality tier and let the user decide where to spend Concentration |
| Specialization-aware profit | Some recipes yield more output or better quality based on which profession spec nodes you've unlocked | Requires per-character specialization data from the character profile API (authenticated endpoint, different OAuth scope). Personal tool doesn't maintain character data | Display baseline recipe profit only; note that specialization bonuses are not modeled |
| Work Order integration | Crafting orders let others craft for you; order fee affects net profit | Work orders involve character-specific data and in-game UI flows — not accessible via game data APIs | Out of scope. Tool models only open AH purchases and sales |
| Crafting queue / to-do list | "Queue up what to craft today" — CraftSim feature | Stateful, per-session workflow requiring significant UI complexity | Out of scope for v1.2. The recipe table is the input to a manual crafting workflow |
| Historical profit trend chart | Show how margin has changed as reagent and crafted item prices moved over time | Requires storing calculated profit snapshots over time (same issue as historical shuffle profit) | Individual item price history charts already exist on the dashboard. User can correlate |

---

## Feature Dependencies — Crafting Profitability

```
[Recipe data import — Blizzard API profession/recipe fetch]
    └──requires──> [Blizzard OAuth credentials (existing in .env)]
    └──produces──> [Recipe records: reagents + crafted_item per recipe]
    └──triggers──> [Auto-watch reagents and crafted items]

[Auto-watch reagents + crafted items]
    └──requires──> [Recipe data import]
    └──creates──>  [WatchedItem records for each reagent and crafted item]
                       └──enables──> [PriceSnapshot polling by scheduler]

[Reagent cost calculation]
    └──requires──> [PriceSnapshot data for each reagent]
    └──requires──> [Recipe reagent quantities]

[Crafted item sell price lookup]
    └──requires──> [PriceSnapshot data for crafted item (both tiers)]
    └──requires──> [Crafted item is a commodity (consumables only)]
    └──note──> [Tier 1 and Tier 2 are separate item IDs — need separate WatchedItem records]

[Tier 1 (Silver) profit]
    └──requires──> [Reagent cost calculation]
    └──requires──> [Tier 1 crafted item sell price]

[Tier 2 (Gold) profit]
    └──requires──> [Reagent cost calculation]
    └──requires──> [Tier 2 crafted item sell price]

[Median profit across tiers]
    └──requires──> [Tier 1 profit]
    └──requires──> [Tier 2 profit]

[Profession overview page — top recipes per profession]
    └──requires──> [Profit calculation for all recipes in that profession]
    └──requires──> [Recipe-to-profession mapping from API import]

[Per-profession recipe table]
    └──requires──> [Profit calculation for all recipes]
    └──requires──> [Recipe data import]
    └──enhances──> [Table sorting by profit, name, tier]

[Staleness indicator]
    └──requires──> [PriceSnapshot.polled_at for all reagents and crafted items]
```

### Dependency Notes

- **Recipe import must precede everything:** Unlike shuffles (user-defined chains), recipes come from Blizzard's API. A one-time (or periodic) import job must seed the database before any profit calc is possible.
- **Two crafted item IDs per quality-aware recipe:** Tier 1 (Silver) and Tier 2 (Gold) outputs are separate item IDs. The recipe API may only expose one — the correct item IDs for both tiers may require cross-referencing Wowhead data or empirical testing. This is the single highest-risk unknown in v1.2. Plan for a manual override / seed file if the API doesn't expose both cleanly.
- **Crafted consumables are commodities:** Flasks, potions, food, enchant scrolls — these are all commodities. The existing commodities poller can fetch their prices once WatchedItem records exist. No new API endpoint needed for these.
- **Auto-watch mirrors v1.1 shuffle pattern:** The same mechanism (create WatchedItem on save/import) applies here. Both reagents AND crafted item IDs need WatchedItem records.
- **Profession-to-recipe mapping lives in the API:** The skill tier endpoint lists recipe IDs per profession. Import must walk: profession index → skill tier list → recipe IDs → recipe detail per ID.

---

## MVP Definition — Crafting Profitability (v1.2)

### Launch With (v1.2)

Minimum to deliver a useful crafting profitability section.

- [ ] Recipe import job — fetch all Midnight profession recipes from Blizzard API and seed DB (one-time + refreshable)
- [ ] Auto-watch reagents and crafted items — create WatchedItem records for every item referenced in imported recipes
- [ ] Profession overview page (/crafting) — cards per Midnight crafting profession with top profitable recipe highlight
- [ ] Per-profession recipe table (/crafting/{profession}) — all recipes with: reagent cost, Tier 1 sell price, Tier 2 sell price, Tier 1 profit, Tier 2 profit, median profit
- [ ] Sortable table — default sort by median profit descending; allow sort by name, tier 1 profit, tier 2 profit
- [ ] Profit calculation — (sell × 0.95) - reagent_cost for each tier
- [ ] Missing price indicator — flag recipes where reagent or crafted item prices are absent
- [ ] Price staleness indicator — flag recipes where any price snapshot is > 1 hour old

### Add After Validation (v1.x)

- [ ] Per-recipe cost breakdown (hover/expand showing each reagent line item) — useful for identifying expensive reagents
- [ ] Manual crafted item ID override — escape hatch if API doesn't expose both Tier 1 and Tier 2 item IDs correctly
- [ ] Periodic recipe refresh job — re-import recipes on expansion patch days to catch new recipes

### Future Consideration (v2+)

- [ ] Gear/weapon crafting profit — requires connected-realm AH API, realm-specific pricing
- [ ] Specialization-aware profit — requires character profile API integration
- [ ] Concentration optimizer — full simulation engine (CraftSim territory)

---

## Feature Prioritization Matrix — Crafting Profitability

| Feature | User Value | Implementation Cost | Priority |
|---------|------------|---------------------|----------|
| Recipe import job (Blizzard API) | HIGH | MEDIUM | P1 |
| Auto-watch reagents + crafted items | HIGH | LOW | P1 |
| Profession overview page | HIGH | LOW | P1 |
| Per-profession recipe table | HIGH | MEDIUM | P1 |
| Tier 1 profit column | HIGH | LOW | P1 |
| Tier 2 profit column | HIGH | LOW | P1 |
| Median profit column | MEDIUM | LOW | P1 |
| Table sort (by profit) | HIGH | LOW | P1 |
| Missing price indicator | MEDIUM | LOW | P1 |
| Price staleness indicator | MEDIUM | LOW | P1 |
| Per-recipe reagent cost breakdown | MEDIUM | LOW | P2 |
| Manual item ID override | MEDIUM | LOW | P2 |
| Periodic recipe refresh job | LOW | LOW | P2 |
| Gear/weapon crafting profit | LOW | HIGH | P3 (out of scope) |
| Specialization-aware profit | LOW | HIGH | P3 (out of scope) |
| Concentration optimizer | LOW | HIGH | P3 (out of scope) |

**Priority key:**
- P1: Must have for v1.2 launch
- P2: Should have, add when possible
- P3: Nice to have / deferred

---

## Integration with Existing App

The Crafting Profitability feature is additive, reusing every existing infrastructure piece.

| Existing Piece | How Crafting Uses It |
|----------------|----------------------|
| `WatchedItem` model | Auto-watch creates WatchedItem records for all reagents and crafted items so they get polled |
| `PriceSnapshot` model | Latest snapshot.median_price used for reagent costs and crafted item sell prices |
| Blizzard API poller (scheduler) | Already running every 15 min — newly auto-watched items get prices within one poll cycle |
| `CatalogItem` model | Reagents and crafted items are CatalogItems; recipe import populates catalog entries |
| Auth (Breeze, single-user) | No changes — Crafting section lives behind existing auth middleware |
| Tailwind CSS v4 / WoW dark theme | Gold/amber for profitable, red for negative margin; consistent with dashboard and shuffles |
| Navigation layout | Add "Crafting" nav entry alongside Dashboard, Watchlist, Shuffles |
| Existing commodities poller | Crafted consumables (flasks, potions, enchants) are commodities — their prices come from the same endpoint already being polled |

**Critical difference from Shuffles:** Shuffles use user-entered yield ratios. Crafting Profitability uses API-sourced recipe data. This means an import job is required before the section is usable, and the data can go stale if Blizzard adds new recipes in a patch.

---

## Ecosystem Tool Comparison — Crafting Profitability

| Feature | TSM (addon) | CraftSim (addon) | WowCrafters (web) | This App |
|---------|-------------|------------------|-------------------|----------|
| Recipe source | In-game profession window | In-game profession window | Blizzard API + Wowhead | Blizzard API |
| Live AH prices | TSM data feed (addon) | TSM data feed (addon) | Not specified | Blizzard API 15-min polling |
| Quality tier profit | Yes (complex) | Yes (simulation-based) | Yes (concentration-aware) | Tier 1 + Tier 2 + median |
| Profession overview | Via crafting operations | Via recipe scan | Yes | Yes |
| Sortable recipe table | Via crafting list | Via recipe scan | Yes | Yes |
| Web-based (no addon) | No | No | Yes | Yes |
| Specialization bonuses | Yes | Yes | Yes (concentration) | No (out of scope) |
| Gear profit | Yes | Yes | No | No (out of scope) |
| Personal tool / no account | No (account required) | No (in-game) | Unclear | Yes (single-user) |

**Gap this fills:** The only web-based, no-addon-required crafting profitability calculator with live Blizzard API prices that also shows Tier 1 / Tier 2 / median profit columns. WowCrafters is closest but requires account setup and has unclear pricing methodology. This app's advantage is tight integration with the existing price polling infrastructure.

---

## Open Questions Requiring Empirical Validation

These cannot be fully answered by research alone — they require hitting the live Blizzard API.

1. **Does the recipe endpoint expose both Tier 1 and Tier 2 item IDs?** If not, how do we identify them? (Options: Wowhead scrape, manual seed file, modified-crafting endpoint). This is the highest-risk unknown.

2. **Are all Midnight crafted consumables (flasks, potions) present in the commodities endpoint?** Assumption is yes — they should be commodities — but needs verification for Midnight specifically.

3. **What is the Midnight expansion's skill tier ID?** The Blizzard API organizes recipes by expansion skill tier. We need the correct tier ID to filter to Midnight-only recipes.

4. **Does `modified_crafting_slots` have the reagent quantity bug in Midnight?** Previous expansions had a known issue where quantities were zero. If this persists, quality reagent quantities must be sourced elsewhere.

---

## Sources

- [WoW-Professions.com — Midnight Tailoring Guide](https://www.wow-professions.com/midnight/tailoring-guide) — Quality tier mechanics verified (HIGH confidence — content fetched)
- [BlizzardApi Ruby gem — Profession class](https://rubydoc.info/gems/blizzard_api/BlizzardApi/Wow/Profession) — API endpoint paths (MEDIUM confidence — content fetched)
- [Blizzard Forums — Modified Crafting API Support](https://us.forums.blizzard.com/en/blizzard/t/wow-game-data-api-modified-crafting-support/12727) — modified_crafting endpoints confirmed (MEDIUM confidence — content fetched)
- [Blizzard Forums — Missing modified_crafting_slots quantity](https://us.forums.blizzard.com/en/blizzard/t/missing-modifiedcraftingslots-quantity-in-recipe-endpoint/49170) — known quantity bug (MEDIUM confidence)
- [Wowhead — CraftSim feature overview](https://www.wowhead.com/news/calculate-your-profession-crafts-and-profit-with-craftsim-346538) — feature landscape for crafting profit tools (MEDIUM confidence — content fetched)
- [WowCrafters](https://wowcrafters.com/) — web-based competitor feature overview (MEDIUM confidence — content fetched)
- [Icy Veins — Best Professions to Make Gold in Midnight](https://www.icy-veins.com/wow/professions-making-gold) — profitability metrics and quality tier impact (MEDIUM confidence — content fetched)
- [Multiple community gold guides](https://www.dtgre.com/2026/03/wow-midnight-gold-guide-farms-professions-lumber.html) — Silver/Gold quality tier price behavior (MEDIUM confidence — multiple sources converge)
- [GitHub — wow-recipe-list-to-json](https://github.com/ArekusuNaito/wow-recipe-list-to-json) — recipe JSON structure example (MEDIUM confidence — content fetched)
- [Blizzard API — Commodities endpoint structure](https://us.forums.blizzard.com/en/blizzard/t/immediate-change-to-auction-apis-for-commodities-with-927/31522) — confirmed commodities vs connected-realm separation (HIGH confidence)
- PROJECT.md — feature requirements and constraints for v1.2 (HIGH confidence)

---

*Feature research for: WoW AH Tracker — v1.2 Crafting Profitability milestone*
*Researched: 2026-03-05*
