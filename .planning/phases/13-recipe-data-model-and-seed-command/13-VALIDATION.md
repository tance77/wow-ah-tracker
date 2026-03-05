---
phase: 13
slug: recipe-data-model-and-seed-command
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-03-05
---

# Phase 13 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | PestPHP 3.8 with pest-plugin-laravel |
| **Config file** | `phpunit.xml` |
| **Quick run command** | `php artisan test --filter SyncRecipes` |
| **Full suite command** | `php artisan test` |
| **Estimated runtime** | ~15 seconds |

---

## Sampling Rate

- **After every task commit:** Run `php artisan test --filter SyncRecipesCommandTest`
- **After every plan wave:** Run `php artisan test`
- **Before `/gsd:verify-work`:** Full suite must be green
- **Max feedback latency:** 15 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|-----------|-------------------|-------------|--------|
| 13-01-01 | 01 | 1 | IMPORT-01 | Feature | `php artisan test --filter SyncRecipesCommandTest` | ❌ W0 | ⬜ pending |
| 13-01-02 | 01 | 1 | IMPORT-02 | Feature | `php artisan test --filter SyncRecipesCommandTest` | ❌ W0 | ⬜ pending |
| 13-01-03 | 01 | 1 | IMPORT-03 | Feature | `php artisan test --filter SyncRecipesCommandTest` | ❌ W0 | ⬜ pending |
| 13-01-04 | 01 | 1 | IMPORT-04 | Feature | `php artisan test --filter SyncRecipesCommandTest` | ❌ W0 | ⬜ pending |
| 13-01-05 | 01 | 1 | IMPORT-05 | Feature | `php artisan test --filter SyncRecipesCommandTest` | ❌ W0 | ⬜ pending |
| 13-01-06 | 01 | 1 | IMPORT-06 | Feature | `php artisan test --filter SyncRecipesCommandTest` | ❌ W0 | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [ ] `tests/Feature/BlizzardApi/SyncRecipesCommandTest.php` — covers all IMPORT-* requirements
- [ ] `tests/Fixtures/blizzard_profession_index.json` — fixture for profession index response
- [ ] `tests/Fixtures/blizzard_profession_alchemy.json` — fixture for profession detail with skill_tiers
- [ ] `tests/Fixtures/blizzard_skill_tier_alchemy.json` — fixture for recipe list response
- [ ] `tests/Fixtures/blizzard_recipe_detail.json` — fixture for individual recipe with reagents

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| *None* | — | — | — |

*All phase behaviors have automated verification.*

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 15s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
