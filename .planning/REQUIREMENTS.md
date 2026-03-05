# Requirements: WoW AH Tracker

**Defined:** 2026-03-04
**Core Value:** See at a glance when crafting material prices dip or spike so I can act on buy/sell opportunities before the market corrects.

## v1.1 Requirements

Requirements for Shuffles milestone. Each maps to roadmap phases.

### Shuffle Management

- [x] **SHUF-01**: User can create a named shuffle with a descriptive name
- [ ] **SHUF-02**: User can define multi-step conversion chains (A → B → C)
- [x] **SHUF-03**: User can edit an existing shuffle's name and steps
- [x] **SHUF-04**: User can delete a shuffle
- [x] **SHUF-05**: User can view a list of all saved shuffles with profitability badge

### Yield Configuration

- [ ] **YILD-01**: User can set a fixed yield ratio per conversion step
- [ ] **YILD-02**: User can set min/max yield range per step for probabilistic conversions
- [ ] **YILD-03**: User can reorder steps within a chain

### Item Integration

- [ ] **INTG-01**: Items added to a shuffle are auto-watched for price polling
- [ ] **INTG-02**: Shuffle calculator uses live median prices from latest price snapshots
- [ ] **INTG-03**: Price staleness warning shown when snapshot is older than 1 hour

### Batch Calculator

- [ ] **CALC-01**: User can enter input quantity and see cascading yields per step
- [ ] **CALC-02**: User can see per-step cost and value breakdown
- [ ] **CALC-03**: User can see total profit summary (cost in, value out with 5% AH cut, net profit)
- [ ] **CALC-04**: User can see break-even input price per shuffle

## v2 Requirements

Deferred to future release. Tracked but not in current roadmap.

### Notifications

- **NOTF-01**: Discord webhook alerts when thresholds are breached
- **NOTF-02**: User-configurable notification preferences

### Analytics

- **ANLX-01**: Volume/supply trend chart overlay
- **ANLX-02**: Percent-from-average labels on chart data points

### Shuffle Enhancements

- **SHFE-01**: Per-output vendor vs AH toggle (omit AH cut for vendored items)
- **SHFE-02**: Historical profitability trend chart over time

## Out of Scope

Explicitly excluded. Documented to prevent scope creep.

| Feature | Reason |
|---------|--------|
| Full recipe-based crafting calculator | Scope explosion — nested sub-recipes, quality tiers, specialization bonuses. Deferred as ADVN-01 |
| Monte Carlo yield simulation | Over-engineered for personal tool — min/max range communicates uncertainty sufficiently |
| Multi-region/realm pricing | US commodities only by API design — no realm difference for commodities |
| Automated shuffle profit alerts | No notification infrastructure yet; 15-min polling cadence is sufficient |
| Multi-output per step (e.g., prospecting yields multiple gem types) | Significant schema complexity; single output per step with separate steps for each output type |

## Traceability

Which phases cover which requirements. Updated during roadmap creation.

| Requirement | Phase | Status |
|-------------|-------|--------|
| SHUF-01 | Phase 10 | Complete |
| SHUF-02 | Phase 11 | Pending |
| SHUF-03 | Phase 10 | Complete |
| SHUF-04 | Phase 10 | Complete |
| SHUF-05 | Phase 10 | Complete |
| YILD-01 | Phase 11 | Pending |
| YILD-02 | Phase 11 | Pending |
| YILD-03 | Phase 11 | Pending |
| INTG-01 | Phase 11 | Pending |
| INTG-02 | Phase 12 | Pending |
| INTG-03 | Phase 12 | Pending |
| CALC-01 | Phase 12 | Pending |
| CALC-02 | Phase 12 | Pending |
| CALC-03 | Phase 12 | Pending |
| CALC-04 | Phase 12 | Pending |

**Coverage:**
- v1.1 requirements: 15 total
- Mapped to phases: 15
- Unmapped: 0

---
*Requirements defined: 2026-03-04*
*Last updated: 2026-03-04 after roadmap creation*
