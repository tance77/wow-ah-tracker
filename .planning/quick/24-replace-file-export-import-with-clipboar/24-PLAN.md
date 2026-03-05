---
phase: quick-24
plan: 1
type: execute
wave: 1
depends_on: []
files_modified:
  - resources/views/livewire/pages/shuffles.blade.php
  - resources/views/livewire/pages/shuffle-detail.blade.php
  - tests/Feature/ShuffleCrudTest.php
autonomous: true
requirements: [QUICK-24]
must_haves:
  truths:
    - "Share button copies shuffle JSON to clipboard instead of downloading a file"
    - "Import Shuffle button opens a paste textarea/modal instead of file picker"
    - "Pasted JSON is parsed and imported identically to the old file import"
    - "Brief 'Copied!' feedback appears after clicking Share"
  artifacts:
    - path: "resources/views/livewire/pages/shuffles.blade.php"
      provides: "Clipboard copy for share, paste modal for import"
    - path: "resources/views/livewire/pages/shuffle-detail.blade.php"
      provides: "Clipboard copy for share on detail page"
    - path: "tests/Feature/ShuffleCrudTest.php"
      provides: "Updated tests for new import flow"
  key_links:
    - from: "shuffles.blade.php Share button"
      to: "exportShuffle() method"
      via: "Returns JSON string instead of stream download, JS copies to clipboard"
    - from: "shuffles.blade.php Import button"
      to: "importShuffle() method"
      via: "Accepts string property instead of file upload"
---

<objective>
Replace file-based shuffle export/import with clipboard copy/paste.

Purpose: Simpler sharing UX -- click Share to copy JSON to clipboard, click Import Shuffle to open a paste textarea modal. No file downloads or uploads.
Output: Both shuffles list and shuffle detail pages use clipboard-based sharing.
</objective>

<execution_context>
@/Users/lancethompson/.claude/get-shit-done/workflows/execute-plan.md
@/Users/lancethompson/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@resources/views/livewire/pages/shuffles.blade.php
@resources/views/livewire/pages/shuffle-detail.blade.php
@tests/Feature/ShuffleCrudTest.php
</context>

<tasks>

<task type="auto">
  <name>Task 1: Convert Share to clipboard copy on both pages</name>
  <files>resources/views/livewire/pages/shuffles.blade.php, resources/views/livewire/pages/shuffle-detail.blade.php</files>
  <action>
On BOTH pages, change the `exportShuffle()` PHP method:
- Instead of returning a `response()->streamDownload(...)`, build the same JSON data array but return it as a plain string: `return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);`
- Actually, since Livewire cannot easily return raw strings from wire:click for JS consumption, use a different approach: Keep `exportShuffle()` but have it set a public property `$exportedJson` with the JSON string, then dispatch a browser event. OR better yet, use Alpine.js to handle it entirely on the frontend side.

**Recommended approach (cleanest):**

1. In `shuffles.blade.php`:
   - Change `exportShuffle(int $id)` to no longer return a streamDownload. Instead, have it build the JSON string and dispatch a Livewire browser event: `$this->dispatch('shuffle-copied', json: json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));`
   - In the Blade template, add an Alpine listener at the component root div: `x-data="{ copiedId: null }" @shuffle-copied.window="navigator.clipboard.writeText($event.detail.json); copiedId = {{ SHUFFLE_ID }}; setTimeout(() => copiedId = null, 2000)"`
   - Actually, a simpler approach: have `exportShuffle()` return the JSON string, and use `wire:click` with Alpine to copy. But Livewire methods called via wire:click don't easily return values to JS.

   **Best approach:** Change `exportShuffle(int $id)` to set a public property `public string $lastExportedJson = '';` and `public ?int $lastExportedId = null;`. After setting them, the Blade can react. But this is clunky.

   **Simplest clean approach:**
   - Keep `exportShuffle(int $id)` but change it to dispatch a browser event instead of returning a download:
     ```php
     public function exportShuffle(int $id): void
     {
         $shuffle = auth()->user()->shuffles()->findOrFail($id);
         $shuffle->load(['steps.inputCatalogItem', 'steps.outputCatalogItem', 'steps.byproducts']);
         // ... same $data building logic ...
         $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
         $this->dispatch('shuffle-exported', json: $json, shuffleId: $id);
     }
     ```
   - On the component root div, add Alpine:
     ```
     x-data="{ copiedId: null }"
     x-on:shuffle-exported.window="
         navigator.clipboard.writeText($event.detail[0].json);
         copiedId = $event.detail[0].shuffleId;
         setTimeout(() => copiedId = null, 2000)
     "
     ```
   - Change each Share button to show "Copied!" feedback when `copiedId` matches:
     ```html
     <button wire:click="exportShuffle({{ $shuffle->id }})" ...>
         <span x-show="copiedId !== {{ $shuffle->id }}">Share</span>
         <span x-show="copiedId === {{ $shuffle->id }}" x-cloak class="text-green-400">Copied!</span>
     </button>
     ```
   - Remove `use Livewire\WithFileUploads;` trait and `public $importFile = null;` property since file uploads are no longer needed.

