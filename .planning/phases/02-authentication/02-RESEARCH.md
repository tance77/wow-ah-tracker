# Phase 2: Authentication - Research

**Researched:** 2026-03-01
**Domain:** Laravel Breeze (Livewire/Volt stack) + Tailwind CSS v4 + Laravel 12 authentication
**Confidence:** MEDIUM-HIGH

---

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions

- **Breeze stack:** Laravel Breeze with the Livewire/Volt stack (Livewire 4 already installed — keeps auth consistent with future dashboard components)
- **Profile management:** Include Breeze's profile management page (update name, email, password, delete account)
- **Email verification:** Not required — users access the dashboard immediately after registering
- **Dark mode:** Enabled by default on all auth views
- **Registration:** Open — anyone can create an account
- **Access model:** All users are equal, no admin role or RBAC
- **Root route:** `/` redirects to `/login` for unauthenticated, to dashboard for authenticated
- **Auth page theming:** Light WoW theming — dark background with gold/amber accents; app name "AH Tracker"
- **Shared layout:** Build a reusable dark theme + gold accents layout for auth pages and future dashboard (Phase 7)
- **Remember-me:** Checkbox on login form (Breeze default — checked = 2-week cookie, unchecked = session-only)
- **Rate limiting:** Breeze default (5 attempts → 60-second lockout)
- **Mail driver:** Log driver for local development (password reset emails written to `laravel.log`)
- **Logout:** Button visible in the navigation bar on every authenticated page

### Claude's Discretion

- Navigation bar design and layout structure
- Specific gold/amber color values and Tailwind classes
- Password validation rules beyond Breeze defaults
- Exact session lifetime configuration

### Deferred Ideas (OUT OF SCOPE)

None — discussion stayed within phase scope
</user_constraints>

---

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| AUTH-01 | User can register with email and password | Breeze publishes `register.blade.php` Volt component with email/password form + `RegisteredUserController` or Livewire form class |
| AUTH-02 | User can log in and stay logged in across sessions | Breeze provides login form with remember-me checkbox; `config/session.php` controls lifetime; remember_token column exists on users table |
| AUTH-03 | User can log out from any page | Breeze publishes `Logout` action class + navigation includes logout form; `auth` middleware group protects routes |
| AUTH-04 | User can reset password via email link | Breeze publishes forgot-password and reset-password Volt components + `VerifyEmailController`; log mail driver captures reset links locally |
</phase_requirements>

---

## Summary

Laravel Breeze is the standard, minimal authentication scaffolding package for Laravel. Its Livewire stack publishes Volt-based single-file components for register, login, forgot-password, reset-password, confirm-password, and profile pages. This is the right choice for this project given Livewire 4 is already installed and the dashboard will use Livewire components in later phases.

**Critical compatibility issue discovered:** Breeze 2.x (`breeze:install livewire`) hardcodes `livewire/livewire:^3.6.4` in its install command. The project already has `livewire/livewire:^4.2.1`. Running `breeze:install livewire` will attempt to downgrade Livewire. The resolution is a two-step approach: (1) run `breeze:install` to publish all stubs, layouts, routes, and Livewire support files, then (2) immediately restore Livewire 4 and install only Volt (`livewire/volt:^1.10`) since Volt v1.10.3 supports `^3.6.1|^4.0`. Breeze's published Volt stubs work with Livewire 4 because Livewire 4 maintains backward compatibility with the class-based Volt component syntax.

**Tailwind v4 conflict:** Breeze's Livewire stack installs Tailwind v3 via PostCSS. The project already uses Tailwind v4 via `@tailwindcss/vite` (no PostCSS). Breeze will overwrite `vite.config.js` and CSS assets with v3 configuration. The resolution is to run Breeze's Tailwind upgrade path after installation using `npx @tailwindcss/upgrade` and re-migrating to the Vite plugin approach.

**Primary recommendation:** Install Breeze (`composer require laravel/breeze --dev && php artisan breeze:install livewire --dark --pest`), then repair the Livewire downgrade and Tailwind v3 conflict with targeted fixes documented in the plan.

---

## Standard Stack

### Core

| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| laravel/breeze | ^2.3.8 | Auth scaffolding — publishes views, routes, controllers | Official Laravel minimal auth starter; full code ownership, no black-box |
| livewire/livewire | ^4.2 (already installed) | Reactive components powering auth forms | Already installed; dashboard phases use Livewire too |
| livewire/volt | ^1.10.3 | Single-file component API for Livewire | Breeze Livewire stubs use Volt routing and component class; supports v3 and v4 |
| tailwindcss | ^4.0 (already installed) | Utility CSS framework for auth views | Already configured with `@tailwindcss/vite` |

