---
phase: quick-10
plan: 01
subsystem: shuffles-ui
tags: [bug-fix, livewire, blade]
dependency_graph:
  requires: []
  provides: [working-new-shuffle-button]
  affects: [shuffles-list-page]
tech_stack:
  added: []
  patterns: [livewire-component-boundary]
key_files:
  modified:
    - resources/views/livewire/pages/shuffles.blade.php
decisions:
  - "New Shuffle button moved from header slot into @else block within Livewire-tracked DOM; header slot retains only the h2 title"
metrics:
  duration: "3 min"
  completed: "2026-03-04"
  tasks_completed: 1
  files_modified: 1
---

# Quick Task 10: Fix New Shuffle Button Outside Livewire Boundary — Summary

**One-liner:** Moved "New Shuffle" button from `<x-slot name="header">` (outside Livewire's tracked DOM) into the `@else` block body so `wire:click="createShuffle"` is processed by Livewire.

## What Was Built

The Shuffles list page had a "New Shuffle" button in the `<x-slot name="header">` block. The app layout renders `{{ $header }}` at line 25, which is outside the `{{ $slot }}` div at line 32 where Livewire tracks its component. As a result, `wire:click` directives in the header slot were never intercepted by Livewire — clicks were silently ignored.

**Fix applied:**
1. The `<x-slot name="header">` block was simplified to contain only the `<h2>` title element.
2. A "New Shuffle" button with `wire:click="createShuffle"` was added inside the `@else` branch (shuffles exist state), above the shuffles table, within the Livewire-tracked `<div class="py-12">`.
3. The empty-state "Create Shuffle" button was left unchanged (it was already inside the Livewire DOM and working correctly).

## Tasks Completed

| Task | Name | Commit | Files |
|------|------|--------|-------|
| 1 | Move New Shuffle button from header slot into Livewire-tracked body | fc2a062 | resources/views/livewire/pages/shuffles.blade.php |

## Verification

- The `<x-slot name="header">` block contains no `wire:click` directives
- The "New Shuffle" button in the `@else` block is inside `<div class="py-12">` (within Livewire's tracked DOM)
- The empty-state "Create Shuffle" button remains unchanged
- All 60 existing Shuffle tests pass (1.27s)

## Deviations from Plan

None — plan executed exactly as written.

## Self-Check: PASSED

- [x] Modified file exists: `resources/views/livewire/pages/shuffles.blade.php`
- [x] Commit exists: `fc2a062`
- [x] All 60 Shuffle tests pass