2. In `shuffle-detail.blade.php`:
   - Same pattern: change `exportShuffle()` to dispatch event instead of streamDownload. Use `$this->shuffle` directly (no $id param needed, it already uses `$this->shuffle`).
     ```php
     public function exportShuffle(): void
     {
         $this->shuffle->load(['steps.inputCatalogItem', 'steps.outputCatalogItem', 'steps.byproducts']);
         // ... same $data building ...
         $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
         $this->dispatch('shuffle-exported', json: $json);
     }
     ```
   - Add Alpine on the outermost div wrapping the header area:
     ```
     x-data="{ copied: false }"
     x-on:shuffle-exported.window="
         navigator.clipboard.writeText($event.detail[0].json);
         copied = true;
         setTimeout(() => copied = false, 2000)
     "
     ```
   - Change the Share button text to toggle "Copied!" feedback:
     ```html
     <button wire:click="exportShuffle" ...>
         <span x-show="!copied">Share</span>
         <span x-show="copied" x-cloak class="text-green-400">Copied!</span>
     </button>
     ```
   - No import functionality exists on detail page, so no changes needed there.
  </action>
  <verify>
    <automated>cd /Users/lancethompson/Github/wow-ah-tracker && grep -c "streamDownload" resources/views/livewire/pages/shuffles.blade.php resources/views/livewire/pages/shuffle-detail.blade.php | grep -v ":0" | wc -l | xargs test 0 -eq && echo "PASS: no streamDownload references" || echo "FAIL: streamDownload still present"</automated>
  </verify>
  <done>Share button on both pages dispatches browser event with JSON, Alpine copies to clipboard and shows "Copied!" feedback for 2 seconds. No file download occurs.</done>
</task>

<task type="auto">
  <name>Task 2: Replace file upload import with paste modal on shuffles list</name>
  <files>resources/views/livewire/pages/shuffles.blade.php</files>
  <action>
Replace the file-upload-based import with a paste modal:

1. **Change the PHP method** `importShuffle()`:
   - Remove the `$importFile` property and `WithFileUploads` trait entirely.
   - Add a new public property: `public string $importJson = '';`
   - Add a boolean: `public bool $showImportModal = false;`
   - Add method `openImportModal(): void { $this->showImportModal = true; $this->importJson = ''; $this->resetErrorBag('importJson'); }`
   - Add method `closeImportModal(): void { $this->showImportModal = false; $this->importJson = ''; $this->resetErrorBag('importJson'); }`
   - Change `importShuffle()` to read from `$this->importJson` instead of `$this->importFile`:
     ```php
     public function importShuffle(): void
     {
         $json = trim($this->importJson);
         if (empty($json)) {
             $this->addError('importJson', 'Please paste shuffle JSON to import.');
             return;
         }
         $data = json_decode($json, true);
         if (!is_array($data) || !isset($data['name']) || !is_string($data['name']) || !isset($data['steps']) || !is_array($data['steps'])) {
             $this->addError('importJson', 'Invalid shuffle JSON format. Must contain "name" and "steps".');
             return;
         }
         // ... rest of import logic stays exactly the same (create shuffle, steps, byproducts, auto-watch) ...
         $this->closeImportModal();
         $this->redirect(route('shuffles.show', $shuffle), navigate: true);
     }
     ```

