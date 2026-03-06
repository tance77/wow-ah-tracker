---
phase: 15
slug: profession-overview-page-and-navigation
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-03-05
---

# Phase 15 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | Pest 3.x (PHP) |
| **Config file** | `phpunit.xml` + `tests/Pest.php` |
| **Quick run command** | `php artisan test --filter=CraftingOverview` |
| **Full suite command** | `php artisan test` |
| **Estimated runtime** | ~5 seconds |

---

## Sampling Rate

- **After every task commit:** Run `php artisan test --filter=CraftingOverview`
- **After every plan wave:** Run `php artisan test`
- **Before `/gsd:verify-work`:** Full suite must be green
- **Max feedback latency:** 10 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|-----------|-------------------|-------------|--------|
| 15-01-01 | 01 | 1 | NAV-01 | Feature (HTTP) | `php artisan test --filter=CraftingOverviewTest::it_shows_crafting_nav_link` | ❌ W0 | ⬜ pending |
| 15-01-02 | 01 | 1 | OVERVIEW-01 | Feature (HTTP) | `php artisan test --filter=CraftingOverviewTest::it_displays_profession_cards` | ❌ W0 | ⬜ pending |
| 15-01-03 | 01 | 1 | OVERVIEW-02 | Feature (HTTP) | `php artisan test --filter=CraftingOverviewTest::it_shows_top_recipes_per_profession` | ❌ W0 | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [ ] `tests/Feature/CraftingOverviewTest.php` — stubs for NAV-01, OVERVIEW-01, OVERVIEW-02
- [ ] Profession factory (if not already in `database/factories/`)
- [ ] Recipe factory with reagents and price snapshots for test data setup

*Existing infrastructure covers test framework — only test files and factories needed.*

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Responsive grid layout (3/2/1 columns) | OVERVIEW-01 | CSS breakpoints not testable in HTTP tests | Resize browser to verify at 1024px (3 col), 768px (2 col), 375px (1 col) |
| Profession icon display | OVERVIEW-01 | Visual appearance | Verify `<img>` tags render with correct profession icons |
| Red/green profit styling | OVERVIEW-02 | CSS class verification | Check profitable recipes show green, loss recipes show red |

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 10s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
