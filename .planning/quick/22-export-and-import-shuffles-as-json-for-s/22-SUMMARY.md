---
phase: quick-22
plan: 1
subsystem: shuffles
tags: [export, import, json, sharing]
dependency_graph:
  requires: [Shuffle, ShuffleStep, ShuffleStepByproduct, WatchedItem, CatalogItem]
  provides: [shuffle-export-json, shuffle-import-json]
  affects: [shuffles-list-page, shuffle-detail-page]
tech_stack:
  added: []
  patterns: [streamDownload, WithFileUploads, file-upload-auto-trigger]
key_files:
  created: []
  modified:
    - resources/views/livewire/pages/shuffles.blade.php
    - resources/views/livewire/pages/shuffle-detail.blade.php
    - tests/Feature/ShuffleCrudTest.php
decisions:
  - JSON format includes version field for future compatibility
  - Item names included in export for human readability alongside blizzard_item_ids
  - Import appends (Imported) suffix to distinguish from original
  - File upload triggers importShuffle automatically via Alpine x-data watcher
metrics:
  duration: 2 min
  completed: "2026-03-05T23:48:31Z"
  tasks_completed: 2
  tasks_total: 2
  files_modified: 3
---

# Quick Task 22: Export and Import Shuffles as JSON Summary

Export/import shuffle configurations as JSON files with full step/byproduct data and auto-watched items on import.

## What Was Done

### Task 1: Export and import methods with TDD tests (RED -> GREEN)

Added 4 new tests to ShuffleCrudTest:
- Export returns valid JSON with correct structure (name, version, steps, byproducts)
- Import with valid JSON creates shuffle with "(Imported)" suffix, all steps, byproducts
- Import auto-watches all referenced blizzard_item_ids (input, output, byproduct)
- Malformed JSON (missing "steps" key) rejected with validation error

Implementation on shuffles.blade.php:
- `exportShuffle(int $id)` - Loads shuffle with eager-loaded relations, builds JSON structure, returns `response()->streamDownload()` with slug-based filename
- `importShuffle()` - Parses uploaded JSON, validates structure, creates shuffle with steps/byproducts, auto-watches all item IDs using same firstOrCreate pattern as clone
- Added `WithFileUploads` trait and `$importFile` property for Livewire file upload
- Export button in Actions column per row, Import JSON button in header and empty state

### Task 2: Export button on shuffle detail page

- Added `exportShuffle()` method to shuffle-detail component with identical JSON structure
- Export JSON button in the page header area (right side, consistent gray-400 hover:text-wow-gold styling)

## Deviations from Plan

None - plan executed exactly as written.

## Commits

| Task | Commit | Description |
|------|--------|-------------|
| 1 (RED) | 7838024 | test(quick-22): add failing tests for shuffle export/import |
| 1 (GREEN) | 2e1d14b | feat(quick-22): add export/import shuffle as JSON on shuffles list page |
| 2 | 8ff5927 | feat(quick-22): add export JSON button to shuffle detail page |

## Test Results

All 23 ShuffleCrudTest tests pass (66 assertions).

## Self-Check: PASSED
