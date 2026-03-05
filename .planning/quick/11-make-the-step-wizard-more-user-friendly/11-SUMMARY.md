---
phase: quick-11
plan: 01
subsystem: shuffle-step-editor
tags: [ux, blade, alpine, tailwind]
dependency_graph:
  requires: []
  provides: [improved-step-card-ux, improved-add-step-form-ux]
  affects: [shuffle-detail]
tech_stack:
  added: []
  patterns: [alpine-reactive-text-binding, blade-loop-index-header]
key_files:
  created: []
  modified:
    - resources/views/livewire/pages/shuffle-detail.blade.php
decisions:
  - Step action buttons moved from bottom row to header row alongside "Step N" label for visual grouping
  - Conversion ratio summary uses x-text Alpine binding for reactive updates when yield values change
  - Add-step form broken into two sections with numbered visual headers to reduce cognitive load
metrics:
  duration: 5 min
  completed: 2026-03-05
  tasks_completed: 2
  files_modified: 1
---

# Quick Task 11: Make the Step Wizard More User-Friendly Summary

**One-liner:** Step cards now show step numbers with action buttons in header row, reactive conversion ratio summaries, and the add-step form uses numbered sections with conversational labels.

## What Was Built

Improved the shuffle step editor UX in shuffle-detail.blade.php with two focused enhancements:

### Step Cards (Task 1)
- Added "Step 1", "Step 2", etc. header row above each step card
- Moved move-up/move-down/delete action buttons from bottom row into the header row (right-aligned with `ml-auto`)
- Added reactive Alpine conversion ratio summary below the item row: `<inputQty> <InputName> -> <min>-<max> <OutputName>` (collapses to single number when min equals max)
- Updated yield row labels from terse abbreviations to natural language: "Input qty:" → "Uses", "Yield:" → "Produces"

### Add-Step Form (Task 2)
- Updated form title to show upcoming step number: "Add Step {{ $this->steps->count() + 1 }}"
- Added numbered section header "1. Choose Items" above the item search grid
- Changed item labels to conversational questions: "What do you put in?" and "What do you get out?"
- Added "2. Set Conversion Rate" section with divider line wrapping the yield configuration
- Updated yield labels: "Input qty:" → "How many input items?", "Yield:" → "Produces"
- Added helper text: "Example: if 5 ore produces 1-3 gems, set input to 5 and yield to 1-3"

## Commits

| Task | Name | Commit | Files |
|------|------|--------|-------|
| 1 | Add step numbering and conversion ratio summary | aeb73c7 | resources/views/livewire/pages/shuffle-detail.blade.php |
| 2 | Improve add-step form layout with sections and better labels | 8b2e325 | resources/views/livewire/pages/shuffle-detail.blade.php |

## Deviations from Plan

None - plan executed exactly as written.

## Verification

- `php artisan view:cache` — Blade templates cached successfully
- `php artisan test --filter=ShuffleStepEditorTest` — 19 passed (44 assertions)

## Self-Check: PASSED

- [x] resources/views/livewire/pages/shuffle-detail.blade.php modified
- [x] Commit aeb73c7 exists
- [x] Commit 8b2e325 exists
- [x] All tests pass
