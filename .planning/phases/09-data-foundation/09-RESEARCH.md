# Phase 9: Data Foundation - Research

**Researched:** 2026-03-04
**Domain:** Laravel 12 migrations, Eloquent models, factories, cascade deletes
**Confidence:** HIGH

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions

**Auto-watch provenance**
- Add nullable `created_by_shuffle_id` FK on `watched_items` table to track which items were auto-watched by shuffles
- Null value means manually added; non-null means auto-watched by that shuffle
- When a shuffle is deleted, remove auto-watched items ONLY if no other shuffle uses them (orphan cleanup)
- Manually-watched items (created_by_shuffle_id = null) are never removed by shuffle deletion, even if a shuffle also references the same item
- Existing thresholds on already-watched items are preserved when auto-watching (firstOrCreate behavior)

**Shuffle schema**
- `shuffles` table: id, user_id (FK), name (string), timestamps
- Name only — no description, notes, tags, or active/inactive toggle
- No cached profit column — profitability calculated on the fly from live prices
- No limit on number of shuffles per user

**Shuffle step schema**
- `shuffle_steps` table: id, shuffle_id (FK), input_blizzard_item_id, output_blizzard_item_id, output_qty_min (unsigned integer), output_qty_max (unsigned integer), sort_order (unsigned integer), timestamps
- Steps reference items via `blizzard_item_id` (not watched_item FK) — decoupled from watchlist
- Item names resolved via catalog_items relationship, not denormalized on steps
- No limit on steps per chain
- Default yield values for new steps: output_qty_min = 1, output_qty_max = 1

**Cascade behavior**
- Deleting a shuffle cascade-deletes all its steps
- Deleting a shuffle triggers orphan cleanup for auto-watched items (remove if no other shuffle uses them)

**Yield columns (pre-decided)**
- Integer columns only: `output_qty_min` and `output_qty_max` — no floats for quantities
- Both columns in Phase 9 migration even though min/max UI ships in Phase 11

### Claude's Discretion

- Migration column ordering and index strategy
- Factory fake data ranges and realistic WoW item IDs
- Test structure and assertion patterns

### Deferred Ideas (OUT OF SCOPE)

None — discussion stayed within phase scope
</user_constraints>

---

## Summary

Phase 9 creates the pure schema and model layer for the Shuffles feature. It introduces two new tables (`shuffles`, `shuffle_steps`), modifies `watched_items` to add provenance tracking (`created_by_shuffle_id`), and creates corresponding Eloquent models with relationships and factories for test seeding.

The existing codebase is Laravel 12 / Pest 3 / PHP 8.4. All patterns are well-established in the project: `declare(strict_types=1)`, `HasFactory`, `$fillable`, `$casts`, `foreignId()->constrained()`, and `cascadeOnDelete()` for FK relationships. The new work follows the exact same conventions already in use.

The only non-trivial decision already locked is cascade behavior for auto-watched item orphan cleanup. This cannot be handled by a DB-level `cascadeOnDelete()` alone — it requires a model-level observer or event on `Shuffle::deleting` to run the orphan check before the row is deleted. The cascade on `shuffle_steps` IS DB-level (simple `cascadeOnDelete()`).

**Primary recommendation:** Follow existing migration and model patterns exactly. Use a `Shuffle::deleting` model event (via `boot()` static method) for the orphan cleanup logic, not a database trigger.

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| Laravel Framework | ^12.0 | Migrations, Eloquent ORM, factories | Project baseline |
| PHP | ^8.4 | Language | Project baseline |
| Pest | ^3.8 | Test runner | Project baseline |
| pestphp/pest-plugin-laravel | ^3.2 | Laravel test helpers | Project baseline |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| Illuminate\Database\Eloquent\Relations\HasMany | (framework) | `User::shuffles()`, `Shuffle::steps()` | One-to-many relationships |
| Illuminate\Database\Eloquent\Relations\BelongsTo | (framework) | `ShuffleStep::inputCatalogItem()`, etc. | Belongs-to relationships |
| Illuminate\Foundation\Testing\RefreshDatabase | (framework) | Reset DB between tests | Already configured in Pest.php |

**No new packages required.** All tooling already installed.

## Architecture Patterns