2. **Replace the Import Shuffle button** (appears in both empty state and non-empty state sections):
   - In both locations, replace the `<label>` with file input with a simple button:
     ```html
     <button
         wire:click="openImportModal"
         class="rounded-md border border-gray-600 px-4 py-2 text-sm font-medium text-gray-300 transition-colors hover:border-wow-gold hover:text-wow-gold focus:outline-none"
     >
         Import Shuffle
     </button>
     ```

3. **Add the import modal** at the bottom of the component (before closing `</div>`), using the same `<x-modal>` pattern used for delete confirmations:
   ```html
   <x-modal name="import-shuffle" focusable>
       <div class="p-6" x-data @shuffle-import-close.window="$dispatch('close')">
           <h2 class="text-lg font-medium text-gray-100">Import Shuffle</h2>
           <p class="mt-2 text-sm text-gray-400">
               Paste the shuffle JSON below to import it.
           </p>
           <textarea
               wire:model="importJson"
               rows="10"
               class="mt-4 w-full rounded-md border border-gray-600 bg-wow-darker px-3 py-2 font-mono text-sm text-gray-200 placeholder-gray-500 focus:border-wow-gold focus:outline-none focus:ring-1 focus:ring-wow-gold"
               placeholder='Paste shuffle JSON here...'
           ></textarea>
           @error('importJson')
               <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
           @enderror
           <div class="mt-4 flex justify-end gap-3">
               <button
                   x-on:click="$dispatch('close')"
                   wire:click="closeImportModal"
                   class="rounded-md border border-gray-600 px-4 py-2 text-sm font-medium text-gray-300 transition-colors hover:border-gray-500 hover:text-gray-200 focus:outline-none"
               >
                   Cancel
               </button>
               <button
                   wire:click="importShuffle"
                   class="rounded-md bg-wow-gold px-4 py-2 text-sm font-semibold text-wow-darker transition-colors hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-wow-gold focus:ring-offset-2 focus:ring-offset-wow-dark"
               >
                   Import
               </button>
           </div>
       </div>
   </x-modal>
   ```

   Actually, since `showImportModal` is a Livewire property, use Livewire's approach. But looking at existing code, the project uses `<x-modal name="...">` with `$dispatch('open-modal', 'name')`. So:
   - Change the Import Shuffle buttons to: `@click="$dispatch('open-modal', 'import-shuffle')"`
   - The `openImportModal` method just resets state: `$this->importJson = ''; $this->resetErrorBag('importJson');`
   - Call `openImportModal` via wire:click alongside the Alpine dispatch, or just call it as a standalone Livewire method. Simplest: `wire:click="openImportModal" x-on:click="$dispatch('open-modal', 'import-shuffle')"` — but wire:click and x-on:click on same element can conflict. Instead, just have the button use `x-data @click="$dispatch('open-modal', 'import-shuffle'); $wire.openImportModal()"`.

   Remove the `public bool $showImportModal` property since modal visibility is handled by Alpine `<x-modal>`. Keep `openImportModal()` as a reset method and `closeImportModal()` to just reset state.

   On successful import, dispatch close: in `importShuffle()` after success, before redirect, call `$this->dispatch('close-modal', 'import-shuffle');` — but since we redirect immediately, the modal closes anyway. Just clean up state and redirect.
  </action>
  <verify>
    <automated>cd /Users/lancethompson/Github/wow-ah-tracker && grep -c "type=\"file\"" resources/views/livewire/pages/shuffles.blade.php | xargs test 0 -eq && echo "PASS: no file inputs" || echo "FAIL: file input still present"</automated>
  </verify>
  <done>Import Shuffle button opens a modal with a textarea. User pastes JSON, clicks Import, shuffle is created with all steps/byproducts/auto-watch. No file upload anywhere. Error messages shown for empty or invalid JSON.</done>
