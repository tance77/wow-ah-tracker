---
phase: 9
slug: data-foundation
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-03-04
---

# Phase 9 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | Pest 3.8 + pest-plugin-laravel 3.2 |
| **Config file** | `tests/Pest.php` (already configured) |
| **Quick run command** | `php artisan test --filter shuffle-data-foundation` |
| **Full suite command** | `php artisan test` |
| **Estimated runtime** | ~5 seconds |

---

## Sampling Rate

- **After every task commit:** Run `php artisan test --filter shuffle-data-foundation`
- **After every plan wave:** Run `php artisan test`
- **Before `/gsd:verify-work`:** Full suite must be green
- **Max feedback latency:** 5 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|-----------|-------------------|-------------|--------|
| 09-01-01 | 01 | 0 | SC-1 | unit (schema) | `php artisan test --filter shuffle-data-foundation` | ❌ W0 | ⬜ pending |
| 09-01-02 | 01 | 0 | SC-2 | unit (model) | `php artisan test --filter shuffle-data-foundation` | ❌ W0 | ⬜ pending |
| 09-01-03 | 01 | 0 | SC-3 | unit (factory) | `php artisan test --filter shuffle-data-foundation` | ❌ W0 | ⬜ pending |
| 09-01-04 | 01 | 0 | SC-4 | unit (cascade) | `php artisan test --filter shuffle-data-foundation` | ❌ W0 | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [ ] `tests/Feature/ShuffleDataFoundationTest.php` — stubs for SC-1 through SC-4
- [ ] No framework install needed — Pest already configured

*Existing infrastructure covers framework requirements.*

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|

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
