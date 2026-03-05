---
phase: quick-16
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - database/migrations/2026_03_06_000001_create_shuffle_step_byproducts_table.php
  - app/Models/ShuffleStepByproduct.php
  - app/Models/ShuffleStep.php
  - app/Models/Shuffle.php
  - database/factories/ShuffleStepByproductFactory.php
  - resources/views/livewire/pages/shuffle-detail.blade.php
autonomous: true
requirements: [QUICK-16]

must_haves:
  truths:
    - "Each shuffle step can have zero or more byproducts with item, chance %, and quantity"
    - "Byproduct items are auto-watched for price polling when added"
    - "Batch calculator includes byproduct expected value in gross output"
    - "Byproducts can be added and removed from the step editor UI"
  artifacts:
    - path: "database/migrations/2026_03_06_000001_create_shuffle_step_byproducts_table.php"
      provides: "shuffle_step_byproducts table"
      contains: "shuffle_step_id"
    - path: "app/Models/ShuffleStepByproduct.php"
      provides: "Byproduct model with ShuffleStep relationship"
      exports: ["ShuffleStepByproduct"]
    - path: "database/factories/ShuffleStepByproductFactory.php"
      provides: "Test factory for byproducts"
  key_links:
    - from: "app/Models/ShuffleStep.php"
      to: "app/Models/ShuffleStepByproduct.php"
      via: "hasMany relationship"
      pattern: "hasMany.*ShuffleStepByproduct"
    - from: "resources/views/livewire/pages/shuffle-detail.blade.php"
      to: "ShuffleStepByproduct"
      via: "Livewire methods addByproduct/removeByproduct"
      pattern: "addByproduct|removeByproduct"
    - from: "batchCalculator JS"
      to: "byproduct price data"
      via: "expected value sum in grossValue getters"
      pattern: "byproduct.*chance|expected"
---

<objective>
Add secondary byproducts with drop chance to shuffle steps. Each step can have multiple byproducts (item + chance % + quantity). Byproducts are auto-watched for price polling and their expected value (price * chance/100 * qty) factors into the batch profit calculator.

Purpose: More accurate profit calculation by accounting for valuable secondary drops (e.g., rare pigment from milling).
Output: Migration, model, factory, UI for managing byproducts, and updated calculator.
</objective>

<execution_context>
@.planning/quick/16-add-secondary-byproducts-with-drop-chanc/16-PLAN.md
</execution_context>

<context>
@.planning/STATE.md
@app/Models/ShuffleStep.php
@app/Models/Shuffle.php
@app/Models/ShuffleStepByproduct.php (will be created)
@resources/views/livewire/pages/shuffle-detail.blade.php
@database/factories/ShuffleStepFactory.php

<interfaces>
<!-- Key types and contracts the executor needs -->

From app/Models/ShuffleStep.php:
```php
class ShuffleStep extends Model {
    protected $fillable = ['shuffle_id', 'input_blizzard_item_id', 'output_blizzard_item_id', 'input_qty', 'output_qty_min', 'output_qty_max', 'sort_order'];
    public function shuffle(): BelongsTo;
    public function inputCatalogItem(): BelongsTo;
    public function outputCatalogItem(): BelongsTo;
    // boot() has orphan cleanup for auto-watched items on delete
}
```

From app/Models/Shuffle.php:
```php
class Shuffle extends Model {
    public function steps(): HasMany;
    public function profitPerUnit(): ?int; // Cascades yield, uses first input + last output prices
    // boot() deleting handler cleans orphan watched items
}
```

From shuffle-detail.blade.php Livewire component:
```php
// Key computed properties:
public function steps(): Collection; // with(['inputCatalogItem', 'outputCatalogItem'])
public function priceData(): array; // keyed by blizzard_item_id => {price, polled_at, age_minutes, stale, item_name}
public function calculatorSteps(): array; // {id, input_id, output_id, input_qty, output_qty_min, output_qty_max, input_name, output_name, input_icon, output_icon}
private function autoWatch(int $blizzardItemId): void; // firstOrCreate WatchedItem with created_by_shuffle_id
```

