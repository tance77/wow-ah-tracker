---
phase: quick-21
plan: 1
type: execute
wave: 1
depends_on: []
files_modified:
  - resources/views/livewire/pages/shuffles.blade.php
  - tests/Feature/ShuffleCrudTest.php
autonomous: true
requirements: [QUICK-21]
must_haves:
  truths:
    - "User can clone an existing shuffle from the shuffles list page"
    - "Cloned shuffle has a distinct name (e.g. 'Original Name (Copy)')"
    - "Cloned shuffle has all the same steps with same items, quantities, sort order"
    - "Cloned shuffle has all byproducts copied for each step"
    - "Cloned shuffle auto-watches all relevant items (input, output, byproducts)"
  artifacts:
    - path: "resources/views/livewire/pages/shuffles.blade.php"
      provides: "cloneShuffle Livewire action + Clone button in UI"
    - path: "tests/Feature/ShuffleCrudTest.php"
      provides: "Test coverage for clone behavior"
  key_links:
    - from: "shuffles.blade.php cloneShuffle()"
      to: "Shuffle model + ShuffleStep + ShuffleStepByproduct"
      via: "Eloquent create + relationship replication"
      pattern: "cloneShuffle"
---

<objective>
Add a "Clone" button to the shuffles list page that duplicates an existing shuffle with all its steps, byproducts, and auto-watched items.

Purpose: Users who create similar shuffles (e.g. same chain with different output items) should be able to clone an existing shuffle as a starting point rather than recreating from scratch.
Output: Clone action on shuffles page, tests proving correctness.
</objective>

<execution_context>
@/Users/lancethompson/.claude/get-shit-done/workflows/execute-plan.md
@/Users/lancethompson/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@resources/views/livewire/pages/shuffles.blade.php
@resources/views/livewire/pages/shuffle-detail.blade.php
@app/Models/Shuffle.php
@app/Models/ShuffleStep.php
@app/Models/ShuffleStepByproduct.php
@tests/Feature/ShuffleCrudTest.php

<interfaces>
From app/Models/Shuffle.php:
- fillable: user_id, name
- relations: user(), steps() (ordered by sort_order)

From app/Models/ShuffleStep.php:
- fillable: shuffle_id, input_blizzard_item_id, output_blizzard_item_id, input_qty, output_qty_min, output_qty_max, sort_order
- relations: shuffle(), inputCatalogItem(), outputCatalogItem(), byproducts()

From app/Models/ShuffleStepByproduct.php:
- fillable: shuffle_step_id, blizzard_item_id, item_name, chance_percent, quantity

From shuffle-detail.blade.php autoWatch():
- Uses auth()->user()->watchedItems()->firstOrCreate() with created_by_shuffle_id
</interfaces>
</context>

<tasks>

<task type="auto" tdd="true">
  <name>Task 1: Add cloneShuffle action and Clone button to shuffles list page</name>
  <files>resources/views/livewire/pages/shuffles.blade.php, tests/Feature/ShuffleCrudTest.php</files>
  <behavior>
    - Test: cloneShuffle creates a new shuffle named "{original} (Copy)" owned by the authenticated user
    - Test: cloned shuffle has the same number of steps with identical input/output items, quantities, and sort order
    - Test: cloned shuffle steps have all byproducts copied (same blizzard_item_id, item_name, chance_percent, quantity)
    - Test: cloned shuffle auto-watches all input, output, and byproduct items (WatchedItem with created_by_shuffle_id set to new shuffle)
    - Test: user cannot clone another user's shuffle (throws ModelNotFoundException)
    - Test: after clone, user is redirected to the new shuffle's detail page
  </behavior>
  <action>
    1. Add `cloneShuffle(int $id)` method to the Livewire component in `shuffles.blade.php`:
       - Find shuffle via `auth()->user()->shuffles()->findOrFail($id)` (ensures ownership)
       - Create new Shuffle with name = "{original->name} (Copy)" and user_id = auth()->id()
       - Load original shuffle's steps with byproducts: `$original->steps()->with('byproducts')->get()`
       - For each step, create a new ShuffleStep on the cloned shuffle with same field values (input_blizzard_item_id, output_blizzard_item_id, input_qty, output_qty_min, output_qty_max, sort_order)
       - For each step's byproducts, create new ShuffleStepByproduct records on the new step
       - Auto-watch all items: collect all unique blizzard_item_ids from steps (input + output) and byproducts, then for each call `auth()->user()->watchedItems()->firstOrCreate()` with created_by_shuffle_id = new shuffle id (same pattern as autoWatch in shuffle-detail.blade.php)
       - Redirect to the new shuffle's detail page

    2. Add a "Clone" button in the Actions column of the shuffles table, next to the Delete button. Use a duplicate/copy icon style consistent with the existing UI:
       - Place before the Delete button
       - Use `wire:click="cloneShuffle({{ $shuffle->id }})"` with `event.stopPropagation()` (same as delete button pattern)
       - Style: `text-sm text-gray-400 transition-colors hover:text-wow-gold focus:outline-none` (neutral color, gold on hover)
       - Text: "Clone"

    3. Add tests to ShuffleCrudTest.php following existing patterns (Volt::actingAs, factory creation).
  </action>
  <verify>
    <automated>cd /Users/lancethompson/Github/wow-ah-tracker && php artisan test --filter=ShuffleCrudTest</automated>
  </verify>
  <done>Clone button visible in shuffles list Actions column. Clicking it creates a complete duplicate shuffle (name, steps, byproducts, watched items) and redirects to the new shuffle detail page. All tests pass including ownership isolation.</done>
</task>

</tasks>

<verification>
- `php artisan test --filter=ShuffleCrudTest` passes all tests including new clone tests
- Clone button appears in the Actions column for each shuffle row
- Cloned shuffle has "(Copy)" suffix in name
- All steps and byproducts are duplicated correctly
</verification>

<success_criteria>
User can click "Clone" on any shuffle in the list, get a full copy with all steps/byproducts, and land on the new shuffle's detail page ready to edit.
</success_criteria>

<output>
After completion, create `.planning/quick/21-being-able-to-clone-a-shuffle-on-the-shu/21-SUMMARY.md`
</output>
