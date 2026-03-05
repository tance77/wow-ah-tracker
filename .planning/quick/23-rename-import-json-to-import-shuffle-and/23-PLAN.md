---
phase: quick-23
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - resources/views/livewire/pages/shuffles.blade.php
  - resources/views/livewire/pages/shuffle-detail.blade.php
autonomous: true
requirements: [QUICK-23]
must_haves:
  truths:
    - "Import button on shuffles list page reads 'Import Shuffle' instead of 'Import JSON'"
    - "Export button on shuffles list page reads 'Share' instead of 'Export'"
    - "Export button on shuffle detail page reads 'Share' instead of 'Export JSON'"
  artifacts:
    - path: "resources/views/livewire/pages/shuffles.blade.php"
      provides: "Shuffles list with renamed buttons"
      contains: "Import Shuffle"
    - path: "resources/views/livewire/pages/shuffle-detail.blade.php"
      provides: "Shuffle detail with renamed button"
      contains: "Share"
  key_links: []
---

<objective>
Rename button labels in the shuffle UI: "Import JSON" becomes "Import Shuffle" and "Export" / "Export JSON" becomes "Share".

Purpose: Clearer, user-friendly labels that describe the action from the user's perspective.
Output: Updated Blade templates with new button text.
</objective>

<execution_context>
@/Users/lancethompson/.claude/get-shit-done/workflows/execute-plan.md
@/Users/lancethompson/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@resources/views/livewire/pages/shuffles.blade.php
@resources/views/livewire/pages/shuffle-detail.blade.php
</context>

<tasks>

<task type="auto">
  <name>Task 1: Rename button labels in shuffle Blade templates</name>
  <files>resources/views/livewire/pages/shuffles.blade.php, resources/views/livewire/pages/shuffle-detail.blade.php</files>
  <action>
In `resources/views/livewire/pages/shuffles.blade.php`:
- Line 250: Change "Import JSON" to "Import Shuffle"
- Line 262: Change "Import JSON" to "Import Shuffle"
- Line 369: Change "Export" to "Share"

In `resources/views/livewire/pages/shuffle-detail.blade.php`:
- Line 575: Change "Export JSON" to "Share"

No other changes needed. Only the visible button text changes; all wire:click handlers, functionality, and styling remain identical.
  </action>
  <verify>
    <automated>grep -n "Import JSON\|Export JSON\|Export" resources/views/livewire/pages/shuffles.blade.php resources/views/livewire/pages/shuffle-detail.blade.php | grep -v "import\|export" || echo "No old labels found (good)" && grep -c "Import Shuffle\|Share" resources/views/livewire/pages/shuffles.blade.php resources/views/livewire/pages/shuffle-detail.blade.php</automated>
  </verify>
  <done>All four button labels renamed: two "Import JSON" -> "Import Shuffle", one "Export" -> "Share", one "Export JSON" -> "Share". No old labels remain in either file.</done>
</task>

</tasks>

<verification>
- grep confirms no remaining "Import JSON" or "Export JSON" text in either Blade file
- grep confirms "Import Shuffle" appears twice in shuffles.blade.php
- grep confirms "Share" appears in both shuffles.blade.php (line ~369) and shuffle-detail.blade.php (line ~575)
</verification>

<success_criteria>
All shuffle UI buttons use the new labels: "Import Shuffle" and "Share". No functional changes, only text.
</success_criteria>

<output>
After completion, create `.planning/quick/23-rename-import-json-to-import-shuffle-and/23-SUMMARY.md`
</output>