</task>

<task type="auto">
  <name>Task 3: Update tests for new clipboard/paste flow</name>
  <files>tests/Feature/ShuffleCrudTest.php</files>
  <action>
Update the existing export/import tests in `tests/Feature/ShuffleCrudTest.php`:

1. **Export test** (`exportShuffle returns JSON with correct structure`):
   - The method now dispatches a `shuffle-exported` event instead of returning a download response.
   - Change assertion: instead of checking response headers/content, assert the event was dispatched with correct JSON structure.
   - Use Livewire's `assertDispatched('shuffle-exported')` and check the event payload contains valid JSON with name, version, and steps.
   ```php
   test('exportShuffle dispatches shuffle-exported event with correct JSON', function () {
       $user = User::factory()->create();
       $shuffle = Shuffle::factory()->create(['user_id' => $user->id, 'name' => 'Export Test']);
       // ... create steps and byproducts same as before ...
       $component = Livewire::actingAs($user)
           ->test('pages.shuffles')
           ->call('exportShuffle', $shuffle->id);
       $component->assertDispatched('shuffle-exported', function ($name, ...$params) {
           $json = $params[0]['json'] ?? $params['json'] ?? '';
           $data = json_decode($json, true);
           return $data !== null
               && $data['name'] === 'Export Test'
               && isset($data['steps'])
               && count($data['steps']) === 1;
       });
   });
   ```

2. **Import test** (`importShuffle with valid JSON creates shuffle`):
   - Change from setting `importFile` (UploadedFile) to setting `importJson` (string).
   - Remove any `UploadedFile::fake()` usage for import tests.
   ```php
   test('importShuffle with valid JSON string creates shuffle with steps and byproducts', function () {
       $user = User::factory()->create();
       $json = json_encode([
           'name' => 'Imported Shuffle',
           'version' => 1,
           'steps' => [/* same test data as before */],
       ]);
       Livewire::actingAs($user)
           ->test('pages.shuffles')
           ->set('importJson', $json)
           ->call('importShuffle')
           ->assertRedirect();
       // ... same assertions about created shuffle, steps, byproducts ...
   });
   ```

3. **Import auto-watch test**: Same change -- use `importJson` string property instead of `importFile`.

4. **Malformed import test** (`importShuffle with malformed JSON`):
   - Change from uploading a file to setting `importJson` with invalid content.
   - Assert error on `importJson` key instead of `importFile`.

5. Remove any `use Illuminate\Http\UploadedFile;` import if it was only used for shuffle import tests (check if other tests need it first).
  </action>
  <verify>
    <automated>cd /Users/lancethompson/Github/wow-ah-tracker && php artisan test --filter=ShuffleCrud 2>&1 | tail -20</automated>
  </verify>
  <done>All shuffle CRUD tests pass. Export test verifies event dispatch with correct JSON payload. Import tests use string property instead of file upload. Malformed JSON test checks importJson error bag.</done>
</task>

</tasks>

<verification>
- `grep -r "streamDownload" resources/views/livewire/pages/shuffle` returns no matches
- `grep -r "WithFileUploads" resources/views/livewire/pages/shuffles.blade.php` returns no matches
- `grep -r "type=\"file\"" resources/views/livewire/pages/shuffles.blade.php` returns no matches
- `php artisan test --filter=ShuffleCrud` -- all tests pass
- Share button markup includes "Copied!" feedback text
- Import modal has textarea element
</verification>

<success_criteria>
- Share button on shuffles list copies JSON to clipboard and shows "Copied!" for 2s
- Share button on shuffle detail page does the same
- Import Shuffle button opens a modal with paste textarea
- Pasting valid JSON and clicking Import creates the shuffle with all steps, byproducts, and auto-watched items
- Invalid/empty JSON shows error message in modal
- No file download or file upload logic remains
- All tests pass
</success_criteria>

<output>
After completion, create `.planning/quick/24-replace-file-export-import-with-clipboar/24-SUMMARY.md`
</output>
