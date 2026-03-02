---
phase: 02-authentication
plan: "01"
subsystem: auth
tags: [breeze, livewire, volt, tailwind, dark-theme, wow-theme, auth, pint]

requires:
  - phase: 01-project-foundation
    provides: Laravel 12 scaffold with Livewire 4, Tailwind v4 CSS-first config, Pest, Pint, SQLite DB

provides:
  - Breeze Livewire/Volt auth scaffolding (register, login, logout, forgot-password, reset-password, confirm-password, profile)
  - Livewire v4 restored after Breeze downgrade (v4.2.1)
  - Volt v1.10.3 with VoltServiceProvider registered
  - Tailwind v4 CSS-first config preserved (no PostCSS, no tailwind.config.js)
  - WoW dark theme: bg-wow-darker/bg-wow-dark backgrounds, wow-gold/wow-gold-light/wow-gold-dark accent colors
  - All auth layouts with class="dark" on html element
  - All Breeze-published PHP files with declare(strict_types=1)
  - Mail driver set to log for local dev (password reset emails to laravel.log)

affects: [03-blizzard-api-client, 07-dashboard-charts-ui, all phases with auth-gated routes]

tech-stack:
  added:
    - laravel/breeze:^2.3 (dev)
    - livewire/volt:^1.10.3
    - @tailwindcss/forms:^0.5.11 (via @plugin in CSS)
  patterns:
    - Volt single-file components for all auth pages (PHP logic + Blade in one .blade.php file)
    - @plugin '@tailwindcss/forms' syntax (not @import) for Tailwind v4
    - class="dark" always on <html> element for forced dark mode
    - WoW custom colors via @theme block in app.css

key-files:
  created:
    - app/Livewire/Forms/LoginForm.php
    - app/Livewire/Actions/Logout.php
    - app/View/Components/AppLayout.php
    - app/View/Components/GuestLayout.php
    - app/Http/Controllers/Auth/VerifyEmailController.php
    - app/Providers/VoltServiceProvider.php
    - resources/views/layouts/guest.blade.php
    - resources/views/livewire/layout/navigation.blade.php
    - resources/views/livewire/pages/auth/login.blade.php
    - resources/views/livewire/pages/auth/register.blade.php
    - resources/views/livewire/pages/auth/forgot-password.blade.php
    - resources/views/livewire/pages/auth/reset-password.blade.php
    - resources/views/livewire/pages/auth/confirm-password.blade.php
    - resources/views/livewire/pages/auth/verify-email.blade.php
    - routes/auth.php
  modified:
    - resources/views/layouts/app.blade.php
    - resources/views/layouts/guest.blade.php
    - resources/css/app.css
    - vite.config.js
    - package.json
    - composer.json
    - bootstrap/providers.php
    - .env
    - .env.example
    - resources/views/components/primary-button.blade.php
    - resources/views/components/text-input.blade.php
    - resources/views/components/input-label.blade.php
    - resources/views/components/nav-link.blade.php
    - resources/views/components/dropdown.blade.php
    - resources/views/components/dropdown-link.blade.php
    - resources/views/components/responsive-nav-link.blade.php

key-decisions:
  - "Tailwind v4 @plugin syntax used for @tailwindcss/forms (not @import) — v4 plugin API requirement"
  - "Livewire v4 restored after Breeze downgrade to v3.7.11 — Breeze stubs are v3-era but Volt 1.10.3 supports ^3.6.1|^4.0"
  - "tailwind.config.js and postcss.config.js removed — Breeze creates them but Phase 1 used CSS-first @tailwindcss/vite approach"
  - "User model MustVerifyEmail kept commented out — no email verification required per CONTEXT.md decision"
  - "Mail driver set to log for local dev — password reset emails written to laravel.log for testing"

patterns-established:
  - "Volt components: PHP logic block + Blade template in single .blade.php file"
  - "Auth theming: bg-wow-darker body, bg-wow-dark card, wow-gold primary actions, gray-400 secondary text"
  - "Dark mode: class=dark on html always-on, no dark: prefix needed for base styles"

requirements-completed: [AUTH-01, AUTH-02, AUTH-03, AUTH-04]

duration: 9min
completed: 2026-03-01
---

# Phase 2 Plan 01: Breeze Livewire Auth Stack Summary

