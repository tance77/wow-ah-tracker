# Feature Research

**Domain:** WoW Auction House commodity price tracker (personal tool)
**Researched:** 2026-03-01
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
