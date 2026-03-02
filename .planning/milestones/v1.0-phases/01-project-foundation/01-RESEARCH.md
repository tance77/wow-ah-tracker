# Phase 1: Project Foundation - Research

**Researched:** 2026-03-01
**Domain:** Laravel 12 scaffolding, SQLite schema design, Livewire 4, Tailwind CSS v4, Pest testing
**Confidence:** HIGH

---

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions

**Schema design:**
- Include `user_id` (nullable FK to `users`) on `watched_items` from the start — avoids a migration change when Phase 2 adds auth
- Hard delete for watched items — items are just Blizzard IDs, trivial to re-add. No SoftDeletes
- Bare minimum columns on `watched_items`: `blizzard_item_id`, `name`, `buy_threshold`, `sell_threshold`, `user_id` — no extra metadata columns (icon, category, quality). Fetch on demand later if needed
- `polled_at` on `price_snapshots` uses a datetime column (Laravel `timestamp()`), not a Unix integer — works natively with Carbon, Eloquent casting, and chart libraries
- Price columns (`min_price`, `avg_price`, `median_price`, `total_volume`) stored as unsigned big integers (copper denomination) per roadmap requirements

**Dev tooling:**
- Pest for testing — matches roadmap plan references and provides expressive syntax
- Minimal dev tools in Phase 1: Pint (code style) + Pest. Add Debugbar, Telescope, IDE Helper as later phases need them
- Include model factories and DatabaseSeeder with sample watched items and fake price snapshots — enables manual testing from day one
- Target PHP 8.3 — latest stable with typed class constants, json_validate(), and `#[Override]` attribute

**Project conventions:**
- Actions + Services pattern: `app/Actions/` for single-purpose operations (PriceFetchAction, PriceAggregateAction), `app/Services/` for stateful service classes (BlizzardTokenService)
- Models stay in `app/Models/` — Laravel default, no custom location
- `declare(strict_types=1)` on all new PHP files
- Full type hints — return types and parameter types on all methods

### Claude's Discretion

- Exact migration column order and naming beyond what's specified
- `.gitignore` contents and IDE config
- Composer script aliases
- Any additional indexes beyond the required composite `(watched_item_id, polled_at)`

### Deferred Ideas (OUT OF SCOPE)

None — discussion stayed within phase scope
</user_constraints>

---

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| DATA-02 | Each snapshot stores min price, average price, median price, and total volume | `price_snapshots` migration with four `unsignedBigInteger` columns; composite index pattern documented below |
| DATA-03 | Prices stored as integers (copper) to avoid rounding errors | `unsignedBigInteger` column type verified in Laravel 12 migrations docs; avoids float rounding; fits WoW copper values (max ~2,147,483,647 copper = ~21,474 gold, safely within BIGINT range) |
</phase_requirements>

---

## Summary

Phase 1 is a greenfield Laravel 12 project setup with no existing code to integrate. The technical domain covers: project scaffolding via the Laravel installer, Livewire 4 installation (manual, not starter-kit), Tailwind CSS v4 Vite integration, two database migrations with specific column type and index requirements, environment credential wiring, and baseline dev tooling (Pest + Pint, both included by default in Laravel 12).

The most important decisions in this phase are **irreversible schema choices**: `unsignedBigInteger` for copper prices, a `timestamp()` for `polled_at`, and the composite index `(watched_item_id, polled_at)`. All three must be correct in the initial migration because changing column types or adding efficient indexes after rows accumulate in SQLite requires table rebuilds. The Livewire starter kit bundles auth scaffolding (Flux UI, Volt) that Phase 2 will replace; starting with a bare Laravel install and adding Livewire manually keeps Phase 1 clean.

The environment configuration pattern (`config/services.php` → `.env` key references) is Laravel convention with no gotchas. Pest and Pint ship with Laravel 12 by default. PHP 8.3 is the target and is fully supported.

**Primary recommendation:** Create a bare Laravel 12 project (no starter kit), add Livewire 4 and Tailwind v4 manually via Composer/npm, write the two migrations with precise column types, wire Blizzard credentials through `config/services.php`, and scaffold factories + seeder for manual testing readiness.

---

## Standard Stack

### Core

| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| Laravel | 12.x | PHP application framework | Locked decision; current LTS-adjacent release (security fixes until Feb 2027) |
| Livewire | 4.x | Reactive UI without JavaScript SPA overhead | Locked decision; released Jan 2026, Laravel 10/11/12/13 compatible |
| Tailwind CSS | v4 | Utility-first CSS | Locked decision; Laravel 12 installs Tailwind v4 by default via Vite plugin |
| SQLite | bundled with PHP | Development database | Default in Laravel 12; zero-config for local dev; database file at `database/database.sqlite` |
| Pest | 3.x | PHP testing framework | Locked decision; ships with Laravel 12 by default |
| Laravel Pint | 1.x | PHP code style fixer (PHP-CS-Fixer wrapper) | Locked decision; ships with Laravel 12 by default; zero-config |

### Supporting

| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| Vite | bundled | Asset compilation | Default Laravel bundler; required for Tailwind v4 plugin integration |
| FakerPHP | bundled | Fake data generation in factories | Used inside model factory `definition()` methods |
| PHP | 8.3 | Runtime | Locked decision; enables typed class constants and `#[Override]` attribute |

### Alternatives Considered

| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| SQLite | MySQL/PostgreSQL | SQLite is zero-config for dev; production can switch via `.env` |
| Bare install + manual Livewire | Livewire starter kit | Starter kit bundles Flux UI + auth (Phase 2 scope); manual keeps Phase 1 clean |
| `timestamp()` for `polled_at` | Unix integer | `timestamp()` works with Carbon/Eloquent casting natively; user locked this in |

**Installation commands:**

```bash
# 1. Create bare Laravel 12 project (select "None" for starter kit when prompted)
laravel new wow-ah-tracker
# OR via Composer:
composer create-project laravel/laravel:^12.0 wow-ah-tracker

# 2. Add Livewire 4 manually
composer require livewire/livewire

# 3. Tailwind v4 is included by default in Laravel 12 via Vite
# Verify in package.json: @tailwindcss/vite should already be present

# 4. Pest is included by default; verify with:
php artisan test --version

# 5. Pint is included by default; verify with:
./vendor/bin/pint --version
```

---

## Architecture Patterns

### Recommended Project Structure

```
app/
├── Actions/          # Single-purpose operation classes (PriceFetchAction, etc.)
├── Models/           # Eloquent models (WatchedItem, PriceSnapshot)
├── Services/         # Stateful service classes (BlizzardTokenService)
config/
├── services.php      # Third-party API credentials (Blizzard)
database/
├── factories/        # Model factories (WatchedItemFactory, PriceSnapshotFactory)
├── migrations/       # Schema migrations (watched_items, price_snapshots)
├── seeders/          # DatabaseSeeder with sample data
resources/
├── views/            # Blade templates
.env                  # Local environment (never committed)
.env.example          # Template with key names (committed)
```

### Pattern 1: Migration Column Types (CRITICAL — irreversible)

**What:** Use `unsignedBigInteger` for copper prices and `timestamp()` for `polled_at`.
**When to use:** Always, from the first migration. Cannot be fixed after data is written.
**Example:**

```php
// Source: https://laravel.com/docs/12.x/migrations
Schema::create('price_snapshots', function (Blueprint $table) {
    $table->id();
    $table->foreignId('watched_item_id')->constrained()->cascadeOnDelete();
    $table->unsignedBigInteger('min_price');
    $table->unsignedBigInteger('avg_price');
    $table->unsignedBigInteger('median_price');
    $table->unsignedBigInteger('total_volume');
    $table->timestamp('polled_at');
    $table->timestamps();

    // Required composite index
    $table->index(['watched_item_id', 'polled_at']);
});
```

### Pattern 2: Nullable FK for Future Auth

**What:** Add `user_id` as nullable on `watched_items` now so Phase 2 (auth) doesn't require a migration.
**When to use:** When a nullable FK relationship is known to be added in a future phase.
**Example:**

```php
// Source: https://laravel.com/docs/12.x/migrations
Schema::create('watched_items', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
    $table->unsignedBigInteger('blizzard_item_id');
    $table->string('name');
    $table->unsignedInteger('buy_threshold');   // percentage
    $table->unsignedInteger('sell_threshold');  // percentage
    $table->timestamps();
});
```

### Pattern 3: config/services.php Credential Wiring

**What:** Third-party API credentials live in `.env`, referenced in `config/services.php`.
**When to use:** Any external API — Laravel convention.
**Example:**

```php
// config/services.php
'blizzard' => [
    'client_id'     => env('BLIZZARD_CLIENT_ID'),
    'client_secret' => env('BLIZZARD_CLIENT_SECRET'),
    'region'        => env('BLIZZARD_REGION', 'us'),
],
```