### Supporting

| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| @tailwindcss/forms | ^0.5 | Form reset plugin for Tailwind | Breeze auth forms rely on this for consistent input styling |

### Alternatives Considered

| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Breeze Livewire stack | Breeze Blade stack | Blade stack has no Livewire — future dashboard phases need Livewire; would add Livewire later anyway |
| Breeze Livewire stack | Laravel Jetstream | Jetstream is heavier (teams, API tokens, 2FA); overkill for this project |
| Volt | Livewire 4 native SFCs | v4 SFCs use identical class syntax; Volt package still works with v4 and Breeze stubs use it |

**Installation (after Breeze stubs resolve):**
```bash
# Step 1: Install Breeze and publish stubs
composer require laravel/breeze --dev
php artisan breeze:install livewire --dark --pest

# Step 2: Restore Livewire 4 (Breeze downgraded it)
composer require livewire/livewire:^4.0 --update-with-dependencies

# Step 3: Install Volt compatible with Livewire 4
composer require livewire/volt:^1.10 --update-with-dependencies
php artisan volt:install

# Step 4: Repair Tailwind v4 setup (Breeze installs v3)
npm uninstall tailwindcss postcss autoprefixer
npm install tailwindcss@^4 @tailwindcss/vite @tailwindcss/forms
# Then restore vite.config.js to use @tailwindcss/vite plugin
# And restore app.css to use @import 'tailwindcss' approach

# Step 5: Run migrations (users table already exists)
php artisan migrate

# Step 6: Build assets
npm run build
```

---

## Architecture Patterns

### What Breeze Livewire Stack Publishes

```
app/
├── Http/Controllers/Auth/
│   └── VerifyEmailController.php       # Email verification handler
├── Livewire/
│   ├── Actions/
│   │   └── Logout.php                  # Logout action class
│   └── Forms/
│       └── LoginForm.php               # Login form object with validation + auth
├── View/Components/
│   ├── AppLayout.php                   # App layout view component
│   └── GuestLayout.php                 # Guest layout view component
resources/views/
├── layouts/
│   ├── app.blade.php                   # Authenticated pages layout (nav + slot)
│   └── guest.blade.php                 # Auth pages layout (centered card)
├── livewire/
│   └── pages/
│       └── auth/
│           ├── login.blade.php         # Volt class component
│           ├── register.blade.php      # Volt class component
│           ├── forgot-password.blade.php
│           ├── reset-password.blade.php
│           ├── verify-email.blade.php
│           └── confirm-password.blade.php
├── profile/                            # Profile management views
│   ├── edit.blade.php                  # Profile edit page
│   └── partials/
│       ├── update-profile-information-form.blade.php
│       ├── update-password-form.blade.php
│       └── delete-user-form.blade.php
├── components/
│   └── nav-link.blade.php              # etc.
routes/
├── auth.php                            # Auth routes (guest + auth middleware groups)
└── web.php                             # Breeze adds dashboard route + require auth.php
tests/
└── Feature/Auth/                       # Pest tests for all auth flows
```

### Pattern 1: Volt Class-Based Auth Component

**What:** Single-file Blade/PHP component using Livewire Volt's class API. PHP class lives in `<?php ?>` block at top, Blade template follows.

**When to use:** All auth components; keeps form logic co-located with its view.

**Example (from Breeze stubs):**
```php
// resources/views/livewire/pages/auth/login.blade.php
<?php

use App\Livewire\Forms\LoginForm;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public LoginForm $form;

    public function login(): void
    {
        $this->validate();
        $this->form->authenticate();
        Session::regenerate();
        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }
};
?>

<div>
    <x-auth-session-status class="mb-4" :status="session('status')" />
    <form wire:submit="login">
        <!-- Email input, password input, remember me checkbox -->
    </form>
</div>
```

### Pattern 2: Volt Routing

**What:** Breeze uses `Volt::route()` to map URLs to Volt single-file components.

**Example (from Breeze stubs):**
```php
// routes/auth.php
use Livewire\Volt\Volt;

Route::middleware('guest')->group(function () {
    Volt::route('register', 'pages.auth.register')->name('register');
    Volt::route('login', 'pages.auth.login')->name('login');
    Volt::route('forgot-password', 'pages.auth.forgot-password')->name('password.request');
    Volt::route('reset-password/{token}', 'pages.auth.reset-password')->name('password.reset');
});

Route::middleware('auth')->group(function () {
    Volt::route('verify-email', 'pages.auth.verify-email')->name('verification.notice');
    Volt::route('confirm-password', 'pages.auth.confirm-password')->name('password.confirm');
});
```

