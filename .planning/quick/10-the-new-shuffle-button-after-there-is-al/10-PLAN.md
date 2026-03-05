---
phase: quick-10
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - resources/views/livewire/pages/shuffles.blade.php
autonomous: true
requirements: [BUG-10]
must_haves:
  truths:
    - "New Shuffle button works when shuffles already exist in the list"
    - "New Shuffle button still works on the empty state"
    - "Header still displays the Shuffles title"
  artifacts:
    - path: "resources/views/livewire/pages/shuffles.blade.php"
      provides: "Shuffles list page with working New Shuffle button"
  key_links:
    - from: "New Shuffle button"
      to: "createShuffle Livewire method"
      via: "wire:click inside Livewire-tracked DOM"
      pattern: "wire:click=\"createShuffle\""
---

<objective>
Fix the "New Shuffle" button that appears in the header when shuffles already exist. The button does not trigger `createShuffle` because it is rendered inside `<x-slot name="header">`, which the layout places outside Livewire's component tracking boundary (`$slot`). Move the button into the main component body so `wire:click` is processed by Livewire.

Purpose: Users with existing shuffles cannot create new ones from the list page.
Output: Working "New Shuffle" button in all states.
</objective>

<execution_context>
@/Users/lancethompson/.claude/get-shit-done/workflows/execute-plan.md
@/Users/lancethompson/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@resources/views/livewire/pages/shuffles.blade.php
@resources/views/layouts/app.blade.php
</context>

<tasks>

<task type="auto">
  <name>Task 1: Move New Shuffle button from header slot into Livewire-tracked body</name>
  <files>resources/views/livewire/pages/shuffles.blade.php</files>
  <action>
    1. Simplify the `<x-slot name="header">` block (lines 59-71) to contain only the title text — remove the flex wrapper and the button, leaving just the h2 element.

    2. In the `@else` block (line 92, where shuffles exist), add a row above the table (above line 94 `<!-- Shuffles Table -->`) containing a flex container with the "New Shuffle" button aligned to the right. Use the same button styling as the existing empty-state button. Example structure:
       ```
       <div class="flex justify-end px-6 pt-4">
           <button wire:click="createShuffle" class="rounded-md bg-wow-gold px-4 py-2 text-sm font-semibold text-wow-darker transition-colors hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-wow-gold focus:ring-offset-2 focus:ring-offset-wow-dark">
               New Shuffle
           </button>
       </div>
       ```

    3. Leave the empty-state "Create Shuffle" button (lines 85-90) unchanged — it already works correctly inside the Livewire DOM.

    Root cause: `<x-slot name="header">` content is rendered by the layout at `{{ $header }}` (line 25 of layouts/app.blade.php), which is OUTSIDE the `{{ $slot }}` div (line 32) where Livewire tracks its component. Therefore `wire:click` directives in the header slot are never processed.
  </action>
  <verify>
    <automated>cd /Users/lancethompson/Github/wow-ah-tracker && php artisan test --filter=Shuffle 2>&1 | tail -20</automated>
  </verify>
  <done>The "New Shuffle" button is inside the Livewire-tracked DOM in both empty and non-empty states. The header slot contains only the page title. Existing tests pass.</done>
</task>

</tasks>

<verification>
- The `<x-slot name="header">` block contains no `wire:click` directives
- The "New Shuffle" button in the `@else` block is inside `<div class="py-12">` (within Livewire's tracked DOM)
- The empty-state "Create Shuffle" button remains unchanged
- All existing Shuffle tests pass
</verification>

<success_criteria>
- Clicking "New Shuffle" when shuffles exist in the list creates a new shuffle and redirects to its detail page
- Empty-state button continues to work as before
- Page header still shows "Shuffles" title
</success_criteria>

<output>
After completion, create `.planning/quick/10-the-new-shuffle-button-after-there-is-al/10-SUMMARY.md`
</output>
