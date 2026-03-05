---
phase: quick-22
plan: 1
type: execute
wave: 1
depends_on: []
files_modified:
  - resources/views/livewire/pages/shuffles.blade.php
  - resources/views/livewire/pages/shuffle-detail.blade.php
  - tests/Feature/ShuffleCrudTest.php
autonomous: true
requirements: [QUICK-22]
must_haves:
  truths:
    - "User can export a shuffle as a JSON file from the shuffles list or detail page"
    - "User can import a JSON file to create a new shuffle with all steps and byproducts"
    - "Imported shuffle auto-watches all referenced items"
    - "JSON format is human-readable with item names for reference"
  artifacts:
    - path: "resources/views/livewire/pages/shuffles.blade.php"
      provides: "Export button per shuffle row, Import button, importShuffle method, exportShuffle method"
    - path: "resources/views/livewire/pages/shuffle-detail.blade.php"
      provides: "Export button on detail page"
    - path: "tests/Feature/ShuffleCrudTest.php"
      provides: "Tests for export JSON structure and import creating shuffle with steps/byproducts"
  key_links:
    - from: "shuffles.blade.php exportShuffle"
      to: "Shuffle model with steps.byproducts eager load"
      via: "JSON response download"
      pattern: "response.*json"
    - from: "shuffles.blade.php importShuffle"
      to: "Shuffle/ShuffleStep/ShuffleStepByproduct creation + WatchedItem auto-watch"
      via: "JSON parse and create"
      pattern: "json_decode.*steps.*create"
---

<objective>
Add export and import functionality for shuffles as JSON files, enabling users to share shuffle configurations between accounts.

Purpose: Users can share profitable shuffle chains with friends or transfer configurations between accounts without manually recreating multi-step shuffles.
Output: Export/Import buttons on shuffles list page, export button on detail page, JSON file download/upload with full shuffle data.
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
</context>

<interfaces>
<!-- Key types and contracts the executor needs. -->

From app/Models/Shuffle.php:
- fillable: user_id, name
- relations: steps() -> HasMany ShuffleStep (ordered by sort_order)

From app/Models/ShuffleStep.php:
- fillable: shuffle_id, input_blizzard_item_id, output_blizzard_item_id, input_qty, output_qty_min, output_qty_max, sort_order
- relations: inputCatalogItem(), outputCatalogItem(), byproducts()

From app/Models/ShuffleStepByproduct.php:
- fillable: shuffle_step_id, blizzard_item_id, item_name, chance_percent, quantity

From shuffles.blade.php (Volt component):
- Already has: createShuffle(), renameShuffle(), deleteShuffle(), cloneShuffle()
- Actions column has Clone and Delete buttons per row
</interfaces>

<tasks>

<task type="auto" tdd="true">
  <name>Task 1: Add export and import methods to shuffles Volt component with tests</name>
  <files>resources/views/livewire/pages/shuffles.blade.php, tests/Feature/ShuffleCrudTest.php</files>
  <behavior>
    - Test: exportShuffle returns JSON with correct structure (name, steps array with input/output item IDs, item names, quantities, byproducts)
    - Test: importShuffle with valid JSON creates shuffle with correct name, steps, byproducts, and sort order
    - Test: importShuffle auto-watches all referenced blizzard_item_ids (inputs, outputs, byproducts)
    - Test: importShuffle with invalid/malformed JSON does not create shuffle and shows validation error
  </behavior>
  <action>
1. Add tests to tests/Feature/ShuffleCrudTest.php:
   - Test exportShuffle(id) returns a Symfony StreamedResponse with JSON content type and correct filename ({shuffle-name}.json). Verify JSON structure contains: name (string), steps (array of objects with: input_blizzard_item_id, input_item_name, output_blizzard_item_id, output_item_name, input_qty, output_qty_min, output_qty_max, sort_order, byproducts array). Use CatalogItem factory for item name resolution.
   - Test importShuffle with a valid UploadedFile JSON creates a new Shuffle owned by auth user with matching name + " (Imported)", all steps with correct field values, all byproducts, and auto-watched items via WatchedItem::firstOrCreate (same pattern as cloneShuffle).
   - Test importShuffle with malformed JSON (missing "steps" key) does not create any Shuffle.

