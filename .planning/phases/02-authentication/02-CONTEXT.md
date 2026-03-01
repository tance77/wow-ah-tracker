# Phase 2: Authentication - Context

**Gathered:** 2026-03-01
**Status:** Ready for planning

<domain>
## Phase Boundary

Secure user access to the application: registration, login/logout, session persistence, and password reset. All dashboard and admin routes require authentication. No public-facing pages — unauthenticated visitors are redirected to login.

</domain>

<decisions>
## Implementation Decisions

### Breeze stack choice
- Use Laravel Breeze with the Livewire/Volt stack (Livewire 4 already installed — keeps auth consistent with future dashboard components)
- Include Breeze's profile management page (update name, email, password, delete account)
- No email verification required — users access the dashboard immediately after registering
- Dark mode enabled by default on all auth views

### User access model
- Open registration — anyone can create an account via the registration form
- All users are equal — no admin role or role-based access control
- No public landing page — the `/` route redirects to `/login` for unauthenticated visitors, and to the dashboard for authenticated users

### Auth page appearance
- Light WoW theming: dark background with gold/amber accents
- App name displayed on the login page (pick a fitting name like "AH Tracker")
- Build a shared app layout (dark theme + gold accents) reusable by both auth pages and future dashboard pages in Phase 7

### Session & security
- Remember-me checkbox on login form (Breeze default — checked = 2-week cookie, unchecked = session-only)
- Rate limiting on login attempts (Breeze default — 5 attempts then 60-second lockout)
- Log mail driver for local development (password reset emails written to laravel.log)
- Logout button visible in the navigation bar on every authenticated page

### Claude's Discretion
- Exact Breeze stack variant (Livewire vs Blade) — leaning Livewire/Volt for consistency
- Navigation bar design and layout structure
- Specific gold/amber color values and Tailwind classes
- Password validation rules beyond Breeze defaults
- Exact session lifetime configuration

</decisions>

<specifics>
## Specific Ideas

- Dark background with gold/amber accents — WoW gold color palette
- Shared layout that serves as the foundation for the entire app (auth + dashboard)
- No public-facing pages at all — auth is the front door

</specifics>

<code_context>
## Existing Code Insights

### Reusable Assets
- Livewire 4 already installed via composer — Breeze Livewire stack can leverage this directly
- Tailwind CSS v4 configured — dark mode utilities available for theming
- `resources/views/layouts/` directory exists — shared layout can be placed here

### Established Patterns
- `declare(strict_types=1)` used in routes — maintain this convention
- Basic `routes/web.php` with closure route — Breeze will expand this with auth routes

### Integration Points
- `routes/web.php` — Breeze adds auth routes here automatically
- `app/Models/User.php` — Breeze expects the default User model (already exists in Laravel 12)
- `resources/views/layouts/` — shared layout for auth views and future dashboard
- `config/session.php` — session lifetime and remember-me configuration

</code_context>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 02-authentication*
*Context gathered: 2026-03-01*
