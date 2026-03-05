---
phase: quick-8
plan: 1
type: execute
wave: 1
depends_on: []
files_modified:
  - resources/views/livewire/pages/auth/login.blade.php
  - resources/views/livewire/pages/auth/register.blade.php
autonomous: true
requirements: []

must_haves:
  truths:
    - "Log in button is full-width on its own row"
    - "Forgot password and Create account links are on a separate row below"
    - "Action row maintains dark theme and wow-gold hover styling"
    - "Register page has 'Already have an account? Log in' link (if missing)"
  artifacts:
    - path: "resources/views/livewire/pages/auth/login.blade.php"
      provides: "Refactored action section with proper layout (lines 59-75)"
      min_lines: 10
    - path: "resources/views/livewire/pages/auth/register.blade.php"
      provides: "Cross-link to login if missing"
      min_lines: 80
  key_links:
    - from: "login.blade.php"
      to: "Action row layout"
      via: "Tailwind flex classes and container structure"
      pattern: "flex.*mt-4"
---

<objective>
Fix the cramped action row in the login page where "Forgot your password?", "Create an account", and "Log in" button are all squeezed on one line. Restructure to separate the button onto its own full-width row, with links below.

Purpose: Improve UX by giving the primary action (Log in button) visual prominence and room to breathe. Makes the page feel less cramped.
Output: Refactored login.blade.php with multi-row action section, matching register.blade.php link pattern.
</objective>

<execution_context>
@/Users/lancethompson/.claude/get-shit-done/workflows/execute-plan.md
</execution_context>

<context>
@/Users/lancethompson/Github/wow-ah-tracker/.planning/STATE.md
@/Users/lancethompson/Github/wow-ah-tracker/resources/views/livewire/pages/auth/login.blade.php (lines 59-75)
@/Users/lancethompson/Github/wow-ah-tracker/resources/views/livewire/pages/auth/register.blade.php (lines 78-86)

Current login action row (lines 59-75) has a single `flex items-center justify-end mt-4` div containing:
- Forgot password link (conditionally rendered)
- Create account link (conditionally rendered)
- Primary button

All three crammed on one line.

Register page pattern (lines 78-86) shows how to do it cleanly: link on left, button on right, in a flex row.
</context>

<tasks>

<task type="auto">
  <name>Task 1: Restructure login page action row — button on own row, links below</name>
  <files>resources/views/livewire/pages/auth/login.blade.php</files>
  <action>
Replace lines 59-75 (current single-row action div) with a two-section layout:

SECTION 1 — Log in button (full width):
```blade
<div class="mt-6">
    <x-primary-button class="w-full justify-center">
        {{ __('Log in') }}
    </x-primary-button>
</div>
```

SECTION 2 — Links (centered or spread):
```blade
<div class="flex items-center justify-center gap-4 mt-4">
    @if (Route::has('password.request'))
        <a class="underline text-sm text-gray-400 hover:text-wow-gold rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-wow-gold focus:ring-offset-wow-darker transition-colors" href="{{ route('password.request') }}" wire:navigate>
            {{ __('Forgot your password?') }}
        </a>
    @endif

    @if (Route::has('register'))
        <a class="underline text-sm text-gray-400 hover:text-wow-gold rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-wow-gold focus:ring-offset-wow-darker transition-colors" href="{{ route('register') }}" wire:navigate>
            {{ __('Create an account') }}
        </a>
    @endif
</div>
```

Keep all styling consistent (text-gray-400, hover:text-wow-gold, focus rings, transitions). The goal is visual breathing room: primary action gets full width, secondary actions get equal prominence below.
  </action>
  <verify>
Visit http://localhost:8000/login and confirm:
1. Log in button is centered, full-width (taking most of the column width)
2. "Forgot your password?" and "Create an account" links are on the line below
3. Links are centered or balanced with gap between them
4. Hover states work (text turns wow-gold on hover)
5. Focus ring styling preserved

Command: Open browser to login page and visually inspect layout. No automated test needed for UI layout.
  </verify>
  <done>
Login action section uses two distinct rows. Button row is full-width. Links row is below, centered. All styling (colors, hover, focus) preserved. No console errors on page load.
  </done>
</task>

<task type="auto">
  <name>Task 2: Verify register page has login link (add if missing)</name>
  <files>resources/views/livewire/pages/auth/register.blade.php</files>
  <action>
Check register.blade.php lines 78-86. Currently has "Already registered?" link pointing to login route.

If the text says "Already registered?" — this is correct; no change needed. It's slightly different from "Already have an account?" but both are clear.

If the register page is completely missing a login link — add one with identical styling to the login page links:

```blade
<div class="flex items-center justify-center gap-4 mt-4">
    <a class="underline text-sm text-gray-400 hover:text-wow-gold rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-wow-gold focus:ring-offset-wow-darker transition-colors" href="{{ route('login') }}" wire:navigate>
        {{ __('Already have an account?') }}
    </a>

    <x-primary-button class="ms-4">
        {{ __('Register') }}
    </x-primary-button>
</div>
```

Current register page (lines 78-86) DOES have a link, so this task is a safety check. Most likely: no changes needed.
  </action>
  <verify>
Open http://localhost:8000/register and confirm:
1. Link to login exists (text "Already registered?" or similar)
2. Link styling matches login page (gray-400, hover:wow-gold)
3. Button styling consistent

If link is present and styled correctly: task complete, no changes.
If link is missing or unstyled: apply changes and reverify.
  </verify>
  <done>
Register page has a clear, styled link to login page. If already present (which it is), task complete with no changes. If it was missing, it's now added with matching styling to login page.
  </done>
</task>

</tasks>

<verification>
After both tasks complete:
1. Visit http://localhost:8000/login — button full-width, links below
2. Visit http://localhost:8000/register — has login link with matching styling
3. Test login flow: fill form, click button, verify form submits
4. Test register flow: fill form, click button, verify form submits
5. Verify no console errors or Livewire issues
</verification>

<success_criteria>
- Login page action section has clean two-row layout (button on own row, links below)
- All styling preserved (wow-gold hover, dark theme, focus rings)
- Register page has login link with consistent styling
- Both pages pass visual inspection and interactive tests
- Git diff shows only targeted changes to action sections
</success_criteria>

<output>
After completion, create `.planning/quick/8-spruce-up-login-page-layout/8-SUMMARY.md` with:
- Tasks completed (2/2)
- Changes made (action row restructuring)
- Visual improvements (spacing, hierarchy, breathing room)
- Files modified (login.blade.php, register.blade.php)
- Verification steps passed
</output>