From batchCalculator Alpine.js:
```javascript
function batchCalculator(prices, steps) {
    // cascade: cascades qty through steps using input_qty ratios
    // grossValueMin/Max: lastOutputId price * cascaded qty
    // netProfitMin/Max: grossValue * 0.95 - totalCost
    // canCalculate: needs first input + last output prices
}
```
</interfaces>
</context>

<tasks>

<task type="auto">
  <name>Task 1: Migration, model, factory, and ShuffleStep relationship</name>
  <files>
    database/migrations/2026_03_06_000001_create_shuffle_step_byproducts_table.php,
    app/Models/ShuffleStepByproduct.php,
    app/Models/ShuffleStep.php,
    app/Models/Shuffle.php,
    database/factories/ShuffleStepByproductFactory.php
  </files>
  <action>
    1. Create migration `2026_03_06_000001_create_shuffle_step_byproducts_table.php`:
       - `id` (auto-increment)
       - `shuffle_step_id` foreignId constrained cascadeOnDelete
       - `blizzard_item_id` unsignedBigInteger (the byproduct item)
       - `item_name` string (denormalized for display — same pattern as WatchedItem)
       - `chance_percent` decimal(5,2) (e.g., 20.00 for 20%)
       - `quantity` unsignedInteger default 1
       - `timestamps`
       - Index on `shuffle_step_id`

    2. Create `app/Models/ShuffleStepByproduct.php`:
       - `declare(strict_types=1)`
       - `use HasFactory`
       - `$fillable`: shuffle_step_id, blizzard_item_id, item_name, chance_percent, quantity
       - `$casts`: blizzard_item_id => integer, chance_percent => decimal:2, quantity => integer
       - `step(): BelongsTo` -> ShuffleStep
       - `catalogItem(): BelongsTo` -> CatalogItem via blizzard_item_id foreign key (same pattern as ShuffleStep's inputCatalogItem)

    3. Add to `ShuffleStep.php`:
       - Import `use Illuminate\Database\Eloquent\Relations\HasMany;`
       - Add `byproducts(): HasMany` returning `$this->hasMany(ShuffleStepByproduct::class)`
       - Update the `boot()` deleted handler: in addition to checking input/output item IDs, also collect all byproduct blizzard_item_ids from the step being deleted. For each byproduct item ID, check if it's still referenced by any other ShuffleStep (input/output) OR any other ShuffleStepByproduct. If not, delete the auto-watched item.

    4. Update `Shuffle.php` `profitPerUnit()` method:
       - Eager-load byproducts with their catalog items and price snapshots: add `'steps.byproducts.catalogItem.priceSnapshots'` to the eager load (with same `latest('polled_at')->limit(1)` constraint on priceSnapshots)
       - After computing `$grossOutput` from the last step's main output, add byproduct expected value: for each step, for each byproduct, add `byproduct_price * (chance_percent / 100) * quantity * cascaded_output_qty_for_that_step`. Use the conservative cascaded qty (min yield) up to that step. Sum all byproduct EV into `$grossOutput` before applying the 5% AH cut.
       - The cascaded qty for step N is the output qty after cascading through steps 0..N. Track this in the existing loop.

    5. Create `database/factories/ShuffleStepByproductFactory.php`:
       - Follow exact pattern of ShuffleStepFactory
       - `$model = ShuffleStepByproduct::class`
       - Default: shuffle_step_id => ShuffleStep::factory(), blizzard_item_id => random int, item_name => fake word, chance_percent => 20.00, quantity => 1

    6. Run `php artisan migrate` to apply the migration.
  </action>
  <verify>
    <automated>cd /Users/lancethompson/Github/wow-ah-tracker && php artisan migrate --force && php artisan tinker --execute="echo App\Models\ShuffleStepByproduct::query()->toSql();"</automated>
  </verify>
  <done>
    - shuffle_step_byproducts table exists with correct schema
    - ShuffleStepByproduct model works with relationships
    - ShuffleStep->byproducts() hasMany relationship works
    - ShuffleStepByproductFactory can create instances
    - Shuffle::profitPerUnit() includes byproduct expected value
    - Orphan cleanup in ShuffleStep boot handles byproduct watched items
  </done>
</task>

<task type="auto">
  <name>Task 2: Byproduct UI in step editor and calculator integration</name>
  <files>resources/views/livewire/pages/shuffle-detail.blade.php</files>
  <action>
    This task modifies the shuffle-detail Livewire Volt component to support byproduct CRUD and calculator integration.

    **Livewire PHP section changes:**

    1. Add component properties for the byproduct add form:
       - `public string $byproductSearch = '';`
       - `public ?int $selectedByproductItemId = null;`
       - `public ?string $selectedByproductName = null;`
       - `public float $newByproductChance = 100;`
       - `public int $newByproductQty = 1;`
       - `public ?int $addingByproductForStep = null;` (which step ID is showing the add form)

    2. Add `byproductSuggestions()` computed property — same pattern as `inputSuggestions()` but using `$this->byproductSearch`.

    3. Add `selectByproductItem(int $blizzardItemId, string $name)` method — same pattern as selectInputItem.

    4. Add `addByproduct(int $stepId)` method:
       - Find step via `$this->shuffle->steps()->findOrFail($stepId)`
       - Validate: selectedByproductItemId is not null, newByproductChance between 0.01 and 100, newByproductQty >= 1
       - Create ShuffleStepByproduct with blizzard_item_id, item_name (from CatalogItem lookup or selectedByproductName), chance_percent, quantity
       - Call `$this->autoWatch($this->selectedByproductItemId)` for price polling
       - Reset form state (selectedByproductItemId = null, etc., addingByproductForStep = null)
       - Unset computed caches: steps, priceData, calculatorSteps

    5. Add `removeByproduct(int $byproductId)` method:
       - Find byproduct, get its blizzard_item_id before deleting
       - Delete the byproduct record
       - Check if blizzard_item_id is still referenced by any ShuffleStep (input/output) or ShuffleStepByproduct; if not, delete auto-watched item
       - Unset computed caches

    6. Update `steps()` computed: add `'byproducts'` to the `with()` eager load: `->with(['inputCatalogItem', 'outputCatalogItem', 'byproducts'])`

    7. Update `priceData()` computed: collect byproduct blizzard_item_ids in addition to input/output IDs:
       ```php
       $itemIds = $this->steps
           ->flatMap(fn ($step) => array_merge(
               [$step->input_blizzard_item_id, $step->output_blizzard_item_id],
               $step->byproducts->pluck('blizzard_item_id')->all()
           ))
           ->unique()
           ->values();
       ```

    8. Update `calculatorSteps()` computed: add byproducts array to each step:
       ```php
       return $this->steps->map(fn ($step) => [
           // ...existing fields...
           'byproducts' => $step->byproducts->map(fn ($bp) => [
               'blizzard_item_id' => $bp->blizzard_item_id,
               'item_name' => $bp->item_name,
               'chance_percent' => (float) $bp->chance_percent,
               'quantity' => $bp->quantity,
           ])->toArray(),
       ])->toArray();
       ```

    **Blade template changes — Step card byproduct section:**

    9. After the yield row (after the `</div>` that closes the flex-wrap items-center gap-4 yield row, around line 679), add a byproduct section for each step:

       ```blade
       <!-- Byproducts Section -->
       @if ($step->byproducts->isNotEmpty() || $addingByproductForStep === $step->id)
           <div class="mt-3 border-t border-gray-700/30 pt-3">
               <div class="mb-2 flex items-center justify-between">
                   <span class="text-xs font-semibold uppercase tracking-wider text-gray-500">Byproducts</span>
               </div>
       ```

       For each existing byproduct, show a compact row:
       - Item name, chance % badge (e.g., "20%"), quantity badge (e.g., "x1")
       - Small red X button to remove: `wire:click="removeByproduct({{ $bp->id }})"`

       If `$addingByproductForStep === $step->id`, show inline add form:
       - Item search input (wire:model.live.debounce.300ms="byproductSearch") with dropdown suggestions (same pattern as input/output search)
       - Selected item display with clear button
       - Chance % number input (wire:model="newByproductChance", min 0.01, max 100, step 0.01)
       - Quantity number input (wire:model="newByproductQty", min 1)
       - Add button: `wire:click="addByproduct({{ $step->id }})"`
       - Cancel button: `wire:click="$set('addingByproductForStep', null)"`

    10. Add a small "+ Byproduct" button below the yield row (visible when not already adding):
        ```blade
        <button wire:click="$set('addingByproductForStep', {{ $step->id }})"
                class="mt-2 text-xs text-gray-500 hover:text-wow-gold">
            + Byproduct
        </button>
        ```
        Only show if `$addingByproductForStep !== $step->id`.

    **Alpine.js calculator changes:**

    11. Update the `batchCalculator` function to include byproduct expected value:

        In the `grossValueMin` getter, after the existing last-output calculation, sum up byproduct EV across ALL steps:
        ```javascript
        get grossValueMin() {
            if (!this.canCalculate) return 0;
            const lastOutputId = this.steps[this.steps.length - 1].output_id;
            let gross = this._cascadedQtyMin * (this.prices[lastOutputId]?.price ?? 0);

            // Add byproduct expected value per step
            let cascadedQty = this.batchQty;
            for (const step of this.steps) {
                const stepOutputQty = Math.floor(cascadedQty * step.output_qty_min / Math.max(1, step.input_qty));
                for (const bp of (step.byproducts || [])) {
                    const bpPrice = this.prices[bp.blizzard_item_id]?.price ?? 0;
                    // EV = price * (chance/100) * qty * (batches at this step = cascadedQty / input_qty)
                    const batches = Math.floor(cascadedQty / Math.max(1, step.input_qty));
                    gross += bpPrice * (bp.chance_percent / 100) * bp.quantity * batches;
                }
                cascadedQty = stepOutputQty;
            }
            return gross;
        }
        ```

        Same pattern for `grossValueMax` but using `output_qty_max`.

        Update `canCalculate` — it should still only require first input and last output prices (byproducts are bonus, not required).

    12. In the calculator display section, if any steps have byproducts with prices, add a small line showing "Includes byproduct EV: {amount}" between the gross value and net profit rows. Calculate this as `grossValue - (cascadedFinalQty * lastOutputPrice)` to show just the byproduct contribution.

    Style all new UI elements with the existing Tailwind classes (wow-dark, wow-darker, wow-gold, gray-100/400/500/600/700 palette). Match the compact, dark theme of existing step cards.
  </action>
  <verify>
    <automated>cd /Users/lancethompson/Github/wow-ah-tracker && php artisan view:cache 2>&1 | head -5</automated>
  </verify>
  <done>
    - Each step card shows its byproducts (if any) with item name, chance %, and quantity
    - "+ Byproduct" button opens inline add form with item search, chance, and quantity fields
    - Adding a byproduct auto-watches the item for price polling
    - Removing a byproduct cleans up orphaned auto-watched items
    - Batch calculator grossValue includes byproduct expected value per step
    - Calculator shows byproduct EV contribution when byproducts exist
    - All UI matches existing dark theme styling
  </done>
</task>

</tasks>

<verification>
1. Create a shuffle with a step, then add a byproduct to the step via the UI
2. Verify the byproduct appears in the step card with correct chance % and quantity
3. Verify the byproduct item appears in the user's watched items
4. Enter a batch quantity in the calculator and verify the profit includes byproduct EV
5. Remove the byproduct and verify it's removed from the step and orphan watched item is cleaned up
</verification>

<success_criteria>
- Byproducts table migrated and model relationships work
- Step editor UI allows adding/removing multiple byproducts per step
- Byproduct items are auto-watched for price polling
- Batch calculator includes byproduct expected value in profit calculation
- Shuffle profitPerUnit() badge accounts for byproduct value
- All UI consistent with existing dark theme
</success_criteria>

<output>
After completion, create `.planning/quick/16-add-secondary-byproducts-with-drop-chanc/16-SUMMARY.md`
</output>