### Recommended Project Structure
```
app/Models/
├── Shuffle.php          # new — HasMany steps, BelongsTo user
├── ShuffleStep.php      # new — BelongsTo shuffle, two BelongsTo catalogItem
├── User.php             # modified — add shuffles() HasMany
└── WatchedItem.php      # modified — add created_by_shuffle_id FK (nullable)

database/migrations/
├── 2026_03_05_XXXXXX_create_shuffles_table.php
├── 2026_03_05_XXXXXX_create_shuffle_steps_table.php
└── 2026_03_05_XXXXXX_add_created_by_shuffle_id_to_watched_items.php

database/factories/
├── ShuffleFactory.php
└── ShuffleStepFactory.php

tests/Feature/
└── ShuffleDataFoundationTest.php
```

### Pattern 1: Migration — New Table with FK and Cascade

Mirrors the existing `create_price_snapshots_table.php` pattern exactly.

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shuffles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shuffles');
    }
};
```

### Pattern 2: Migration — Alter Existing Table (Add Nullable FK)

Mirrors the `add_profession_to_watched_items.php` pattern for additive migrations.

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('watched_items', function (Blueprint $table) {
            $table->foreignId('created_by_shuffle_id')
                ->nullable()
                ->after('profession')
                ->constrained('shuffles')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('watched_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by_shuffle_id');
        });
    }
};
```

**Note on `nullOnDelete()`:** When the shuffle that auto-watched an item is deleted, this FK on `watched_items` is set to null. This is correct — the item stays watched, just loses its provenance. Orphan cleanup (removing the actual WatchedItem row) must be handled in application code, not at DB level, because it has conditional logic (only remove if no other shuffle references the item).

### Pattern 3: Shuffle Step Migration with Non-Standard FK Column Names

`blizzard_item_id` columns on `shuffle_steps` do NOT reference `catalog_items` via FK — they are just unsigned integers matching the pattern already used in `watched_items`. The step model resolves names via a custom relationship using `blizzard_item_id` as a foreign key to `catalog_items.blizzard_item_id`, same as `WatchedItem::catalogItem()`.

```php
Schema::create('shuffle_steps', function (Blueprint $table) {
    $table->id();
    $table->foreignId('shuffle_id')->constrained()->cascadeOnDelete();
    $table->unsignedBigInteger('input_blizzard_item_id');
    $table->unsignedBigInteger('output_blizzard_item_id');
    $table->unsignedInteger('output_qty_min')->default(1);
    $table->unsignedInteger('output_qty_max')->default(1);
    $table->unsignedInteger('sort_order')->default(0);
    $table->timestamps();
});
```

### Pattern 4: Eloquent Model with Ordered Relationship

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shuffle extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function steps(): HasMany
    {
        return $this->hasMany(ShuffleStep::class)->orderBy('sort_order');
    }

    protected static function boot(): void
    {
        parent::boot();

        static::deleting(function (Shuffle $shuffle): void {
            // Orphan cleanup: remove auto-watched items that were created by
            // this shuffle and are not referenced by any other shuffle.
            WatchedItem::where('created_by_shuffle_id', $shuffle->id)
                ->whereNotExists(function ($query) use ($shuffle) {
                    $query->select('id')
                        ->from('watched_items as wi2')
                        ->join('shuffle_steps as ss', function ($join) use ($shuffle) {
                            $join->on('wi2.blizzard_item_id', '=', 'ss.input_blizzard_item_id')
                                ->orOn('wi2.blizzard_item_id', '=', 'ss.output_blizzard_item_id');
                        })
                        ->join('shuffles', 'ss.shuffle_id', '=', 'shuffles.id')
                        ->where('shuffles.id', '!=', $shuffle->id)
                        ->whereColumn('wi2.id', 'watched_items.id');
                })
                ->delete();
        });
    }
}
```

**Important:** The cascade delete of `shuffle_steps` is handled at the DB level (`cascadeOnDelete()` in the migration). The `deleting` observer runs BEFORE the DB cascade, so steps still exist when orphan cleanup fires — this is the correct order.

### Pattern 5: ShuffleStep BelongsTo CatalogItem via blizzard_item_id

Mirrors the existing `WatchedItem::catalogItem()` pattern:

```php
public function inputCatalogItem(): BelongsTo
{
    return $this->belongsTo(CatalogItem::class, 'input_blizzard_item_id', 'blizzard_item_id');
}

