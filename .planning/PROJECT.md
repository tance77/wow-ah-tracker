# WoW AH Tracker

## What This Is

A Laravel web application that tracks World of Warcraft Auction House commodity prices using the Blizzard API. It polls prices every 15 minutes for a curated list of crafting materials, stores price history, and presents a dashboard with trend charts to help spot buy/sell opportunities. Built for a single user on the US Retail region.

## Core Value

See at a glance when crafting material prices dip or spike so I can act on buy/sell opportunities before the market corrects.

## Requirements

### Validated

(None yet — ship to validate)

### Active

- [ ] Fetch commodity prices from Blizzard API on a 15-minute schedule
- [ ] Store price history for tracked items over time
- [ ] Dashboard with price trend charts (line charts over days/weeks)
- [ ] Visual indicators for price dips and spikes (buy/sell signals)
- [ ] Admin UI to add/remove watched items (currently ~6-7 crafting materials)
- [ ] Simple authentication (single user login)
- [ ] Blizzard API credentials managed via .env

### Out of Scope

- Multi-region support — US only for now
- Classic/SoD support — Retail only
- Multi-user / public access — single user app
- Email/Discord notifications — dashboard-only for v1
- Mobile app — web only
- Gear/equipment tracking — commodities only (no unique item stat variations)

## Context

- **Game:** World of Warcraft Retail (The War Within / current expansion)
- **API:** Blizzard Game Data API — Commodities endpoint returns all commodities for a region in a single call
- **Auth flow:** Blizzard API uses OAuth2 client credentials flow (client ID + secret → access token)
- **Commodities:** Unlike regular AH items, commodities are region-wide (not realm-specific). The `/data/wow/auctions/commodities` endpoint returns all active commodity listings.
- **Items to track:** ~6-7 crafting materials (herbs, ores, cloth, etc.) — managed through admin UI
- **Pricing model:** Commodities have quantity + unit price. Useful metrics: min price, average price, total volume, median price.

## Constraints

- **Tech stack:** Laravel (latest), Tailwind CSS, PHP 8.2+
- **Infrastructure:** Queue worker needed for scheduled jobs (Laravel scheduler + queue)
- **API rate limits:** Blizzard API has rate limits (~100 requests/second, 36,000/hour) — not a concern at 15-min intervals for a single endpoint
- **Data volume:** Commodities endpoint returns thousands of items per call, but we only store data for watched items

## Key Decisions

| Decision | Rationale | Outcome |
|----------|-----------|---------|
| Commodities endpoint (not realm-specific AH) | Crafting materials are commodities — region-wide, simpler API | — Pending |
| 15-minute polling interval | Balances freshness with API courtesy, matches AH update cadence | — Pending |
| Tailwind CSS for styling | User preference, rapid UI development | — Pending |
| Single-user simple auth | Personal tool, no need for complex user management | — Pending |

---
*Last updated: 2026-03-01 after initialization*
