# Stack Research

**Domain:** WoW Auction House commodity price tracker (single-user Laravel web app)
**Researched:** 2026-03-04 (v1.1 Shuffles milestone update)
**Confidence:** HIGH for all recommendations below

---

## v1.1 Shuffles: Stack Delta

This document preserves the v1.0 stack baseline and appends a focused delta for the Shuffles milestone. The existing stack (Laravel 12, Livewire 4, Volt, Tailwind CSS v4, ApexCharts, Pest 3, SQLite, database queues) is unchanged and fully validated. Do not re-research those decisions.

### What Shuffles Adds

Shuffles introduces three new concerns not present in v1.0:

1. **Data model for conversion chains** — ordered sequences of steps, each with item references and ratio/yield data
2. **Ratio/yield arithmetic** — multiply input quantities through steps, surface profit at each step
3. **Dynamic multi-step forms** — Livewire form that grows/shrinks as the user adds/removes chain steps

All three are solved entirely within the existing stack. No new packages are required.

---

## No New Dependencies Needed

The Shuffles feature can be built entirely with what is already installed. Each concern below is handled natively.

### Conversion Chain Data Model

**Use standard Eloquent with two new tables.** A `shuffles` table holds the named chain and a `shuffle_steps` table holds ordered steps with `position`, input item reference, yield ratio, and min/max yield. No package needed.

```php
// shuffle_steps columns:
// id, shuffle_id, position (unsigned tinyint), catalog_item_id,
// ratio_numerator (unsigned int), ratio_denominator (unsigned int),
// min_yield (unsigned int nullable), max_yield (unsigned int nullable)
```

Ordering by `position` in a `hasMany` relationship with `->orderBy('position')` is straightforward. No adjacency-list or recursive CTE package is needed — chains are linear sequences, not trees.

**Why not JSON columns for steps?** Storing steps as a JSON column on `shuffles` would make individual step validation, indexing, and future extension harder. Separate rows are the correct choice when each step is a first-class entity with its own foreign key (`catalog_item_id`).

### Ratio and Profit Arithmetic

**Use PHP integer arithmetic throughout.** All prices are already stored as BIGINT UNSIGNED copper values (v1.0 decision). Ratios are stored as integer numerator/denominator pairs (e.g., 5 herbs → 1 pigment is stored as `ratio_numerator=1, ratio_denominator=5`).

**PHP 8.4 BCMath\Number is available but not needed.** For yield multiplication and profit summation on integer copper values, standard PHP integer arithmetic is exact. BCMath is warranted only when you have non-integer intermediate values (e.g., price-per-unit in decimal gold). Since all math stays in copper integers, standard PHP is correct and simpler.

```php
// Example: step yield from integer input
$stepOutput = (int) floor($inputQty * $step->ratio_numerator / $step->ratio_denominator);
$stepCost   = $inputQty * $latestPrice; // copper, integer
$stepValue  = $stepOutput * $outputPrice; // copper, integer
$profit     = $stepValue - $stepCost;
```

No new library needed.

### Dynamic Multi-Step Form in Livewire

**Use Livewire 4's built-in array property binding.** The chain builder form manages a PHP array property (e.g., `$steps = []`) where each element holds step data. Methods like `addStep()` and `removeStep($index)` mutate the array; `wire:model="steps.{$i}.ratio_numerator"` binds individual fields.

**Validation uses wildcard rules.** Livewire 4 supports `'steps.*.catalog_item_id' => 'required|integer'` patterns in the `rules()` method or `#[Validate]` attributes. This is the documented approach for array field validation.

**Known limitation:** Real-time (`.live`) validation on nested array fields has known rough edges in Livewire — error assignment can mismatch when rows are reordered. Mitigate by validating on explicit save, not on every keystroke.

No third-party form builder package needed.

---

## Recommended Stack (Complete — v1.0 + v1.1)

### Core Technologies