### Pattern 3: Auth Middleware for Route Protection

**What:** Laravel 12's `auth` middleware redirects unauthenticated visitors to the `login` named route. Configured in `bootstrap/app.php`.

**Example:**
```php
// routes/web.php
Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth'])->name('dashboard');

// bootstrap/app.php — redirect unauthenticated users
->withMiddleware(function (Middleware $middleware): void {
    $middleware->redirectGuestsTo(fn (Request $request) => route('login'));
})
```

### Pattern 4: Root Route Redirect

**What:** The `/` route must redirect based on auth state. Use a conditional redirect in web.php.

**Example:**
```php
Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});
```

### Pattern 5: Tailwind v4 Dark Mode (Class-Based)

**What:** Tailwind v4 replaced the `darkMode: 'class'` config key with a CSS `@custom-variant` directive.

**Example:**
```css
/* resources/css/app.css */
@import 'tailwindcss';

@custom-variant dark (&:where(.dark, .dark *));
```

Then add `class="dark"` to the `<html>` tag for WoW dark theme:
```html
<html class="dark" lang="{{ str_replace('_', '-', app()->getLocale()) }}">
```

### Anti-Patterns to Avoid

- **Applying `auth` middleware to the root `/` route**: The root route needs to handle both states, not redirect via middleware; use `auth()->check()` conditional.
- **Using Tailwind v3 `darkMode: 'class'` config**: Tailwind v4 uses `@custom-variant` in CSS — no JS config file needed.
- **Double Alpine.js**: Breeze Blade stacks bundle Alpine in `app.js`; Livewire v4 already ships Alpine. Do not include Alpine separately — remove any standalone Alpine import from `app.js` if present.
- **Leaving email verification enabled**: The Context.md specifies no email verification; ensure `MustVerifyEmail` is NOT implemented on the User model.

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Login/registration forms | Custom Livewire auth components | `breeze:install` published stubs | Covers CSRF, session regeneration, rate limiting, remember-me edge cases |
| Password reset flow | Custom token/mail system | Breeze + Laravel's built-in `Password::sendResetLink()` | Token hashing, expiry, single-use invalidation are all handled |
| Rate limiting on login | Custom attempt counter | Breeze's `LoginForm::authenticate()` uses `RateLimiter` internally | Handles per-IP + per-email throttling |
| Remember me | Custom cookie/session logic | Breeze default checkbox + `Auth::attempt($credentials, $remember)` | Session vs persistent cookie handled by framework |
| Logout | Custom session destroy | Breeze's `Logout` action class | Handles session invalidation, CSRF token regen, redirect |

**Key insight:** Breeze publishes all code to your application — you own it fully. Use it as the starting point and customize the theming layer, not the auth mechanics.

---

## Common Pitfalls

### Pitfall 1: Breeze Downgrades Livewire to v3

**What goes wrong:** Running `php artisan breeze:install livewire` triggers `composer require livewire/livewire:^3.6.4` which downgrades the existing `livewire/livewire:^4.2.1`.

**Why it happens:** Breeze 2.x was written before Livewire 4 stable release; its `InstallsLivewireStack.php` hardcodes the v3 constraint.

**How to avoid:** After running `breeze:install`, immediately run `composer require livewire/livewire:^4.0 --update-with-dependencies` to restore v4. Volt v1.10.3 supports `^3.6.1|^4.0` so it remains compatible.

**Warning signs:** `composer show livewire/livewire` shows version `3.x` after breeze install.

### Pitfall 2: Breeze Overwrites Tailwind v4 Setup with v3

**What goes wrong:** The Breeze livewire installer copies `tailwind.config.js`, `postcss.config.js`, and Tailwind v3 packages into your project, conflicting with the existing `@tailwindcss/vite` setup.

**Why it happens:** Breeze 2.x predates Tailwind CSS v4; its stubs use PostCSS-based Tailwind v3 configuration.