public function outputCatalogItem(): BelongsTo
{
    return $this->belongsTo(CatalogItem::class, 'output_blizzard_item_id', 'blizzard_item_id');
}
```

### Pattern 6: Factory Pattern

Mirrors `WatchedItemFactory` and `CatalogItemFactory` exactly:

```php
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Shuffle;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ShuffleFactory extends Factory
{
    protected $model = Shuffle::class;

    public function definition(): array
    {
        return [
            'user_id'  => User::factory(),
            'name'     => $this->faker->words(3, true),
        ];
    }
}
```

```php
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Shuffle;
use App\Models\ShuffleStep;
use Illuminate\Database\Eloquent\Factories\Factory;

class ShuffleStepFactory extends Factory
{
    protected $model = ShuffleStep::class;

    public function definition(): array
    {
        return [
            'shuffle_id'             => Shuffle::factory(),
            'input_blizzard_item_id' => $this->faker->numberBetween(100000, 300000),
            'output_blizzard_item_id'=> $this->faker->numberBetween(100000, 300000),
            'output_qty_min'         => 1,
            'output_qty_max'         => 1,
            'sort_order'             => $this->faker->numberBetween(0, 10),
        ];
    }
}
```

### Pattern 7: Pest Test Structure for Model Layer

Feature tests in `tests/Feature/` use `RefreshDatabase` (already configured in `Pest.php` for the `Feature` directory). Model relationship tests are pure database tests — no Livewire or HTTP needed.

```php
<?php

declare(strict_types=1);

use App\Models\Shuffle;
use App\Models\ShuffleStep;
use App\Models\User;
use App\Models\WatchedItem;

uses()->group('shuffle-data-foundation');

test('user has many shuffles', function () {
    $user = User::factory()->create();
    Shuffle::factory()->count(3)->create(['user_id' => $user->id]);

    expect($user->shuffles)->toHaveCount(3);
});

test('shuffle steps are ordered by sort_order', function () {
    $shuffle = Shuffle::factory()->create();
    ShuffleStep::factory()->create(['shuffle_id' => $shuffle->id, 'sort_order' => 2]);
    ShuffleStep::factory()->create(['shuffle_id' => $shuffle->id, 'sort_order' => 0]);
    ShuffleStep::factory()->create(['shuffle_id' => $shuffle->id, 'sort_order' => 1]);

    expect($shuffle->steps->pluck('sort_order')->all())->toBe([0, 1, 2]);
});

