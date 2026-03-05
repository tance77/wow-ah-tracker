---
phase: 12
slug: batch-calculator-and-profit-summary
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-03-04
---

# Phase 12 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | Pest PHP (Laravel feature tests) |
| **Config file** | `phpunit.xml` |
| **Quick run command** | `php artisan test --filter=ShuffleBatchCalculator` |
| **Full suite command** | `php artisan test` |
| **Estimated runtime** | ~15 seconds |

---

## Sampling Rate

- **After every task commit:** Run `php artisan test --filter=ShuffleBatchCalculator`
- **After every plan wave:** Run `php artisan test`
- **Before `/gsd:verify-work`:** Full suite must be green
- **Max feedback latency:** 15 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|-----------|-------------------|-------------|--------|
| 12-01-01 | 01 | 1 | INTG-02 | unit (model) | `php artisan test --filter=ShuffleBatchCalculator` | ❌ W0 | ⬜ pending |
| 12-01-02 | 01 | 1 | INTG-03 | unit (model) | `php artisan test --filter=ShuffleBatchCalculator` | ❌ W0 | ⬜ pending |
| 12-01-03 | 01 | 1 | CALC-03 | unit (model) | `php artisan test --filter=ShuffleBatchCalculator` | ❌ W0 | ⬜ pending |
| 12-01-04 | 01 | 1 | CALC-04 | unit (model) | `php artisan test --filter=ShuffleBatchCalculator` | ❌ W0 | ⬜ pending |
| 12-02-01 | 02 | 1 | CALC-01 | feature (Volt) | `php artisan test --filter=ShuffleBatchCalculator` | ❌ W0 | ⬜ pending |
| 12-02-02 | 02 | 1 | CALC-02 | feature (Volt) | `php artisan test --filter=ShuffleBatchCalculator` | ❌ W0 | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [ ] `tests/Feature/ShuffleBatchCalculatorTest.php` — stubs for INTG-02, INTG-03, CALC-01, CALC-02, CALC-03, CALC-04
  - Test: `priceData()` returns correct median_price and stale=false for fresh snapshots
  - Test: `priceData()` returns stale=true for snapshots > 1 hour old
  - Test: `profitPerUnit()` correctly cascades multi-step yield ratios
  - Test: `profitPerUnit()` returns null when first input or last output price is missing
  - Test: `calculatorSteps()` returns correct array shape with item names and icons
  - Test: Calculator section hidden when shuffle has no steps (Volt component render test)
  - Test: Calculator section visible when shuffle has steps

*Existing infrastructure covers framework setup — only test file stubs needed.*

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Alpine.js reactivity — typing quantity updates all values without page reload | CALC-01 | Alpine client-side rendering not testable via Pest | 1. Open shuffle detail 2. Enter quantity 3. Verify all values update instantly |
| Gold/silver/copper display format in calculator | CALC-02, CALC-03 | Blade template formatting verified visually | Check that values show as "1g 23s 45c" format |

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 15s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
