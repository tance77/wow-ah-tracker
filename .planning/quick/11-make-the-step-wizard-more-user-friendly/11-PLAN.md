---
phase: quick-11
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - resources/views/livewire/pages/shuffle-detail.blade.php
autonomous: true
requirements: [UX-STEP-CARDS, UX-ADD-FORM]

must_haves:
  truths:
    - "Each step card displays its position number (Step 1, Step 2, etc.)"
    - "Step cards show a human-readable conversion summary like '5 Ore -> 1-3 Gems'"
    - "The add-step form has clear section headers and visual grouping that reduces cognitive load"
    - "Yield configuration uses descriptive labels instead of terse abbreviations"
  artifacts:
    - path: "resources/views/livewire/pages/shuffle-detail.blade.php"
      provides: "Improved step editor UX"
      contains: "Step {{ $loopIndex + 1 }}"
  key_links: []
---

<objective>
Improve the shuffle step editor UX in the shuffle detail page. Two areas of focus: (1) make existing step cards more readable with step numbering and human-readable conversion ratios, and (2) improve the add-step form layout with better labels, section headers, and visual grouping.

Purpose: Reduce cognitive load when building and reviewing shuffle chains.
Output: Updated shuffle-detail.blade.php with improved step card and add-step form UX.
</objective>

<execution_context>
@/Users/lancethompson/.claude/get-shit-done/workflows/execute-plan.md
@/Users/lancethompson/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@.planning/STATE.md
@resources/views/livewire/pages/shuffle-detail.blade.php
</context>

<tasks>

<task type="auto">
  <name>Task 1: Add step numbering and conversion ratio summary to step cards</name>
  <files>resources/views/livewire/pages/shuffle-detail.blade.php</files>
  <action>
Modify the step card rendering (the `@foreach ($this->steps as $loopIndex => $step)` loop, around lines 510-684) with these changes:

1. **Add step number header** — Above the existing "Item Row" div (line 535), add a header row with:
   - Left side: "Step {{ $loopIndex + 1 }}" in `text-xs font-semibold uppercase tracking-wider text-gray-500` (matches existing section header patterns in the file)
   - Right side: The action buttons (move up/down, delete) — MOVE them from the bottom of the card (lines 642-672) up into this header row, aligned right with `ml-auto`
   - Separate the header from content with `mb-3` spacing

2. **Add human-readable conversion ratio summary** — Below the item row (after line 584), add a summary line that reads like a recipe. Use Alpine's existing `inputQty`, `min`, `max` reactive data:
   - Format: `<inputQty> <InputItemName> -> <min>-<max> <OutputItemName>` (e.g., "5 Rousing Earth -> 1-3 Awakened Earth")
   - When min === max (not range mode), show just the single number: "5 Rousing Earth -> 1 Awakened Earth"
   - Style: `text-xs text-gray-400` with a subtle `border-t border-gray-700/30 pt-2 mt-2` separator
   - Use `x-text` binding so it updates reactively when the user edits yield values
   - Template: `<span x-text="inputQty + ' {{ $step->inputCatalogItem?->name ?? 'Input' }} -> ' + (min === max ? min : min + '-' + max) + ' {{ $step->outputCatalogItem?->name ?? 'Output' }}'"></span>`

3. **Improve yield row labels** — In the yield row (lines 587-639):
   - Change "Input qty:" label to "Uses" (more natural language)
   - Change "Yield:" label to "Produces"
   - Add a right-arrow or "of" text between the input qty field and the yield field for flow: `Uses [5] -> Produces [1] to [3]`

No PHP backend changes. All changes are HTML/Tailwind/Alpine only.
  </action>
  <verify>
    <automated>cd /Users/lancethompson/Github/wow-ah-tracker && php artisan view:cache 2>&1 | tail -5</automated>
  </verify>
  <done>Step cards show "Step 1", "Step 2" etc. in header with action buttons moved to header row. Conversion ratio summary line appears below items. Yield labels use natural language ("Uses", "Produces").</done>
</task>

<task type="auto">
  <name>Task 2: Improve add-step form layout with sections and better labels</name>
  <files>resources/views/livewire/pages/shuffle-detail.blade.php</files>
  <action>
Modify the add-step form (the `@if ($addingStep)` block, around lines 701-900) with these changes:

1. **Section headers with visual grouping** — Break the form into two visually distinct sections:

   a. **Items section** — Wrap the existing input/output item search grid (lines 705-828) in a labeled group:
      - Add a small numbered label above: `<p class="mb-3 text-xs font-semibold uppercase tracking-wider text-gray-500">1. Choose Items</p>`
      - Change "Input Item" label to "What do you put in?" (`text-xs font-medium text-gray-400`)
      - Change "Output Item" label to "What do you get out?"
      - Keep existing search/selection UI unchanged (it already works well)

   b. **Yield section** — Wrap the yield configuration (lines 831-877) in a separate group:
      - Add divider: `<div class="mt-5 border-t border-gray-700/30 pt-5">`
      - Add numbered label: `<p class="mb-3 text-xs font-semibold uppercase tracking-wider text-gray-500">2. Set Conversion Rate</p>`
      - Change "Input qty:" to "How many input items?"
      - Change "Yield:" to "Produces"
      - Add helper text below the yield section: `<p class="mt-2 text-xs text-gray-600">Example: if 5 ore produces 1-3 gems, set input to 5 and yield to 1-3</p>`

2. **Form title** — Change existing "Add Conversion Step" h4 (line 703) to include step number context:
   - `Add Step {{ $this->steps->count() + 1 }}` — so users know where in the chain they are
   - Keep existing styling: `text-sm font-medium text-gray-100`

No PHP backend changes. All changes are HTML/Tailwind only.
  </action>
  <verify>
    <automated>cd /Users/lancethompson/Github/wow-ah-tracker && php artisan view:cache 2>&1 | tail -5 && php artisan test --filter=ShuffleStepEditorTest 2>&1 | tail -10</automated>
  </verify>
  <done>Add-step form shows numbered sections ("1. Choose Items", "2. Set Conversion Rate"), uses conversational labels, includes helper text example, and displays the upcoming step number in the form title.</done>
</task>

</tasks>

<verification>
- `php artisan view:cache` compiles without errors
- `php artisan test --filter=ShuffleStepEditorTest` passes (existing tests still work since no backend changes)
- Visual: Step cards show step numbers and conversion summaries
- Visual: Add-step form has clear sections and improved labels
</verification>

<success_criteria>
- Step cards display "Step 1", "Step 2", etc. with action buttons in header
- Each step card shows human-readable conversion ratio (e.g., "5 Ore -> 1-3 Gems")
- Yield row uses natural labels ("Uses", "Produces") instead of terse abbreviations
- Add-step form broken into numbered sections with descriptive labels
- Helper text guides new users on how to set conversion rates
- All existing step editor tests pass unchanged
</success_criteria>

<output>
After completion, create `.planning/quick/11-make-the-step-wizard-more-user-friendly/11-SUMMARY.md`
</output>
