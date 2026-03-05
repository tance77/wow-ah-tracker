---
phase: 10
slug: shuffle-crud-navigation
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-03-04
---

# Phase 10 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | Pest PHP (via PHPUnit) |
| **Config file** | `phpunit.xml` |
| **Quick run command** | `php artisan test --filter ShuffleCrud` |
| **Full suite command** | `php artisan test` |
| **Estimated runtime** | ~15 seconds |

---

## Sampling Rate

- **After every task commit:** Run `php artisan test --filter ShuffleCrud`
- **After every plan wave:** Run `php artisan test`
- **Before `/gsd:verify-work`:** Full suite must be green
- **Max feedback latency:** 15 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|-----------|-------------------|-------------|--------|
| 10-01-01 | 01 | 1 | SHUF-01 | Feature | `php artisan test --filter "user can create a shuffle"` | ❌ W0 | ⬜ pending |
| 10-01-02 | 01 | 1 | SHUF-03 | Feature | `php artisan test --filter "user can rename a shuffle"` | ❌ W0 | ⬜ pending |
| 10-01-03 | 01 | 1 | SHUF-04 | Feature | `php artisan test --filter "user can delete a shuffle"` | ❌ W0 | ⬜ pending |
| 10-01-04 | 01 | 1 | SHUF-05 | Feature | `php artisan test --filter "shuffles list shows profitability badge"` | ❌ W0 | ⬜ pending |
| 10-01-05 | 01 | 1 | — | Feature | `php artisan test --filter "shuffles redirects unauthenticated"` | ❌ W0 | ⬜ pending |
| 10-01-06 | 01 | 1 | — | Feature | `php artisan test --filter "shuffle detail returns 403"` | ❌ W0 | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [ ] `tests/Feature/ShuffleCrudTest.php` — stubs for SHUF-01, SHUF-03, SHUF-04, SHUF-05 plus auth guard tests

*Pest.php, TestCase.php, and factory infrastructure are already in place from Phase 9.*

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Shuffles nav link active state on list and detail | — | Visual state requires browser render | Navigate to /shuffles, verify link highlighted; navigate to /shuffles/{id}, verify still highlighted |
| Profitability badge color rendering | SHUF-05 | CSS color verification requires visual check | View shuffle with positive profit (green), negative profit (red), and no data (gray neutral) |

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 15s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
