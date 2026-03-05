---
phase: 16
slug: per-profession-recipe-table
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-03-05
---

# Phase 16 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | Pest (PHPUnit wrapper) |
| **Config file** | `phpunit.xml` |
| **Quick run command** | `php artisan test --filter=CraftingDetailTest` |
| **Full suite command** | `php artisan test` |
| **Estimated runtime** | ~15 seconds |

---

## Sampling Rate

- **After every task commit:** Run `php artisan test --filter=CraftingDetailTest`
- **After every plan wave:** Run `php artisan test`
- **Before `/gsd:verify-work`:** Full suite must be green
- **Max feedback latency:** 15 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|-----------|-------------------|-------------|--------|
| 16-01-01 | 01 | 1 | TABLE-01 | feature | `php artisan test --filter=CraftingDetailTest::it_shows_all_recipes_in_table` | No - W0 | pending |
| 16-01-02 | 01 | 1 | TABLE-02 | feature | `php artisan test --filter=CraftingDetailTest::it_displays_profit_columns` | No - W0 | pending |
| 16-01-03 | 01 | 1 | TABLE-03 | feature | `php artisan test --filter=CraftingDetailTest::it_flags_missing_prices` | No - W0 | pending |
| 16-01-04 | 01 | 1 | TABLE-04 | feature | `php artisan test --filter=CraftingDetailTest::it_shows_staleness_warning` | No - W0 | pending |
| 16-01-05 | 01 | 1 | TABLE-05 | feature | `php artisan test --filter=CraftingDetailTest::it_includes_reagent_breakdown_data` | No - W0 | pending |
| 16-01-06 | 01 | 1 | TABLE-06 | feature | `php artisan test --filter=CraftingDetailTest::it_marks_non_commodity_recipes` | No - W0 | pending |

*Status: pending / green / red / flaky*

---

## Wave 0 Requirements

- [ ] `tests/Feature/CraftingDetailTest.php` — stubs for TABLE-01 through TABLE-06
- [ ] Reuse `createRecipeWithProfit()` helper from `CraftingOverviewTest.php` or extract to shared trait
- Framework install: Not needed — Pest already configured

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Client-side sort toggle (asc/desc) | TABLE-01 | Alpine.js client-side interaction | Click column headers in browser, verify sort direction changes |
| Accordion expand/collapse | TABLE-05 | Alpine.js DOM interaction | Click recipe rows, verify only one expands at a time |
| Staleness banner visibility | TABLE-04 | Requires real timestamp comparison | Seed stale data, verify amber banner appears |

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 15s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