2. Add `exportShuffle(int $id)` method to the shuffles.blade.php Volt component:
   - Load shuffle with steps.inputCatalogItem, steps.outputCatalogItem, steps.byproducts eager loading
   - Build JSON structure:
     ```json
     {
       "name": "Shuffle Name",
       "version": 1,
       "steps": [
         {
           "input_blizzard_item_id": 12345,
           "input_item_name": "Ore Name",
           "output_blizzard_item_id": 67890,
           "output_item_name": "Bar Name",
           "input_qty": 2,
           "output_qty_min": 1,
           "output_qty_max": 1,
           "sort_order": 0,
           "byproducts": [
             {
               "blizzard_item_id": 11111,
               "item_name": "Dust",
               "chance_percent": "50.00",
               "quantity": 1
             }
           ]
         }
       ]
     }
     ```
   - Return `response()->streamDownload()` with JSON content, filename: Str::slug($shuffle->name) . '.json', Content-Type: application/json

3. Add `importShuffle()` method with a `$importFile` property (Livewire file upload using WithFileUploads trait):
   - Add `use Livewire\WithFileUploads;` trait and `public $importFile = null;` property
   - Validate file is .json, max 1MB
   - json_decode the file contents, validate structure has "name" (string) and "steps" (array)
   - Create Shuffle with name = "{json.name} (Imported)"
   - Loop through steps, create ShuffleStep for each, create ShuffleStepByproduct for each byproduct
   - Auto-watch all unique blizzard_item_ids using the same firstOrCreate pattern from cloneShuffle
   - Reset importFile, redirect to new shuffle detail page
   - On validation/parse failure, add Livewire validation error

4. Add UI to shuffles.blade.php:
   - Add "Export" button in the Actions column next to Clone and Delete (same text-gray-400 hover:text-wow-gold styling)
   - Add "Import Shuffle" button next to the "New Shuffle" button in the header area
   - Import uses a hidden file input triggered by the Import button click, with wire:model="importFile" and an Alpine x-data watcher that calls $wire.importShuffle() when file is selected
   - Also add Import button in the empty state section
  </action>
  <verify>
    <automated>cd /Users/lancethompson/Github/wow-ah-tracker && php artisan test --filter=ShuffleCrudTest</automated>
  </verify>
  <done>Export returns valid JSON download with all shuffle data. Import creates complete shuffle from JSON file with auto-watched items. Tests pass for export structure, import creation, and malformed input rejection.</done>
</task>

<task type="auto">
  <name>Task 2: Add export button to shuffle detail page</name>
  <files>resources/views/livewire/pages/shuffle-detail.blade.php</files>
  <action>
1. Add an `exportShuffle()` method to the shuffle-detail.blade.php Volt component:
   - Same JSON structure and streamDownload logic as the shuffles list page export
   - Uses `$this->shuffle` (already available via mount) with steps.inputCatalogItem, steps.outputCatalogItem, steps.byproducts eager load
   - Return response()->streamDownload() with JSON content

2. Add an "Export JSON" button in the shuffle detail page header area (near the shuffle name at the top). Style it as a secondary action: `text-sm text-gray-400 hover:text-wow-gold transition-colors` (consistent with existing action button styles on this page). Place it in the header slot area or next to the shuffle name.
  </action>
  <verify>
    <automated>cd /Users/lancethompson/Github/wow-ah-tracker && php artisan test --filter=ShuffleCrudTest</automated>
  </verify>
  <done>Shuffle detail page has an Export JSON button that downloads the shuffle as a .json file with all steps and byproducts.</done>
</task>

</tasks>

<verification>
- Export a shuffle from list page -> downloads .json file with name, version, steps, byproducts
- Import a .json file -> creates new shuffle with "(Imported)" suffix, all steps and byproducts recreated
- Import auto-watches all item IDs referenced in the shuffle
- Export from detail page works identically to list page export
- Malformed JSON shows error, does not create shuffle
- All existing shuffle CRUD tests still pass
</verification>

<success_criteria>
- Users can export any shuffle as a downloadable JSON file from both the list and detail pages
- Users can import a JSON file to create a new shuffle with all steps, byproducts, and auto-watched items
- JSON format includes item names for human readability alongside blizzard_item_ids for machine import
- Invalid JSON files are rejected gracefully with user feedback
</success_criteria>

<output>
After completion, create `.planning/quick/22-export-and-import-shuffles-as-json-for-s/22-SUMMARY.md`
</output>