**How to avoid:**
1. After `breeze:install`, remove Tailwind v3 artifacts: `npm uninstall tailwindcss postcss autoprefixer`
2. Remove `postcss.config.js` and `tailwind.config.js`
3. Reinstall Tailwind v4: `npm install tailwindcss@^4 @tailwindcss/vite @tailwindcss/forms`
4. Restore `vite.config.js` to use `@tailwindcss/vite` plugin
5. Restore `app.css` to use `@import 'tailwindcss'` (not PostCSS `tailwindcss` reference)
6. Update Breeze's published views to use `@custom-variant dark` instead of v3 `dark:` class assumptions

**Warning signs:** `vite.config.js` references `tailwindcss` as a PostCSS plugin; `tailwind.config.js` is present in the project root.

### Pitfall 3: Volt Stubs Not Found After Install

**What goes wrong:** After `breeze:install`, Volt components return 404 or fail routing because `VoltServiceProvider` is not registered or the pages directory is not mounted.

**Why it happens:** `volt:install` must be run explicitly, or the service provider was removed.

**How to avoid:** Run `php artisan volt:install` after installing Breeze and after restoring Volt to v1.10. Verify `VoltServiceProvider` is in `bootstrap/providers.php`.

**Warning signs:** `Volt::route()` throws "Undefined method" or auth pages return 404.

### Pitfall 4: Double Alpine.js Conflict

**What goes wrong:** Auth pages show Alpine.js errors or reactivity is broken because Alpine loads twice.

**Why it happens:** Breeze's published `app.js` may include an explicit Alpine import; Livewire 4 already bundles Alpine internally.

**How to avoid:** After `breeze:install`, inspect `resources/js/app.js`. If it contains `import Alpine from 'alpinejs'`, remove those lines — Livewire handles Alpine.

**Warning signs:** Browser console shows "Alpine has already been initialized" or duplicate `x-data` initialization errors.

### Pitfall 5: `declare(strict_types=1)` Missing from Published Files

**What goes wrong:** Breeze's published PHP files don't include `declare(strict_types=1)` at the top, violating project conventions set in Phase 1.

**Why it happens:** Breeze stubs don't use strict types by default.

**How to avoid:** After `breeze:install`, add `declare(strict_types=1);` to all published `.php` files: `LoginForm.php`, `Logout.php`, `AppLayout.php`, `GuestLayout.php`, `VerifyEmailController.php`.

**Warning signs:** Any PHP file in `app/` missing `declare(strict_types=1)` after install.

### Pitfall 6: Email Verification Accidentally Enabled

**What goes wrong:** Registration flow requires email verification before dashboard access.

**Why it happens:** Breeze stubs include `verify-email.blade.php` and routes. The decision is no email verification, but if `MustVerifyEmail` gets added to User model (common mistake), it activates.

**How to avoid:** Confirm `User` model does NOT implement `MustVerifyEmail`. The existing `User.php` has the interface commented out — ensure it stays commented after Breeze modifies the file (Breeze does not modify the User model, only layouts/routes/views).

---

## Code Examples

### Session Configuration for Remember Me

```php
// config/session.php
// 'lifetime' => 120  (default: 2 hours for session-only)
// Remember me token duration controlled by framework — 2 weeks (20160 minutes)
// No change needed — Breeze default behavior is correct

// .env (local dev)
MAIL_MAILER=log
// Password reset links written to storage/logs/laravel.log
```

### Log Driver for Password Reset (local dev)

```ini
# .env
MAIL_MAILER=log
MAIL_FROM_ADDRESS="noreply@ah-tracker.local"
MAIL_FROM_NAME="AH Tracker"
```

Reset link appears in `storage/logs/laravel.log` — search for `reset-password` to find the token.

### Tailwind v4 Dark Mode for WoW Theme

```css
/* resources/css/app.css */
@import 'tailwindcss';

/* Enable class-based dark mode for WoW theme */
@custom-variant dark (&:where(.dark, .dark *));

/* WoW Gold/Amber color palette */
@theme {
    --color-wow-gold: #f7a325;
    --color-wow-gold-light: #fbbf24;
    --color-wow-dark: #1a1a2e;
}
```

```html
<!-- layouts/guest.blade.php — dark by default -->
<html class="dark" lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="bg-gray-900 text-gray-100 min-h-screen">
    {{ $slot }}
    @livewireScripts
</body>
</html>
```

### Protecting Dashboard Routes

```php
// routes/web.php
declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth'])->name('dashboard');

require __DIR__.'/auth.php';
```

