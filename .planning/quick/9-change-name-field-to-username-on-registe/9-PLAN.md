---
phase: quick-9
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - database/migrations/XXXX_XX_XX_XXXXXX_rename_name_to_username_on_users_table.php
  - app/Models/User.php
  - database/factories/UserFactory.php
  - resources/views/livewire/pages/auth/register.blade.php
  - resources/views/livewire/profile/update-profile-information-form.blade.php
  - resources/views/livewire/layout/navigation.blade.php
  - tests/Feature/Auth/RegistrationTest.php
  - tests/Feature/ProfileTest.php
autonomous: true
requirements: [QUICK-9]

must_haves:
  truths:
    - "Register form shows 'Username' label instead of 'Name'"
    - "Profile edit form shows 'Username' label instead of 'Name'"
    - "Navigation dropdown displays the user's username"
    - "DB users table has 'username' column, no 'name' column"
    - "All existing tests pass with the renamed field"
  artifacts:
    - path: "database/migrations/*_rename_name_to_username_on_users_table.php"
      provides: "Column rename migration"
      contains: "renameColumn"
    - path: "app/Models/User.php"
      provides: "Updated fillable with username"
      contains: "'username'"
  key_links:
    - from: "resources/views/livewire/pages/auth/register.blade.php"
      to: "app/Models/User.php"
      via: "User::create with validated data"
      pattern: "username"
    - from: "resources/views/livewire/layout/navigation.blade.php"
      to: "auth()->user()"
      via: "username property access"
      pattern: "->username"
---

<objective>
Rename the `name` field to `username` across the entire User domain: DB column, model, validation, views, navigation, and tests.

Purpose: The field represents a username, not a full name. Renaming makes the intent clear.
Output: Fully renamed field with migration, all references updated, all tests passing.
</objective>

<execution_context>
@/Users/lancethompson/.claude/get-shit-done/workflows/execute-plan.md
@/Users/lancethompson/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@app/Models/User.php
@resources/views/livewire/pages/auth/register.blade.php
@resources/views/livewire/profile/update-profile-information-form.blade.php
@resources/views/livewire/layout/navigation.blade.php
@database/factories/UserFactory.php
@tests/Feature/Auth/RegistrationTest.php
@tests/Feature/ProfileTest.php
</context>

<tasks>

<task type="auto">
  <name>Task 1: Create migration and update model + factory</name>
  <files>
    database/migrations/XXXX_rename_name_to_username_on_users_table.php,
    app/Models/User.php,
    database/factories/UserFactory.php
  </files>
  <action>
1. Create a new migration via `php artisan make:migration rename_name_to_username_on_users_table`. In the `up()` method, use `$table->renameColumn('name', 'username')`. In the `down()` method, use `$table->renameColumn('username', 'name')`.

2. In `app/Models/User.php`, change `$fillable` from `'name'` to `'username'`. The rest of the model stays the same.

3. In `database/factories/UserFactory.php`, change `'name' => fake()->name()` to `'username' => fake()->userName()`. Use `userName()` (not `name()`) since it generates username-style strings.

4. Run `php artisan migrate` to apply the column rename.
  </action>
  <verify>
    <automated>cd /Users/lancethompson/Github/wow-ah-tracker && php artisan migrate:status | grep username</automated>
  </verify>
  <done>Migration applied, users.name column renamed to users.username. Model fillable updated. Factory generates usernames.</done>
</task>

<task type="auto">
  <name>Task 2: Update all views, navigation, and tests</name>
  <files>
    resources/views/livewire/pages/auth/register.blade.php,
    resources/views/livewire/profile/update-profile-information-form.blade.php,
    resources/views/livewire/layout/navigation.blade.php,
    tests/Feature/Auth/RegistrationTest.php,
    tests/Feature/ProfileTest.php
  </files>
  <action>
1. **register.blade.php** — In the PHP section:
   - Change `public string $name = ''` to `public string $username = ''`
   - Change validation key from `'name'` to `'username'` (keep rules: `['required', 'string', 'max:255']`)
   - In the HTML section, change all occurrences:
     - Label: `for="name"` to `for="username"`, value `__('Name')` to `__('Username')`
     - Input: `wire:model="name"` to `wire:model="username"`, `id="name"` to `id="username"`, `name="name"` to `name="username"`, `autocomplete="name"` to `autocomplete="username"`
     - Error: `$errors->get('name')` to `$errors->get('username')`
     - Comment: `<!-- Name -->` to `<!-- Username -->`

2. **update-profile-information-form.blade.php** — In the PHP section:
   - Change `public string $name = ''` to `public string $username = ''`
   - In `mount()`: change `$this->name = Auth::user()->name` to `$this->username = Auth::user()->username`
   - In validation: change `'name'` key to `'username'` (keep rules: `['required', 'string', 'max:255']`)
   - In dispatch: change `name: $user->name` to `name: $user->username` (keep the event detail key as `name` since Alpine JS references `$event.detail.name`)
   - In the HTML section:
     - Label: `for="name"` to `for="username"`, value `__('Name')` to `__('Username')`
     - Input: `wire:model="name"` to `wire:model="username"`, `id="name"` to `id="username"`, `name="name"` to `name="username"`, `autocomplete="name"` to `autocomplete="username"`
     - Error: `$errors->get('name')` to `$errors->get('username')`

3. **navigation.blade.php** — Two places where `auth()->user()->name` is used:
   - Line 47: Change `json_encode(['name' => auth()->user()->name])` to `json_encode(['name' => auth()->user()->username])` (keep Alpine `name` variable as-is for simplicity — only the data source changes)
   - Line 98: Same change — `auth()->user()->name` to `auth()->user()->username`

4. **tests/Feature/Auth/RegistrationTest.php**:
   - Line 19: Change `->set('name', 'Test User')` to `->set('username', 'TestUser')` (no spaces, username-style)

5. **tests/Feature/ProfileTest.php**:
   - Line 28: Change `->set('name', 'Test User')` to `->set('username', 'TestUser')`
   - Line 38: Change `$user->name` to `$user->username` and expected value to `'TestUser'`
   - Line 49: Change `->set('name', 'Test User')` to `->set('username', 'TestUser')`
  </action>
  <verify>
    <automated>cd /Users/lancethompson/Github/wow-ah-tracker && php artisan test</automated>
  </verify>
  <done>All views show "Username" label and bind to username field. Navigation displays username. All tests pass.</done>
</task>

</tasks>

<verification>
- `php artisan test` passes with zero failures
- Register page shows "Username" field
- Profile edit page shows "Username" field
- Navigation dropdown shows the user's username
- DB schema confirms `username` column exists, `name` column does not
</verification>

<success_criteria>
- DB column renamed from `name` to `username` via migration
- User model fillable updated
- UserFactory generates usernames
- Register form uses "Username" label and `username` field
- Profile update form uses "Username" label and `username` field
- Navigation displays `auth()->user()->username`
- All tests pass
</success_criteria>

<output>
After completion, create `.planning/quick/9-change-name-field-to-username-on-registe/9-SUMMARY.md`
</output>