```ini
# .env
BLIZZARD_CLIENT_ID=your-client-id
BLIZZARD_CLIENT_SECRET=your-client-secret
BLIZZARD_REGION=us
```

Accessed in code via: `config('services.blizzard.client_id')`.

### Pattern 4: Actions Pattern (project convention)

**What:** Single-responsibility action classes in `app/Actions/`. One class, one public `handle()` method.
**When to use:** Any discrete business operation — price fetching, aggregation, etc.
**Example:**

```php
<?php

declare(strict_types=1);

namespace App\Actions;

class PriceFetchAction
{
    public function handle(int $watchedItemId): array
    {
        // single responsibility logic
    }
}
```

### Pattern 5: Model Factory with Realistic Data

**What:** Factories use FakerPHP to generate realistic copper price values.
**When to use:** Model factories for `WatchedItem` and `PriceSnapshot` — required by user decision.
**Example:**

```php
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\WatchedItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class PriceSnapshotFactory extends Factory
{
    public function definition(): array
    {
        $avgPrice = $this->faker->numberBetween(10_000, 500_000); // copper
        return [
            'watched_item_id' => WatchedItem::factory(),
            'min_price'       => (int) ($avgPrice * 0.85),
            'avg_price'       => $avgPrice,
            'median_price'    => (int) ($avgPrice * 0.95),
            'total_volume'    => $this->faker->numberBetween(100, 50_000),
            'polled_at'       => $this->faker->dateTimeBetween('-30 days', 'now'),
        ];
    }
}
```

### Anti-Patterns to Avoid

- **Float columns for prices:** `$table->float('min_price')` — rounding errors accumulate; use `unsignedBigInteger` only.
- **Unix integer for `polled_at`:** `$table->unsignedInteger('polled_at')` — breaks Carbon casting, Eloquent date handling, and chart libraries that expect datetime.
- **Storing prices in gold (decimal):** Intermediate gold conversion belongs in presentation layer, not storage.
- **Livewire starter kit for Phase 1:** It ships auth scaffolding (Flux UI, Volt, login/register pages) which is Phase 2 scope. Use bare install.
- **SoftDeletes on `watched_items`:** User locked this out — hard delete only.
- **Missing `declare(strict_types=1)`:** Project convention requires it on ALL new PHP files.

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Code style enforcement | Custom lint scripts | Laravel Pint (bundled) | Bundled with Laravel 12, zero-config, PHP-CS-Fixer under the hood |
| Test runner | Custom test harness | Pest (bundled) | Bundled with Laravel 12; expressive syntax, parallel test support |
| Migration schema builder | Raw SQL DDL | Laravel Schema Builder | Handles SQLite/MySQL/PG column type differences, index naming, FK cascades |
| Fake data generation | Custom random data scripts | FakerPHP via model factories | Bundled via Composer; locale-aware, reproducible with seeds |
| Environment config loading | Custom `.env` parser | Laravel's built-in (`env()` + `config()`) | Handles casting, caching, test overrides automatically |

**Key insight:** Laravel 12 ships with all tooling needed for Phase 1. The risk is adding packages not yet needed — YAGNI applies.

---

## Common Pitfalls

### Pitfall 1: Choosing the Livewire Starter Kit Instead of Bare Install

**What goes wrong:** The official Livewire starter kit installs auth scaffolding, Flux UI component library, Laravel Volt, and prebuilt login/register pages. These are Phase 2+ scope. Installing the kit now creates components you'll redo in Phase 2.
**Why it happens:** `laravel new` prompts interactively — easy to select Livewire kit by default.
**How to avoid:** When prompted for starter kit during `laravel new`, select **None**. Then add Livewire manually: `composer require livewire/livewire`.
**Warning signs:** After installation, if `resources/views/pages/` exists with `login.blade.php`, you chose the starter kit.

### Pitfall 2: Wrong Column Type for Copper Prices

**What goes wrong:** Using `$table->integer()` (signed 32-bit) caps at ~2.1 billion copper (~21,474 gold). WoW commodity prices for rare mats can exceed this. Using `$table->float()` introduces rounding errors that corrupt price comparisons.
**Why it happens:** `integer` feels natural; float seems like "enough precision."
**How to avoid:** Always use `$table->unsignedBigInteger()` for all four price columns. BIGINT UNSIGNED safely holds values up to ~18.4 quintillion copper.
**Warning signs:** Migration uses `$table->integer()`, `$table->decimal()`, or `$table->float()` for any price column.