test('deleting a shuffle cascade-deletes all its steps', function () {
    $shuffle = Shuffle::factory()->create();
    ShuffleStep::factory()->count(3)->create(['shuffle_id' => $shuffle->id]);

    $shuffle->delete();

    expect(ShuffleStep::where('shuffle_id', $shuffle->id)->count())->toBe(0);
});
```

### Anti-Patterns to Avoid

- **Using `float` columns for quantities:** All yield values are integers (`unsignedInteger`). Never `decimal` or `float` for output quantities.
- **Denormalizing item names on shuffle_steps:** Item names live in `catalog_items`. Do not add a `name` column to `shuffle_steps`.
- **FK from shuffle_steps to watched_items:** Steps reference `blizzard_item_id` directly, decoupled from the watchlist. Do not add a `watched_item_id` FK.
- **DB-level cascade for orphan cleanup:** The orphan cleanup logic is conditional (only remove if no other shuffle uses the item). A DB `cascadeOnDelete()` cannot express this — use a model event.
- **Skipping `declare(strict_types=1)`:** All PHP files in this project use strict types. Do not omit it.
- **Using anonymous function in `boot()` without type hint:** Always type-hint the model in the closure: `function (Shuffle $shuffle): void`.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Ordered relationship | Custom query with ORDER BY in controller | `->orderBy('sort_order')` on `HasMany` in model | Relationship carries ordering everywhere it's used |
| Cascade deletes for steps | Manual loop deleting steps | `cascadeOnDelete()` in migration | DB-enforced, atomic, no risk of partial delete |
| Factory auto-associations | Hardcoded IDs in factory definitions | `User::factory()` / `Shuffle::factory()` as default values | Factory creates proper parent records automatically |
| `dropConstrainedForeignId` | Manual `dropForeign` + `dropColumn` | `dropConstrainedForeignId('col_name')` | Laravel 12 helper handles both in one call |

**Key insight:** Cascade for steps is a pure DB concern; orphan cleanup for watched_items is a business logic concern. Use the right tool for each.

## Common Pitfalls

### Pitfall 1: Wrong Column Type for blizzard_item_id on Shuffle Steps

**What goes wrong:** Using `unsignedInteger` instead of `unsignedBigInteger` for `input_blizzard_item_id` / `output_blizzard_item_id` columns.
**Why it happens:** Blizzard item IDs can exceed the `unsignedInteger` max (4,294,967,295 is sufficient for current IDs, but `unsignedBigInteger` is the established project convention for all Blizzard IDs).
**How to avoid:** Check `watched_items` migration — it uses `unsignedBigInteger('blizzard_item_id')`. Follow the same convention.
**Warning signs:** Migration succeeds but casting issues emerge when storing large Blizzard item IDs.

### Pitfall 2: Migration Order Dependency

**What goes wrong:** `create_shuffle_steps_table` runs before `create_shuffles_table`, causing FK constraint failure.
**Why it happens:** Laravel runs migrations in alphabetical/timestamp order.
**How to avoid:** Ensure the `shuffles` migration timestamp is earlier than `shuffle_steps`. Similarly, `add_created_by_shuffle_id_to_watched_items` must run after `create_shuffles_table`.
**Warning signs:** `php artisan migrate` fails with "Table 'shuffles' doesn't exist" or similar FK error.

### Pitfall 3: Orphan Cleanup Fires After Steps Are Deleted

**What goes wrong:** Putting orphan cleanup in `deleted` (past tense) instead of `deleting` — by then, cascade has already removed the steps, making it impossible to check which items were referenced.
**Why it happens:** Confusion between `deleting` (before delete) vs `deleted` (after delete) model events.
**How to avoid:** Use `static::deleting()` in `boot()`. Steps still exist at this point; DB cascade fires after the model event completes.
**Warning signs:** Orphan cleanup query returns zero rows even when it should find items to clean.

### Pitfall 4: `nullOnDelete()` vs Orphan Cleanup Confusion

**What goes wrong:** Relying on `nullOnDelete()` on `watched_items.created_by_shuffle_id` to serve as orphan cleanup — it only nulls the FK, it does not delete the WatchedItem row.
**Why it happens:** Misreading the intent of the FK constraint.
**How to avoid:** `nullOnDelete()` is intentional — it preserves the watched item after the shuffle is deleted, just clears the provenance. The model event in `Shuffle::deleting` is what actually deletes orphan WatchedItem rows (those with no other shuffle reference).
**Warning signs:** Deleting a shuffle leaves behind WatchedItem rows that should have been removed.

### Pitfall 5: Missing `$fillable` for Factory-Created Attributes

**What goes wrong:** `MassAssignmentException` when factory tries to create records.
**Why it happens:** Forgetting to add new columns to `$fillable` on the model.
**How to avoid:** Ensure every column the factory sets is in `$fillable`. Required for: `Shuffle` (`user_id`, `name`) and `ShuffleStep` (`shuffle_id`, `input_blizzard_item_id`, `output_blizzard_item_id`, `output_qty_min`, `output_qty_max`, `sort_order`).

## Code Examples

### Full shuffle_steps migration
```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shuffle_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shuffle_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('input_blizzard_item_id');
            $table->unsignedBigInteger('output_blizzard_item_id');
            $table->unsignedInteger('output_qty_min')->default(1);
            $table->unsignedInteger('output_qty_max')->default(1);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shuffle_steps');
    }
};
```

### User::shuffles() relationship addition
```php
// In app/Models/User.php — add alongside existing watchedItems()
public function shuffles(): HasMany
{
    return $this->hasMany(Shuffle::class);
}
```

### Testing cascade delete
```php
test('deleting a shuffle cascade-deletes all its steps', function () {
    $shuffle = Shuffle::factory()
        ->has(ShuffleStep::factory()->count(3), 'steps')
        ->create();

    expect(ShuffleStep::count())->toBe(3);

    $shuffle->delete();

    expect(ShuffleStep::count())->toBe(0);
});
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Anonymous class migrations | Already used in project | Laravel 9+ | No change needed |
| `$table->foreign('col_id')->references('id')->on('table')` | `foreignId('col_id')->constrained()` | Laravel 7+ | Project already uses constrained() |
| Manual `dropForeign` + `dropColumn` | `dropConstrainedForeignId('col')` | Laravel 8+ | Use in `down()` methods |

**No deprecated patterns to worry about** — this project already uses current conventions throughout.

## Open Questions

