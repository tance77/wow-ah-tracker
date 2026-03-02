---
phase: 01-project-foundation
plan: "01"
subsystem: infra
tags: [laravel, livewire, tailwind, pest, pint, blizzard-api]

# Dependency graph
requires: []
provides:
  - Laravel 12 application skeleton booting and serving default route
  - Livewire 4 installed with layout template at resources/views/layouts/app.blade.php
  - Tailwind CSS v4 compiled via Vite with @tailwindcss/vite plugin
  - Blizzard API credentials wired through config/services.php
  - Pint code style enforcement with declare_strict_types rule
  - Pest test suite operational with pestphp/pest-plugin-laravel
  - app/Actions/ and app/Services/ directory conventions
affects:
  - 01-02 (database schema)
  - 01-03 (data seeding)
  - 02-auth (auth system built on this skeleton)
  - 04-api-integration (BlizzardTokenService uses config/services.blizzard)

# Tech tracking
tech-stack:
  added:
    - laravel/framework ^12.0
    - livewire/livewire ^4.2
    - "@tailwindcss/vite ^4.0 (CSS-first, no tailwind.config.js)"
    - pestphp/pest ^3.8
    - pestphp/pest-plugin-laravel ^3.2
    - laravel/pint ^1.24
  patterns:
    - CSS-first Tailwind v4 (no tailwind.config.js, use @import in app.css)
    - declare_strict_types enforced on all PHP files via Pint
    - Single-purpose classes in app/Actions/, stateful services in app/Services/
    - Blizzard API credentials via env() -> config/services.blizzard chain

key-files:
  created:
    - composer.json (PHP ^8.4 requirement, laravel/livewire/pest dependencies)
    - pint.json (laravel preset + declare_strict_types rule)
    - config/services.php (Blizzard API credential wiring)
    - resources/views/layouts/app.blade.php (Livewire layout with @livewireStyles/@livewireScripts)
    - app/Actions/.gitkeep
    - app/Services/.gitkeep
    - .env.example (template with BLIZZARD_* keys)
  modified:
    - vite.config.js (Tailwind v4 plugin configured)
    - resources/css/app.css (@import "tailwindcss" CSS-first)

key-decisions:
  - "PHP ^8.4 enforced in composer.json (locked project decision — not ^8.2 Laravel default)"
  - "Pest installed with pest-plugin-laravel; PHPUnit upgraded from ^11 to support Pest v3"
  - "Pint applied declare_strict_types to all scaffolded PHP files immediately"
  - "No Livewire starter kit used — bare Laravel + manual Livewire install to avoid Flux/Volt/auth scaffolding"

patterns-established:
  - "Pattern: Strict types — all PHP files must declare(strict_types=1) enforced by Pint"
  - "Pattern: Config chain — env() in .env -> config/services.php -> config() in code"
  - "Pattern: Directory conventions — app/Actions/ for single-purpose classes, app/Services/ for stateful services"

requirements-completed: []

# Metrics
duration: 3min
completed: 2026-03-01
---

# Phase 1 Plan 01: Project Foundation Summary

**Laravel 12 skeleton with Livewire 4, Tailwind CSS v4 (CSS-first), Pest test suite, Pint strict-types enforcement, and Blizzard API credential wiring via config/services.php**

## Performance

- **Duration:** 3 min
- **Started:** 2026-03-01T19:19:33Z
- **Completed:** 2026-03-01T19:23:07Z
- **Tasks:** 2 completed
- **Files modified:** 60+

## Accomplishments
- Laravel 12 project scaffolded with PHP ^8.4 requirement (locking the team to 8.4 minimum)
- Livewire 4.2 installed with generated layout template containing @livewireStyles/@livewireScripts directives
- Tailwind CSS v4 configured via @tailwindcss/vite plugin with CSS-first @import in app.css (no tailwind.config.js)
- Blizzard API credentials wired through .env -> config/services.blizzard chain, ready for Phase 4 BlizzardTokenService
- Pint with declare_strict_types applied to all scaffolded PHP files; Pest test suite operational with 2 passing tests

## Task Commits

Each task was committed atomically:

