---
phase: quick-14
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - app/Console/Commands/SyncCatalogCommand.php
autonomous: true
requirements: [QUICK-14]
must_haves:
  truths:
    - "Realm auction item IDs are extracted even when item objects contain extra fields (context, bonus_list, modifiers)"
    - "Commodity auction regex remains functional for simple item objects"
  artifacts:
    - path: "app/Console/Commands/SyncCatalogCommand.php"
      provides: "Streaming auction parsers for both commodity and realm endpoints"
      contains: '/"item":\{"id":(\d+)/'
  key_links:
    - from: "SyncCatalogCommand.php line 106"
      to: "Blizzard commodity auction JSON"
      via: "regex extraction"
      pattern: '"item":.{"id":'
    - from: "SyncCatalogCommand.php line 162"
      to: "Blizzard realm auction JSON"
      via: "regex extraction"
      pattern: '"item":.{"id":'
---

<objective>
Fix the realm auction streaming regex so it extracts item IDs from JSON objects that contain extra fields beyond just `id`.

Purpose: The current regex `/"item":\{"id":(\d+)\}/` requires a closing `}` immediately after the numeric ID. Realm auction items include additional fields (`context`, `bonus_list`, `modifiers`), so the `}` does not follow the ID -- a `,` does. This causes zero realm item IDs to be extracted.

Output: Updated regex on both line 106 (commodities) and line 162 (realm) that matches item IDs regardless of trailing content.
</objective>

<execution_context>
@/Users/lancethompson/.claude/get-shit-done/workflows/execute-plan.md
@/Users/lancethompson/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@app/Console/Commands/SyncCatalogCommand.php
</context>

<tasks>

<task type="auto">
  <name>Task 1: Fix item ID regex patterns for both commodity and realm auction parsing</name>
  <files>app/Console/Commands/SyncCatalogCommand.php</files>
  <action>
Change the regex on TWO lines in SyncCatalogCommand.php:

1. Line 106 (commodity parser): Change `/"item":\{"id":(\d+)\}/` to `/"item":\{"id":(\d+)/`
2. Line 162 (realm parser): Change `/"item":\{"id":(\d+)\}/` to `/"item":\{"id":(\d+)/`

The fix removes the trailing `\}` from both patterns. The pattern `"item":{"id":` is unique enough in the Blizzard API JSON that no false positives will occur. This makes both parsers robust to item objects with any number of extra fields after the id.

IMPORTANT: Also update the buffer truncation logic on both occurrences. Currently both use `strrpos($buffer, '}')` to find a safe truncation point. Since the regex no longer requires a closing brace, change the truncation to find the last match boundary instead. Use `strrpos($buffer, '"item"')` as the truncation anchor -- if found, keep from that position onward (to avoid splitting a partial match); if not found, clear the buffer. This prevents the buffer from growing unbounded.

Specifically, replace both instances of:
```php
$lastBrace = strrpos($buffer, '}');
$buffer = $lastBrace !== false ? substr($buffer, $lastBrace + 1) : '';
```
with:
```php
$lastItem = strrpos($buffer, '"item"');
$buffer = $lastItem !== false ? substr($buffer, $lastItem) : '';
```
  </action>
  <verify>
    <automated>cd /Users/lancethompson/Github/wow-ah-tracker && php -l app/Console/Commands/SyncCatalogCommand.php && grep -c '"item":\\\\{\"id\":(\\\\d+)\\}' app/Console/Commands/SyncCatalogCommand.php | grep -q '^0$' && echo "PASS: no old regex found" || echo "FAIL: old regex still present"</automated>
  </verify>
  <done>Both regex patterns updated to match item IDs without requiring a trailing `}`. Buffer truncation updated to use `"item"` as anchor. `php -l` passes syntax check. Zero occurrences of the old `\}` regex remain.</done>
</task>

</tasks>

<verification>
1. `php -l app/Console/Commands/SyncCatalogCommand.php` -- no syntax errors
2. Grep confirms old pattern `\}` at end of regex is gone from both lines
3. New pattern `/"item":\{"id":(\d+)/` appears exactly twice
</verification>

<success_criteria>
- Both commodity (line ~106) and realm (line ~162) regex patterns match item IDs regardless of extra fields in the item object
- Buffer truncation logic updated to not depend on trailing `}`
- No syntax errors in the file
</success_criteria>

<output>
After completion, create `.planning/quick/14-fix-realm-auction-regex-to-handle-item-o/14-SUMMARY.md`
</output>