1. **Orphan cleanup query complexity**
   - What we know: The cleanup must remove WatchedItem rows where `created_by_shuffle_id = $shuffle->id` AND no other shuffle references the same `blizzard_item_id` via its steps.
   - What's unclear: The exact join path for "any other shuffle references this item" — both `input_blizzard_item_id` and `output_blizzard_item_id` must be checked.
   - Recommendation: Keep a simple readable query in `Shuffle::deleting`. Test it directly in the test file. A more complex subquery approach can be verified empirically with the RefreshDatabase trait.

2. **Index strategy for shuffle_steps**
   - What we know: `shuffle_id` is the primary lookup key (load all steps for a shuffle). `sort_order` is used for ordering.
   - What's unclear: Whether a composite index on `(shuffle_id, sort_order)` is needed at this scale.
   - Recommendation: At Claude's discretion — add a composite index `$table->index(['shuffle_id', 'sort_order'])` for correctness. The table will be small but it's cheap to add and future-proof.

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | Pest 3.8 + pest-plugin-laravel 3.2 |
| Config file | `tests/Pest.php` (already configured) |
| Quick run command | `php artisan test --filter shuffle-data-foundation` |
| Full suite command | `php artisan test` |

### Phase Requirements to Test Map

Phase 9 has no formal REQ IDs — it is a pure infrastructure prerequisite. The success criteria from the phase description map to tests as follows:

| Success Criterion | Behavior | Test Type | Automated Command | File Exists? |
|-------------------|----------|-----------|-------------------|-------------|
| SC-1: Migrations create tables with integer yield columns | `shuffles` and `shuffle_steps` tables exist with correct columns | Unit (schema) | `php artisan test --filter shuffle-data-foundation` | No — Wave 0 |
| SC-2: Eloquent models with correct relationships | `User::shuffles()`, `Shuffle::steps()` ordered by `sort_order` | Unit (model) | `php artisan test --filter shuffle-data-foundation` | No — Wave 0 |
| SC-3: Factories seed test data without errors | `ShuffleFactory` and `ShuffleStepFactory` create valid records | Unit (factory) | `php artisan test --filter shuffle-data-foundation` | No — Wave 0 |
| SC-4: Cascade deletes remove steps | Deleting a shuffle removes all `shuffle_steps` rows | Unit (cascade) | `php artisan test --filter shuffle-data-foundation` | No — Wave 0 |

### Sampling Rate
- **Per task commit:** `php artisan test --filter shuffle-data-foundation`
- **Per wave merge:** `php artisan test`
- **Phase gate:** Full suite green before `/gsd:verify-work`

### Wave 0 Gaps
- [ ] `tests/Feature/ShuffleDataFoundationTest.php` — covers all 4 success criteria above
- [ ] No framework changes needed — `RefreshDatabase` already wired in `tests/Pest.php` for Feature directory

## Sources

### Primary (HIGH confidence)
- Direct code inspection: `app/Models/WatchedItem.php`, `app/Models/User.php`, `app/Models/CatalogItem.php`
- Direct code inspection: `database/migrations/2026_03_01_192521_create_watched_items_table.php`
- Direct code inspection: `database/migrations/2026_03_01_192522_create_price_snapshots_table.php`
- Direct code inspection: `database/migrations/2026_03_04_000000_add_profession_to_watched_items.php`
- Direct code inspection: `database/factories/WatchedItemFactory.php`, `database/factories/CatalogItemFactory.php`
- Direct code inspection: `tests/Pest.php`, `tests/Feature/WatchlistTest.php`
- Direct code inspection: `composer.json` — Laravel 12, PHP 8.4, Pest 3.8, pest-plugin-laravel 3.2
- Direct code inspection: `.planning/phases/09-data-foundation/09-CONTEXT.md` — locked decisions

### Secondary (MEDIUM confidence)
- Laravel 12 `foreignId()->constrained()->cascadeOnDelete()` — verified by multiple existing migrations in project
- `dropConstrainedForeignId()` — Laravel 8+ helper, used in project pattern

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — All tooling confirmed from composer.json and existing code
- Architecture: HIGH — All patterns verified from existing project migrations and models
- Pitfalls: HIGH — Derived from actual code patterns and locked decisions in CONTEXT.md
- Orphan cleanup logic: MEDIUM — Pattern is sound but complex query needs empirical test verification

**Research date:** 2026-03-04
**Valid until:** 2026-04-04 (stable, no fast-moving dependencies)
