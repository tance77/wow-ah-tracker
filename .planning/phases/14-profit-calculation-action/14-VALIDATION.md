---
phase: 14
slug: profit-calculation-action
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-03-05
---

# Phase 14 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | PestPHP 3.8 with pest-plugin-laravel |
| **Config file** | `phpunit.xml` |
| **Quick run command** | `php artisan test --filter RecipeProfitAction` |
| **Full suite command** | `php artisan test` |
| **Estimated runtime** | ~5 seconds |

---

## Sampling Rate

- **After every task commit:** Run `php artisan test --filter RecipeProfitAction`
- **After every plan wave:** Run `php artisan test`
- **Before `/gsd:verify-work`:** Full suite must be green
- **Max feedback latency:** 5 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|-----------|-------------------|-------------|--------|
| 14-01-01 | 01 | 1 | PROFIT-01 | unit | `php artisan test --filter RecipeProfitActionTest` | ❌ W0 | ⬜ pending |
| 14-01-02 | 01 | 1 | PROFIT-01 | unit | `php artisan test --filter RecipeProfitActionTest` | ❌ W0 | ⬜ pending |
| 14-01-03 | 01 | 1 | PROFIT-02 | unit | `php artisan test --filter RecipeProfitActionTest` | ❌ W0 | ⬜ pending |
| 14-01-04 | 01 | 1 | PROFIT-03 | unit | `php artisan test --filter RecipeProfitActionTest` | ❌ W0 | ⬜ pending |
| 14-01-05 | 01 | 1 | PROFIT-04 | unit | `php artisan test --filter RecipeProfitActionTest` | ❌ W0 | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [ ] `tests/Feature/RecipeProfitActionTest.php` — stubs for PROFIT-01, PROFIT-02, PROFIT-03, PROFIT-04

*Existing infrastructure covers framework needs. RecipeFactory, PriceSnapshotFactory, CatalogItemFactory all exist.*

---

## Manual-Only Verifications

*All phase behaviors have automated verification.*

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 5s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
