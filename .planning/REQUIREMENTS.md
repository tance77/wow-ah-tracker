# Requirements: WoW AH Tracker

**Defined:** 2026-03-01
**Core Value:** See at a glance when crafting material prices dip or spike so users can act on buy/sell opportunities before the market corrects.

## v1 Requirements

Requirements for initial release. Each maps to roadmap phases.

### Authentication

- [x] **AUTH-01**: User can register with email and password
- [x] **AUTH-02**: User can log in and stay logged in across sessions
- [x] **AUTH-03**: User can log out from any page
- [x] **AUTH-04**: User can reset password via email link

### Item Management

- [ ] **ITEM-01**: User can add a WoW commodity item to their watchlist by name or item ID
- [ ] **ITEM-02**: User can remove an item from their watchlist
- [ ] **ITEM-03**: User can set buy threshold (% below average) per watched item
- [ ] **ITEM-04**: User can set sell threshold (% above average) per watched item
- [ ] **ITEM-05**: Each user has their own independent watchlist

### Data Collection

- [ ] **DATA-01**: Scheduled job fetches commodity prices from Blizzard API every 15 minutes
- [x] **DATA-02**: Each snapshot stores min price, average price, median price, and total volume
- [x] **DATA-03**: Prices stored as integers (copper) to avoid rounding errors
- [ ] **DATA-04**: Duplicate snapshots skipped when API data hasn't changed (Last-Modified check)
- [ ] **DATA-05**: Blizzard OAuth2 token cached and refreshed automatically
- [ ] **DATA-06**: Job uses withoutOverlapping to prevent duplicate runs

### Dashboard

- [ ] **DASH-01**: User sees summary cards for all watched items with current price and trend direction
- [ ] **DASH-02**: User can view price history line chart for each watched item
- [ ] **DASH-03**: User can toggle chart timeframe between 24h, 7d, and 30d
- [ ] **DASH-04**: Visual buy signal shown when price is below user's buy threshold
- [ ] **DASH-05**: Visual sell signal shown when price is above user's sell threshold
- [ ] **DASH-06**: Dashboard only shows the logged-in user's watched items

## v2 Requirements

Deferred to future release. Tracked but not in current roadmap.

### Notifications

- **NOTF-01**: User receives Discord webhook alert when a threshold is breached
- **NOTF-02**: User can configure notification preferences

### Analytics

- **ANLX-01**: Volume/supply trend chart overlay alongside price
- **ANLX-02**: Percent-from-average label on chart data points

### Advanced

- **ADVN-01**: Crafting profit calculator (track crafted item sell prices)
- **ADVN-02**: Additional item categories (gear, pets, mounts)

## Out of Scope

Explicitly excluded. Documented to prevent scope creep.

| Feature | Reason |
|---------|--------|
| Cross-region support (EU/KR/TW) | US Retail only — commodities are region-wide, adding regions doubles API calls and data |
| Classic/SoD support | Different API endpoints and data shape; separate project |
| Real-time WebSocket streaming | Blizzard API updates hourly max; WebSocket adds complexity without real freshness |
| Full AH item database | Value is curation, not breadth; 100k+ items would need different architecture |
| Mobile native app | Web-first with responsive Tailwind; mobile browser is sufficient |
| Email notifications | Discord webhook is simpler and more immediate for v1.x |

## Traceability

Which phases cover which requirements. Updated during roadmap creation.

| Requirement | Phase | Status |
|-------------|-------|--------|
| AUTH-01 | Phase 2 | Complete |
| AUTH-02 | Phase 2 | Complete |
| AUTH-03 | Phase 2 | Complete |
| AUTH-04 | Phase 2 | Complete |
| ITEM-01 | Phase 3 | Pending |
| ITEM-02 | Phase 3 | Pending |
| ITEM-03 | Phase 3 | Pending |
| ITEM-04 | Phase 3 | Pending |
| ITEM-05 | Phase 3 | Pending |
| DATA-01 | Phase 5 | Pending |
| DATA-02 | Phase 1 | Complete |
| DATA-03 | Phase 1 | Complete |
| DATA-04 | Phase 6 | Pending |
| DATA-05 | Phase 4 | Pending |
| DATA-06 | Phase 5 | Pending |
| DASH-01 | Phase 7 | Pending |
| DASH-02 | Phase 7 | Pending |
| DASH-03 | Phase 7 | Pending |
| DASH-04 | Phase 8 | Pending |
| DASH-05 | Phase 8 | Pending |
| DASH-06 | Phase 7 | Pending |

**Coverage:**
- v1 requirements: 21 total
- Mapped to phases: 21
- Unmapped: 0

---
*Requirements defined: 2026-03-01*
*Last updated: 2026-03-01 after roadmap creation*