**Laravel Breeze Livewire/Volt auth scaffolding with Livewire v4 restored, Tailwind v4 preserved, and WoW dark/gold theme applied to all auth views**

## Performance

- **Duration:** 9 min
- **Started:** 2026-03-01T19:59:33Z
- **Completed:** 2026-03-01T20:08:00Z
- **Tasks:** 2
- **Files modified:** 56 (created) + 34 (themed/styled)

## Accomplishments

- Installed Breeze Livewire/Volt stack and immediately restored Livewire v4.2.1 (Breeze downgraded to v3.7.11)
- Preserved Tailwind v4 CSS-first approach: removed Breeze-generated tailwind.config.js and postcss.config.js, restored vite.config.js with @tailwindcss/vite plugin
- Applied WoW dark theme (bg-wow-darker body, bg-wow-dark cards, wow-gold accents) to all auth layouts, navigation, and form components
- Added declare(strict_types=1) to all Breeze-published PHP files via Pint
- Configured mail driver to log for local password reset email testing

## Task Commits

Each task was committed atomically:

1. **Task 1: Install Breeze Livewire stack and repair Livewire v4 + Tailwind v4 conflicts** - `211fbb1` (feat)
2. **Task 2: Apply WoW dark theme to auth layouts and add strict_types** - `1ba3ea0` (feat)

## Files Created/Modified

Key files:
- `resources/views/layouts/guest.blade.php` - Dark WoW guest layout with AH Tracker brand, dark card for auth forms
- `resources/views/layouts/app.blade.php` - Dark WoW authenticated layout with dark navigation
- `resources/views/livewire/layout/navigation.blade.php` - WoW-themed nav with wow-gold brand link and dropdown
- `resources/css/app.css` - Tailwind v4 with @plugin '@tailwindcss/forms', WoW theme colors, @custom-variant dark
- `vite.config.js` - Restored @tailwindcss/vite plugin (not PostCSS)
- `app/Livewire/Forms/LoginForm.php` - Login form with rate limiting and remember-me
- `app/Livewire/Actions/Logout.php` - Logout action class
- `routes/auth.php` - Auth routes using Volt::route()
- All `resources/views/livewire/pages/auth/*.blade.php` - WoW-themed auth views
- All `resources/views/components/*.blade.php` - WoW-themed UI components

## Decisions Made

- Used `@plugin '@tailwindcss/forms'` syntax (not `@import`) — required by Tailwind v4 plugin API
- Removed Breeze-created tailwind.config.js and postcss.config.js immediately — Phase 1 established CSS-first Tailwind v4 approach that must be preserved
- Applied class="dark" always on html element (not conditional toggle) — simpler, matches design intent of always-dark app
- Kept User model MustVerifyEmail commented out per CONTEXT.md decision (no email verification needed)

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] Fixed @tailwindcss/forms import syntax for Tailwind v4**
- **Found during:** Task 1 (Step 7 - Build verification)
- **Issue:** Plan specified `@import '@tailwindcss/forms'` which fails in Tailwind v4 — forms plugin must use `@plugin` directive
- **Fix:** Changed to `@plugin '@tailwindcss/forms'` in app.css
- **Files modified:** resources/css/app.css
- **Verification:** npm run build completed successfully
- **Committed in:** 211fbb1 (Task 1 commit)

---

**Total deviations:** 1 auto-fixed (1 blocking)
**Impact on plan:** Necessary correction for Tailwind v4 compatibility. No scope creep.

## Issues Encountered

- Breeze v2.3 stubs are v3-era and downgrade Livewire from v4.2.1 to v3.7.11 — restored immediately via `composer require livewire/livewire:^4.0`
- Breeze creates tailwind.config.js and postcss.config.js — removed immediately to preserve Phase 1's CSS-first Tailwind v4 setup
- Both issues were anticipated in the plan and handled as documented

## User Setup Required

None - no external service configuration required. Mail is set to log driver for local testing.

## Next Phase Readiness

- Complete auth scaffolding ready: register, login, logout, forgot-password, reset-password, confirm-password, and profile management all functional
- Dark WoW theme foundation established — Phase 7 dashboard can extend the same theme patterns
- All auth routes registered and verified via php artisan route:list
- Migrations already up to date (sessions table from Phase 1)

---
*Phase: 02-authentication*
*Completed: 2026-03-01*