### Pitfall 3: Missing Composite Index at Migration Time

**What goes wrong:** Adding the composite `(watched_item_id, polled_at)` index after data is inserted requires SQLite to rebuild the table. With millions of snapshots, this is extremely slow or impractical.
**Why it happens:** Indexes feel like an optimization concern for later.
**How to avoid:** Include `$table->index(['watched_item_id', 'polled_at'])` inside the `price_snapshots` migration's `create` callback.
**Warning signs:** Migration has no `index()` call for `price_snapshots`.

### Pitfall 4: Tailwind v4 Config Assumptions

**What goes wrong:** Tailwind v4 removed `tailwind.config.js` as the primary config mechanism. Developers following v3 tutorials create a `tailwind.config.js` and wonder why classes aren't purging/applying correctly.
**Why it happens:** Most tutorials still reference v3 patterns. Tailwind v4 uses CSS-first configuration via `@theme` directives in CSS files.
**How to avoid:** Use the Vite plugin integration (`@tailwindcss/vite`) which Laravel 12 configures by default. Only create `tailwind.config.js` if you need to add custom plugins.
**Warning signs:** You see build errors about missing config file, or `@apply` directives fail.

### Pitfall 5: SQLite Foreign Key Enforcement Off by Default

**What goes wrong:** SQLite disables foreign key constraints by default. The `user_id` FK on `watched_items` won't enforce referential integrity during development without explicit configuration.
**Why it happens:** SQLite behavior difference from MySQL/PostgreSQL.
**How to avoid:** Laravel 12 enables SQLite foreign keys automatically via `database.php` configuration (`'foreign_key_constraints' => true` is the default). Verify this is in place before adding FK migrations.
**Warning signs:** You can delete users without cascading or nullifying `watched_items.user_id`.

### Pitfall 6: .env.example Not Updated

**What goes wrong:** Blizzard API keys are in `.env` but not in `.env.example`. Next developer (or CI environment) has no idea what keys are required.
**Why it happens:** Easy to forget the example file when adding credentials.
**How to avoid:** Always add new `.env` keys (with empty values) to `.env.example` in the same commit.
**Warning signs:** `.env.example` has no `BLIZZARD_*` keys.

---

## Code Examples

Verified patterns from official sources:

### Migration: price_snapshots

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
        Schema::create('price_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('watched_item_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('min_price');
            $table->unsignedBigInteger('avg_price');
            $table->unsignedBigInteger('median_price');
            $table->unsignedBigInteger('total_volume');
            $table->timestamp('polled_at');
            $table->timestamps();

            $table->index(['watched_item_id', 'polled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_snapshots');
    }
};
```

Source: https://laravel.com/docs/12.x/migrations

### Migration: watched_items

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
        Schema::create('watched_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('blizzard_item_id');
            $table->string('name');
            $table->unsignedInteger('buy_threshold');
            $table->unsignedInteger('sell_threshold');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('watched_items');
    }
};
```

### config/services.php Blizzard Entry

```php
// Source: https://laravel.com/docs/12.x (services.php convention)
'blizzard' => [
    'client_id'     => env('BLIZZARD_CLIENT_ID'),
    'client_secret' => env('BLIZZARD_CLIENT_SECRET'),
    'region'        => env('BLIZZARD_REGION', 'us'),
],
```

### Composite Index Syntax

```php
// Source: https://laravel.com/docs/12.x/migrations
$table->index(['watched_item_id', 'polled_at']);

// With explicit name (optional):
$table->index(['watched_item_id', 'polled_at'], 'price_snapshots_item_polled_idx');
```

### Livewire 4 Manual Install

```bash
# Source: https://livewire.laravel.com/docs/4.x/installation
composer require livewire/livewire
php artisan livewire:layout   # creates resources/views/layouts/app.blade.php
```

### Pest Install Verification

```bash
# Pest ships with Laravel 12 — verify:
php artisan test

# Or directly:
./vendor/bin/pest
```

### Pint Config (enforce strict_types via pint.json)

```json
{
    "preset": "laravel",
    "rules": {
        "declare_strict_types": true
    }
}
```

Source: https://laravel.com/docs/12.x/pint

