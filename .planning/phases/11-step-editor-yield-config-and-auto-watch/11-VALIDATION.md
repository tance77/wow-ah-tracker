---
phase: 11
slug: step-editor-yield-config-and-auto-watch
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-03-04
---

# Phase 11 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | Pest PHP (existing) |
| **Config file** | `phpunit.xml` + `tests/Pest.php` |
| **Quick run command** | `php artisan test --filter ShuffleStep` |
| **Full suite command** | `php artisan test` |
| **Estimated runtime** | ~15 seconds |

---

## Sampling Rate

- **After every task commit:** Run `php artisan test --filter ShuffleStep`
- **After every plan wave:** Run `php artisan test`
- **Before `/gsd:verify-work`:** Full suite must be green
- **Max feedback latency:** 15 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|-----------|-------------------|-------------|--------|
| 11-01-01 | 01 | 1 | SHUF-02 | Feature (Volt) | `php artisan test --filter ShuffleStepEditorTest` | ❌ W0 | ⬜ pending |
| 11-01-02 | 01 | 1 | SHUF-02 | Feature (Volt) | `php artisan test --filter ShuffleStepEditorTest` | ❌ W0 | ⬜ pending |
| 11-01-03 | 01 | 1 | YILD-01 | Feature (Volt) | `php artisan test --filter ShuffleStepEditorTest` | ❌ W0 | ⬜ pending |
| 11-01-04 | 01 | 1 | YILD-02 | Feature (Volt) | `php artisan test --filter ShuffleStepEditorTest` | ❌ W0 | ⬜ pending |
| 11-01-05 | 01 | 1 | YILD-03 | Feature (Volt) | `php artisan test --filter ShuffleStepEditorTest` | ❌ W0 | ⬜ pending |
| 11-01-06 | 01 | 1 | INTG-01 | Feature (Volt) | `php artisan test --filter ShuffleStepEditorTest` | ❌ W0 | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [ ] `tests/Feature/ShuffleStepEditorTest.php` — stubs for SHUF-02, YILD-01, YILD-02, YILD-03, INTG-01
- [ ] Migration `add_input_qty_to_shuffle_steps` — must exist and run before any other task

*Existing infrastructure covers framework and fixture needs.*

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Step card visual layout with WoW icons and arrow connectors | SHUF-02 | Visual/CSS rendering | Verify step cards show item icons, names, and arrow flow on shuffle detail page |
| Up/down reorder buttons respond correctly in browser | YILD-03 | Alpine.js interaction | Click up/down arrows and verify step order updates visually |
| Search-as-you-type dropdown for item selection | SHUF-02 | Alpine.js + Livewire UI | Type in search field, verify dropdown appears with matching items |

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 15s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