| Technology | Version | Purpose | Why Recommended |
|------------|---------|---------|-----------------|
| Laravel | ^12.0 | PHP web framework | Validated in v1.0. Scheduler, queues, Eloquent. No change. |
| PHP | ^8.4 | Runtime | Project constraint. PHP 8.4 is now the pinned version. BCMath\Number available natively if ever needed. |
| Livewire + Volt | ^4.0 / ^1.7 | Reactive UI, SFC pages | Validated in v1.0. Volt SFC pattern used for all pages including new Shuffles pages. |
| Tailwind CSS | ^4.2 | Utility-first CSS | Validated in v1.0. WoW dark theme + gold/amber accents. No change. |
| ApexCharts | ^5.7 | Time-series charts on dashboard | Validated in v1.0. Not used in Shuffles UI, but retained for dashboard. |
| SQLite | 3.x | Primary data store | Validated in v1.0. Two new tables (shuffles, shuffle_steps) fit trivially. |

### New Tables for Shuffles (no package — pure Eloquent)

| Table | Purpose | Key Columns |
|-------|---------|-------------|
| `shuffles` | Named conversion chain | `id`, `user_id`, `name`, `description`, `timestamps` |
| `shuffle_steps` | Ordered steps within a chain | `id`, `shuffle_id`, `position` (tinyint), `catalog_item_id`, `ratio_numerator`, `ratio_denominator`, `min_yield`, `max_yield` |

### Supporting Libraries (unchanged from v1.0)

| Library | Version | Purpose | Status |
|---------|---------|---------|--------|
| Laravel HTTP Client | Built-in | Blizzard API calls | Unchanged |
| Laravel Breeze | ^2.3 (dev) | Auth scaffolding | Unchanged |
| Laravel Pint | ^1.24 (dev) | Code style | Unchanged |
| Pest PHP | ^3.8 (dev) | Test framework | Unchanged |
| Faker | ^1.23 (dev) | Test factories | Unchanged |

---

## Installation

No new packages to install. The Shuffles feature is built with migrations + Eloquent + Livewire Volt pages already in the project.

```bash
# Generate migrations for new tables
php artisan make:migration create_shuffles_table
php artisan make:migration create_shuffle_steps_table

# Generate models
php artisan make:model Shuffle
php artisan make:model ShuffleStep

# Generate factories for tests
php artisan make:factory ShuffleFactory
php artisan make:factory ShuffleStepFactory

# Run migrations
php artisan migrate
```

---

## Alternatives Considered

| Recommended | Alternative | Why Not |
|-------------|-------------|---------|
| Integer numerator/denominator pairs for ratios | Decimal/float column for ratio | Float storage risks rounding errors in copper arithmetic. Int pairs are exact and self-documenting (e.g., 1/5 = "1 output per 5 inputs"). |
| Separate `shuffle_steps` table with `position` column | JSON column on `shuffles` for steps | JSON steps can't have their own `catalog_item_id` FK constraint, can't be queried individually, and complicate validation. Separate rows are correct for entities. |
| Plain Eloquent with `orderBy('position')` | `staudenmeir/laravel-adjacency-list` (recursive CTE package) | Chains are linear sequences, not trees. Recursive CTEs add no value. The adjacency-list package targets nested/recursive hierarchies. |
| BCMath only if intermediate floats appear | BCMath for all arithmetic | Overkill. All values are copper integers. Standard PHP integer division with `floor()` is exact for this use case. |
| Livewire native array binding + save-time validation | Third-party repeater form package | No package adds enough value to justify a dependency. Real-time nested validation is a Livewire limitation, not something a package reliably fixes. |

---

## What NOT to Add

| Avoid | Why | Use Instead |
|-------|-----|-------------|
| `staudenmeir/laravel-adjacency-list` | Designed for recursive trees. Shuffles chains are linear — parent/child recursion adds unnecessary complexity. | Eloquent `hasMany` with `orderBy('position')` |
| `spatie/laravel-data` for shuffle chain DTOs | Shuffle chain data is simple enough that Eloquent models + array access is sufficient. Adding typed DTOs for a 4-field step model is over-engineering. | Plain Eloquent models |
| Any "drag to reorder" JS library (Sortable.js, etc.) | Chain steps don't need drag reorder UX — numeric position input or up/down buttons are sufficient for a personal tool with short chains. | Simple `addStep()` / `removeStep()` / `moveUp()` Livewire methods |
| Redis | Still no benefit. Shuffles adds zero new queue jobs. | Database queue driver (unchanged) |
| Nova or Filament admin panel | Shuffles management is a user-facing page, not an admin feature. | Livewire Volt page with inline CRUD |