1. **Task 1: Create bare Laravel 12 project and install Livewire 4** - `65680ec` (feat)
2. **Task 2: Configure development tooling and Blizzard API credential wiring** - `6932948` (feat)

**Plan metadata:** (docs commit — see below)

## Files Created/Modified
- `/composer.json` - PHP ^8.4, livewire/livewire ^4.2, pestphp/pest ^3.8, pestphp/pest-plugin-laravel ^3.2
- `/pint.json` - Laravel preset with declare_strict_types rule enforced
- `/config/services.php` - Blizzard API credential block wired to BLIZZARD_* env vars
- `/resources/views/layouts/app.blade.php` - Livewire layout with @livewireStyles/@livewireScripts
- `/.env.example` - Template with BLIZZARD_CLIENT_ID, BLIZZARD_CLIENT_SECRET, BLIZZARD_REGION
- `/vite.config.js` - Tailwind v4 plugin configured via @tailwindcss/vite
- `/resources/css/app.css` - CSS-first @import "tailwindcss"
- `/app/Actions/.gitkeep` - Actions directory convention
- `/app/Services/.gitkeep` - Services directory convention
- All PHP scaffold files updated with declare(strict_types=1) via Pint

## Decisions Made
- **PHP ^8.4 in composer.json**: Laravel 12 defaults to ^8.2; updated to enforce PHP 8.4 minimum per project decision
- **Pest installation**: Pest 3.x required upgrading PHPUnit from ^11 to compatible version; used `--with-all-dependencies` flag to resolve
- **No Livewire starter kit**: Used bare install + `php artisan livewire:layout` to avoid Flux UI, Volt, and auth scaffolding (Phase 2 scope)
- **Pint applied immediately**: Ran Pint on all scaffolded files so codebase starts 100% conformant with declare_strict_types rule

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 2 - Missing Critical] Applied Pint declare_strict_types to all scaffold files**
- **Found during:** Task 2 (Pint configuration)
- **Issue:** Running `pint --test` showed all scaffolded Laravel files failed the declare_strict_types check
- **Fix:** Ran `./vendor/bin/pint` to apply fixes to all 26 affected files
- **Files modified:** app/, bootstrap/, config/, database/, public/, routes/, tests/ (26 PHP files)
- **Verification:** `pint --test` returns `{"result":"pass"}` after fix
- **Committed in:** 6932948 (Task 2 commit)

**2. [Rule 3 - Blocking] Installed Pest explicitly (not in default Laravel 12 scaffold)**
- **Found during:** Task 2 (Pest verification)
- **Issue:** Plan stated "Pest ships with Laravel 12" but vendor/bin/pest did not exist after scaffolding
- **Fix:** Ran `composer require pestphp/pest pestphp/pest-plugin-laravel --dev --with-all-dependencies`
- **Files modified:** composer.json, composer.lock
- **Verification:** `./vendor/bin/pest` passes 2 tests
- **Committed in:** 6932948 (Task 2 commit)

---

**Total deviations:** 2 auto-fixed (1 missing critical, 1 blocking)
**Impact on plan:** Both fixes necessary for correctness and test suite operation. No scope creep.

## Issues Encountered
- Pest 4.x conflicts with PHPUnit ^11 constraint in composer.json; resolved by installing Pest 3.x with `--with-all-dependencies` flag, which upgraded PHPUnit to a compatible version without breaking existing tests.

## User Setup Required
Replace the placeholder credentials in `.env` with real Blizzard OAuth client credentials:
```
BLIZZARD_CLIENT_ID=<your actual client ID from developer.battle.net>
BLIZZARD_CLIENT_SECRET=<your actual client secret>
BLIZZARD_REGION=us
```
These are consumed by `config('services.blizzard.client_id')` — no code changes needed, only .env values.

## Next Phase Readiness
- Application boots and serves HTTP 200 on default route
- Tailwind CSS v4 compiles via `npm run build`
- Livewire 4 ready for component development
- Blizzard API config ready for Phase 4 BlizzardTokenService
- Pest test suite provides TDD foundation for all subsequent phases
- Ready to proceed to Phase 1 Plan 02 (database schema)

---
*Phase: 01-project-foundation*
*Completed: 2026-03-01*
