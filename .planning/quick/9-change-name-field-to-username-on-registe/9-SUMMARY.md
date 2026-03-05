---
phase: quick-9
plan: "01"
subsystem: auth
tags: [rename, users, username, migration, livewire, views, tests]
dependency_graph:
  requires: []
  provides: [users.username column, username in register/profile views]
  affects: [auth, profile, navigation]
tech_stack:
  added: []
  patterns: [renameColumn migration, Livewire Volt component property rename]
key_files:
  created:
    - database/migrations/2026_03_05_014546_rename_name_to_username_on_users_table.php
  modified:
    - app/Models/User.php
    - database/factories/UserFactory.php
    - resources/views/livewire/pages/auth/register.blade.php
    - resources/views/livewire/profile/update-profile-information-form.blade.php
    - resources/views/livewire/layout/navigation.blade.php
    - tests/Feature/Auth/RegistrationTest.php
    - tests/Feature/ProfileTest.php
decisions:
  - Kept Alpine JS event detail key as 'name' (profile-updated event) for simplicity; only the data source changed to ->username
metrics:
  duration: "~3 minutes"
  completed_date: "2026-03-05"
  tasks_completed: 2
  tasks_total: 2
  files_created: 1
  files_modified: 7
---

# Phase quick-9 Plan 01: Change Name Field to Username Summary

**One-liner:** Renamed users.name DB column to users.username via migration and updated all model, factory, views, navigation, and tests accordingly.

## Tasks Completed

| Task | Name | Commit | Files |
|------|------|--------|-------|
| 1 | Create migration and update model + factory | c197c29 | migration, User.php, UserFactory.php |
| 2 | Update all views, navigation, and tests | 3eaa4fb | register.blade.php, update-profile-information-form.blade.php, navigation.blade.php, RegistrationTest.php, ProfileTest.php |

## What Was Built

Renamed the `name` field to `username` across the entire User domain:

- **Migration:** `renameColumn('name', 'username')` with reversible `down()` method
- **User model:** `$fillable` updated from `'name'` to `'username'`
- **UserFactory:** Changed `fake()->name()` to `fake()->userName()` to generate username-style strings
- **Register form:** Label shows "Username", all form bindings use `username` property
- **Profile edit form:** Label shows "Username", mount/validate/dispatch all use `username`
- **Navigation:** Both desktop and mobile menus read `auth()->user()->username`
- **Tests:** RegistrationTest and ProfileTest updated to set/assert `username`

## Verification

- `php artisan migrate` ran successfully — column renamed in DB
- `php artisan test` — 114 tests passed, 285 assertions, zero failures

## Deviations from Plan

None — plan executed exactly as written.

## Self-Check: PASSED

Files confirmed:
- database/migrations/2026_03_05_014546_rename_name_to_username_on_users_table.php (exists)
- app/Models/User.php (modified)
- database/factories/UserFactory.php (modified)
- resources/views/livewire/pages/auth/register.blade.php (modified)
- resources/views/livewire/profile/update-profile-information-form.blade.php (modified)
- resources/views/livewire/layout/navigation.blade.php (modified)
- tests/Feature/Auth/RegistrationTest.php (modified)
- tests/Feature/ProfileTest.php (modified)

Commits confirmed:
- c197c29: feat(quick-9): rename name to username in migration, model, and factory
- 3eaa4fb: feat(quick-9): update views, navigation, and tests for username field