---

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| `tailwind.config.js` content array | CSS-first `@source` / `@theme` directives | Tailwind v4 (Jan 2025) | No JS config file needed; Vite plugin auto-detects templates |
| `php artisan make:livewire` + separate Blade | Single-file components (PHP + Blade in one file) | Livewire 4 (Jan 2026) | Optional; multi-file components still work |
| Mix (webpack) for asset compilation | Vite | Laravel 9+ | Faster HMR; required for Tailwind v4 plugin |
| Livewire 3 with `wire:navigate` | Livewire 4 with `wire:transition` for animations | Livewire 4 (Jan 2026) | Smoother DOM transitions |
| PHPUnit as default test runner | Pest is now co-equal default | Laravel 11+ | Both available; Pest preferred by user decision |

**Deprecated/outdated:**
- `tailwind.config.js` as primary config: No longer needed for basic Tailwind v4 setups
- `laravel/ui` package: Replaced by official starter kits (React/Vue/Livewire)
- Livewire 3 `$wire.entangle()` for Alpine.js integration: Still works in v4 but `wire:model` covers most cases

---

## Open Questions

1. **PHP 8.3 vs 8.4 on the developer's machine**
   - What we know: PHP 8.3 is the locked target; Laravel 12 requires PHP 8.2+ minimum
   - What's unclear: Whether the developer's machine has 8.3 or 8.4 installed
   - Recommendation: Add `"php": "^8.3"` to `composer.json` require section to enforce minimum version; app will work on 8.4 too

2. **`buy_threshold` and `sell_threshold` column type**
   - What we know: User said "percentage" — implied values like 10, 15, 20 (percent below/above average)
   - What's unclear: Exact storage format — integer percentage (e.g., 15 = 15%) or decimal (0.15)?
   - Recommendation: Use `unsignedInteger` — store as integer percentage (15 = 15%). Simple, avoids decimal precision issues for thresholds that are always whole numbers in UI.

3. **`watched_items` migration ordering relative to `users` table**
   - What we know: `user_id` nullable FK references `users` table; `users` table is created by Laravel's default migration
   - What's unclear: Migration filename timestamp ordering — `watched_items` must run AFTER the `users` migration
   - Recommendation: When creating migrations, ensure `watched_items` migration filename timestamp is after `0001_01_01_000000_create_users_table.php` (it will be, since any `php artisan make:migration` uses current timestamp)

---

## Sources

### Primary (HIGH confidence)

- https://laravel.com/docs/12.x/installation — Laravel 12 install command, SQLite default, PHP requirements
- https://laravel.com/docs/12.x/migrations — `unsignedBigInteger`, `timestamp`, `index(['col1','col2'])`, `foreignId()->constrained()` syntax verified
- https://laravel.com/docs/12.x/starter-kits — Livewire starter kit contents; confirmed it includes auth scaffolding (Phase 2 scope)
- https://livewire.laravel.com/docs/4.x/installation — Livewire 4 manual install: `composer require livewire/livewire`; Laravel 10-13 compatible
- https://laravel.com/docs/12.x/pint — Pint bundled with Laravel 12; `declare_strict_types` rule available in pint.json
- https://laravel.com/docs/12.x/database-testing — Model factory `definition()` syntax, `state()`, `create()`, `count()` patterns

### Secondary (MEDIUM confidence)

- https://laravel-news.com/livewire-4-is-dropping-next-week-and-wiretransition-makes-animations-effortless — Livewire 4 release confirmed Jan 2026; wire:transition feature
- https://laravel-news.com/tailwind-css-v4-is-now-released — Tailwind v4 confirmed released; CSS-first config approach
- https://laravel.com/docs/12.x/releases — Laravel 12 released Feb 24, 2025; relatively minor release focused on upstream dependency updates and new starter kits

### Tertiary (LOW confidence)

- Multiple Medium/DEV.to articles on Actions pattern — consistent description of `app/Actions/` directory with `handle()` method; not from official Laravel docs but widely adopted community convention

---

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — all versions verified via official docs and release announcements
- Architecture: HIGH — migration syntax directly from Laravel 12 docs; Actions pattern is well-documented community convention
- Pitfalls: HIGH (schema pitfalls) / MEDIUM (Tailwind v4 config changes) — schema issues verified from docs; Tailwind v4 pitfall from multiple consistent sources

**Research date:** 2026-03-01
**Valid until:** 2026-06-01 (stable ecosystem; Livewire 4 is new so check for breaking changes if using beyond 4.0)
