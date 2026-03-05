---
phase: quick-7
plan: 1
subsystem: auth-ui
tags: [auth, login, registration, navigation, livewire]
dependency_graph:
  requires: []
  provides: [register-link-on-login]
  affects: [login-page]
tech_stack:
  added: []
  patterns: [blade-conditional-route, wire-navigate]
key_files:
  created: []
  modified:
    - resources/views/livewire/pages/auth/login.blade.php
decisions:
  - Wrapped register link in Route::has('register') guard matching the password.request pattern already in use
  - Used ms-4 spacing between the two links to give visual separation without crowding
metrics:
  duration: "< 5 minutes"
  completed: "2026-03-04"
  tasks_completed: 1
  files_changed: 1
---

# Quick Task 7: Add Register Link to Login Page Summary

**One-liner:** Added "Create an account" link to the login form using wire:navigate and wow-gold hover styling matching existing auth link patterns.

## What Was Done

Added a "Create an account" link to the login page's action row (the flex container holding "Forgot your password?" and "Log in"). The link appears between the forgot-password link and the Log in button, giving new users a clear path to registration directly from the login screen.

## Tasks Completed

| Task | Name | Commit | Files |
|------|------|--------|-------|
| 1 | Add register link to login page | 8e24758 | resources/views/livewire/pages/auth/login.blade.php |

## Implementation Details

The register link was added at line 66-70 of the login blade template:

```blade
@if (Route::has('register'))
    <a class="underline text-sm text-gray-400 hover:text-wow-gold rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-wow-gold focus:ring-offset-wow-darker transition-colors ms-4" href="{{ route('register') }}" wire:navigate>
        {{ __('Create an account') }}
    </a>
@endif
```

Key implementation choices:
- Identical Tailwind classes to "Forgot your password?" link for visual consistency
- Added `ms-4` left margin for spacing between the two text links
- Conditional `Route::has('register')` guard matching the existing forgot-password pattern
- `wire:navigate` for SPA-style navigation consistent with the rest of the app

## Deviations from Plan

None - plan executed exactly as written.

## Verification Criteria

- [x] Register link is visible on the login page
- [x] Link styling matches "Forgot your password?" styling (same Tailwind classes)
- [x] Link uses wire:navigate for navigation
- [x] Link has proper accessibility (focus states with ring classes)
- [x] Wrapped in Route::has('register') guard for safety

## Self-Check: PASSED

- File modified: resources/views/livewire/pages/auth/login.blade.php - FOUND
- Commit 8e24758 - FOUND