---

## Stack Patterns for Shuffles

**Profit calculation in a PHP service class:**
- Extract `ShuffleCalculator::calculate(Shuffle $shuffle, int $inputQty): array` as a plain PHP class
- Returns per-step breakdown and totals as arrays of copper integers
- Keeps Volt component thin; makes the calculator independently testable with Pest
- Prices come from `CatalogItem->priceSnapshots()->latest('polled_at')->first()` — already established in v1.0

**Auto-watch integration:**
- When a shuffle step is saved with a `catalog_item_id`, check if a `WatchedItem` exists for that `blizzard_item_id` and the current user
- If not, create it via `auth()->user()->watchedItems()->firstOrCreate([...])` — same pattern used by the watchlist page
- Run this in a model observer on `ShuffleStep::created` and `ShuffleStep::updated`, or inline in the Livewire save method

**Batch calculator as a Livewire `$wire` interaction:**
- Input quantity is a Livewire property (`public int $inputQty = 1`)
- Profit breakdown is a `#[Computed]` property that runs `ShuffleCalculator::calculate()` on each request
- No JavaScript needed — Livewire re-renders the breakdown table on every quantity change (`wire:model.live="inputQty"`)

**Ordering steps in the chain:**
- `shuffle_steps.position` is a 0-based or 1-based `tinyint unsigned`
- `Shuffle::steps()` relationship: `return $this->hasMany(ShuffleStep::class)->orderBy('position');`
- Reorder via swap: `moveStepUp($index)` swaps `position` values between adjacent steps
- No complex sorting algorithm needed — chains will rarely exceed 4–5 steps

---

## Version Compatibility

| Package | Compatible With | Notes |
|---------|-----------------|-------|
| Laravel ^12.0 | PHP ^8.4 | PHP 8.4 confirmed for this project |
| Livewire ^4.0 + Volt ^1.7 | Laravel 12 | Validated in production (v1.0 shipped) |
| Tailwind CSS ^4.2 | Vite + `@tailwindcss/vite` | CSS-first config, no `tailwind.config.js` |
| ApexCharts ^5.7 | Vanilla JS via `window.ApexCharts` | Direct global — no ES module import in Volt SFCs |
| SQLite 3.x | All new tables | WAL mode recommended if write contention ever appears (not a current concern) |

---

## Sources

- Project v1.0 codebase (`composer.json`, `package.json`, existing models) — Version baseline (HIGH confidence, directly inspected)
- [PHP 8.4 BCMath\Number](https://www.php.net/manual/en/book.bc.php) — Available natively in PHP 8.4; not needed for integer arithmetic (HIGH confidence)
- [Livewire 4 Validation Docs](https://livewire.laravel.com/docs/4.x/validation) — Wildcard array validation rules supported (HIGH confidence)
- [Livewire 4 wire:model Docs](https://livewire.laravel.com/docs/4.x/wire-model) — Array property binding pattern (HIGH confidence)
- [Laravel Eloquent orderByPivot / orderBy on hasMany](https://laravel.com/docs/12.x/eloquent-relationships) — `orderBy('position')` on hasMany is built-in (HIGH confidence)
- [staudenmeir/laravel-adjacency-list on GitHub](https://github.com/staudenmeir/laravel-adjacency-list) — Confirmed it targets recursive tree structures, not linear ordered lists; not appropriate here (HIGH confidence)
- WebSearch: "Livewire 4 dynamic repeatable form fields array inputs 2025" — Confirms native array binding + save-time validation is the documented pattern; no third-party package is the consensus recommendation (MEDIUM confidence)
- WebSearch: "PHP 8.4 BCMath decimal class new API 2024" — Confirms BCMath\Number is available in PHP 8.4 with operator overloading; confirmed as overkill for integer-only copper arithmetic (HIGH confidence)

---
*Stack research for: WoW AH Tracker — v1.1 Shuffles milestone*
*Researched: 2026-03-04*