---

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| `darkMode: 'class'` in `tailwind.config.js` | `@custom-variant dark` in `app.css` | Tailwind v4 (Feb 2025) | Must configure dark mode in CSS, not JS config |
| Volt as standalone SFC package | Livewire 4 has native SFCs; Volt package still works | Livewire 4 (Jan 2026) | Volt package still usable with v4; Breeze stubs unchanged |
| `Volt::route()` | `Route::livewire()` (Livewire 4 native) | Livewire 4 | Volt routing still works with v4; migration optional |
| Breeze via `laravel new --breeze` | `composer require laravel/breeze --dev` then `php artisan breeze:install` | Laravel 12 (Feb 2025) | New installer doesn't include Breeze option; manual install required |
| PostCSS + `tailwind.config.js` | `@tailwindcss/vite` plugin | Tailwind v4 | PostCSS config no longer needed; Vite plugin is preferred |

**Deprecated/outdated:**
- `tailwindcss/forms` with PostCSS: Replaced by `@tailwindcss/forms` installed as npm dependency, imported via `@import '@tailwindcss/forms'` in CSS

---

## Open Questions

1. **Does `breeze:install livewire` block on Livewire v4 presence, or does it attempt a downgrade silently?**
   - What we know: The install command runs `requireComposerPackages(['livewire/livewire:^3.6.4', ...])` — Composer will attempt to resolve this
   - What's unclear: Whether Composer will error and abort, or silently downgrade
   - Recommendation: Run in a branch, verify `livewire/livewire` version immediately after, then restore v4

2. **Do Breeze's published Tailwind v3 view classes conflict visually in v4?**
   - What we know: Tailwind v4 changed some utility class names (e.g., `ring-opacity-5` → `ring-black/5`)
   - What's unclear: How many Breeze auth view classes are affected
   - Recommendation: After install, run `npx @tailwindcss/upgrade` to auto-migrate deprecated classes, then manually review auth views

3. **Is `@tailwindcss/forms` compatible with Tailwind v4?**
   - What we know: Package exists at `^0.5` and has been maintained; Tailwind v4 uses a plugin API
   - What's unclear: Whether the exact `@tailwindcss/forms` import syntax changed for v4
   - Recommendation: Import via `@import '@tailwindcss/forms'` in `app.css` (v4 CSS-first approach) and verify form inputs render correctly

---

## Validation Architecture

> `workflow.nyquist_validation` is not present in `.planning/config.json` — skipping this section.

*(Note: config.json has `workflow: { research, plan_check, verifier }` but no `nyquist_validation` key — section omitted per instructions.)*

---

## Sources

### Primary (HIGH confidence)

- Breeze 2.x GitHub source — `InstallsLivewireStack.php`, `composer.json`, stubs directory, `CHANGELOG.md` — verified hardcoded Livewire `^3.6.4` constraint and stubs structure
- Packagist `livewire/volt` v1.10.3 — verified `livewire/livewire: "^3.6.1|^4.0"` constraint — confirmed Volt is v4-compatible
- Packagist `laravel/breeze` v2.3.8 — confirmed Laravel 12 support, no Livewire listed as direct dependency
- Tailwind CSS v4 official docs — confirmed `@custom-variant dark` syntax replacing `darkMode: 'class'` config
- Laravel 12 authentication docs — confirmed `auth` middleware, `redirectGuestsTo` in `bootstrap/app.php`
- Breeze livewire auth.php stub — confirmed `Volt::route()` usage and guest/auth middleware groups

### Secondary (MEDIUM confidence)

- Laravel Daily "Breeze Upgrade Tailwind 3 to 4" guide — confirmed `npx @tailwindcss/upgrade` workflow and PostCSS removal steps
- Livewire 4 upgrade guide — confirmed Volt "superseded" by native SFCs but Volt package still compatible; `Volt::route()` can stay if volt package retained
- Laravel News "Everything new in Livewire 4" — confirmed strong backward compatibility, existing Volt class components work unchanged

### Tertiary (LOW confidence)

- Community search results about `breeze:install` in Laravel 12 — confirm it works but lack specifics on v4 conflict behavior

---

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — Breeze 2.x source code inspected, Packagist constraints verified
- Architecture (published files): HIGH — stubs directory structure confirmed via GitHub
- Livewire v4/Tailwind v4 conflict: MEDIUM — deduced from version constraints; exact Composer behavior (error vs silent downgrade) not directly tested
- Pitfalls: MEDIUM — Tailwind v3/v4 conflict well-documented; Alpine double-load documented; Livewire downgrade deduced from code

**Research date:** 2026-03-01
**Valid until:** 2026-04-01 (stable libraries; Breeze 2.x unlikely to release Livewire 4 support given Laravel 12 new starter kit direction)
