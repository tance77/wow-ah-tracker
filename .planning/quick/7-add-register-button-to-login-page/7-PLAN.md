---
phase: quick-7
plan: 1
type: execute
wave: 1
depends_on: []
files_modified:
  - resources/views/livewire/pages/auth/login.blade.php
autonomous: true
requirements: []
---

<objective>
Add a register link/button to the login page so users can navigate to the registration page.

Purpose: Provide a clear call-to-action for new users who need to create an account
Output: Login page with visible register link
</objective>

<execution_context>
@/Users/lancethompson/.claude/get-shit-done/workflows/execute-plan.md
</execution_context>

<context>
@.planning/PROJECT.md
@.planning/STATE.md

Current login page: /Users/lancethompson/Github/wow-ah-tracker/resources/views/livewire/pages/auth/login.blade.php
Registration route exists at `route('register')` in routes/auth.php (verified)
</context>

<tasks>

<task type="auto">
  <name>Task 1: Add register link to login page</name>
  <files>resources/views/livewire/pages/auth/login.blade.php</files>
  <action>
    Add a register link alongside the "Forgot your password?" link in the login form.

    Location: In the flex container at line 59, after the "Forgot your password?" conditional link, add a new link to route('register').

    Style matching: Use the same Tailwind classes as the "Forgot your password?" link for consistency:
    - underline text-sm text-gray-400 hover:text-wow-gold rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-wow-gold focus:ring-offset-wow-darker transition-colors
    - Use wire:navigate for navigation
    - Text: "Create an account" or "Register"

    Layout: The button should appear between the "Forgot your password?" link and the "Log in" button. Adjust flex spacing as needed to keep the layout clean.
  </action>
  <verify>
    npm test or manual check:
    1. Navigate to http://localhost (or test server)
    2. Verify login page displays
    3. Verify register link is visible and styled consistently
    4. Click register link and confirm navigation to registration page works
  </verify>
  <done>Login page contains a visible, styled link to the registration page. Link uses wire:navigate and matches existing UI styling (gold on hover, proper focus states).</done>
</task>

</tasks>

<verification>
- Register link is visible on the login page
- Link styling matches "Forgot your password?" styling
- Link navigates to registration page when clicked
- Link has proper accessibility (focus states, wire:navigate)
</verification>

<success_criteria>
- Register link added to login form
- Text is clear and discoverable
- Styling consistent with existing links on the page
- Navigation works (wire:navigate to route('register'))
</success_criteria>

<output>
After completion, create `.planning/quick/7-add-register-button-to-login-page/7-SUMMARY.md`
</output>
