# Requirements: WoW AH Tracker

**Defined:** 2026-03-05
**Core Value:** See at a glance when crafting material prices dip or spike so I can act on buy/sell opportunities before the market corrects.

## v1.2 Requirements

Requirements for Crafting Profitability milestone. Each maps to roadmap phases.

### Recipe Data Import

- [x] **IMPORT-01**: User can run `artisan blizzard:sync-recipes` to seed all Midnight expansion recipes from Blizzard API
- [x] **IMPORT-02**: Seed command auto-watches all reagents and crafted items (deduped across professions)
- [x] **IMPORT-03**: Seed command supports `--dry-run` flag to preview without writing
- [x] **IMPORT-04**: Seed command supports `--report-gaps` to log API field coverage (missing crafted_item, etc.)
- [x] **IMPORT-05**: Seed command is idempotent — re-runnable after game patches to pick up new recipes
- [x] **IMPORT-06**: Recipes table tracks `last_synced_at` timestamp

### Profitability Calculation

- [x] **PROFIT-01**: Per-recipe reagent cost calculated from live AH prices (sum of reagent quantities x median price)
- [x] **PROFIT-02**: Per-recipe crafted item sell price shown for Tier 1 (Silver) and Tier 2 (Gold)
- [x] **PROFIT-03**: Profit calculated as `(sell_price x 0.95) - reagent_cost` with 5% AH cut on sell side
- [x] **PROFIT-04**: Median profit across both tiers displayed per recipe

### Profession Overview

- [x] **OVERVIEW-01**: Crafting page shows cards for each Midnight profession
- [x] **OVERVIEW-02**: Each profession card displays top 3-5 most profitable recipes

### Recipe Table

- [x] **TABLE-01**: Per-profession page shows all recipes in a sortable table (default: median profit descending)
- [x] **TABLE-02**: Table columns: recipe name, reagent cost, Tier 1 profit, Tier 2 profit, median profit
- [x] **TABLE-03**: Recipes with missing price data flagged with indicator
- [x] **TABLE-04**: Stale price warning shown when any snapshot is > 1 hour old
- [x] **TABLE-05**: Per-reagent cost breakdown available on hover/expand
- [x] **TABLE-06**: Non-commodity recipes (gear) displayed as "realm AH — not tracked"

### Navigation

- [x] **NAV-01**: "Crafting" link added to main navigation

## Future Requirements

Deferred to future release. Tracked but not in current roadmap.

### Advanced Calculation

- **ADVN-01**: Yield quantity handling for multi-output recipes (alchemy/cooking)
- **ADVN-02**: Specialization-aware profit adjustments
- **ADVN-03**: Concentration cost factored into Tier 2 profit calculation

### Advanced Features

- **ADVN-04**: Manual crafted item ID override for API gap workarounds
- **ADVN-05**: Periodic scheduled recipe refresh job
- **ADVN-06**: Gear/weapon crafting profit via connected-realm AH pricing

## Out of Scope

Explicitly excluded. Documented to prevent scope creep.

| Feature | Reason |
|---------|--------|
| Gear/weapon crafting profit | Requires connected-realm AH API; different pricing pipeline |
| Specialization-aware profit | Requires character profile API with different OAuth scope |
| Concentration optimizer | CraftSim territory; full simulation engine complexity |
| Mobile app | Web only, responsive Tailwind |
| Multi-region | US only |

## Traceability

Which phases cover which requirements. Updated during roadmap creation.

| Requirement | Phase | Status |
|-------------|-------|--------|
| IMPORT-01 | Phase 13 | Complete |
| IMPORT-02 | Phase 13 | Complete |
| IMPORT-03 | Phase 13 | Complete |
| IMPORT-04 | Phase 13 | Complete |
| IMPORT-05 | Phase 13 | Complete |
| IMPORT-06 | Phase 13 | Complete |
| PROFIT-01 | Phase 14 | Complete |
| PROFIT-02 | Phase 14 | Complete |
| PROFIT-03 | Phase 14 | Complete |
| PROFIT-04 | Phase 14 | Complete |
| OVERVIEW-01 | Phase 15 | Complete |
| OVERVIEW-02 | Phase 15 | Complete |
| NAV-01 | Phase 15 | Complete |
| TABLE-01 | Phase 16 | Complete |
| TABLE-02 | Phase 16 | Complete |
| TABLE-03 | Phase 16 | Complete |
| TABLE-04 | Phase 16 | Complete |
| TABLE-05 | Phase 16 | Complete |
| TABLE-06 | Phase 16 | Complete |

**Coverage:**
- v1.2 requirements: 19 total
- Mapped to phases: 19
- Unmapped: 0

---
*Requirements defined: 2026-03-05*
*Last updated: 2026-03-05 — traceability complete after roadmap creation*
