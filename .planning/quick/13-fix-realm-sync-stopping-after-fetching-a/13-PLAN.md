---
phase: quick-13
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - app/Console/Commands/SyncCatalogCommand.php
autonomous: true
requirements: [QUICK-13]
must_haves:
  truths:
    - "Running blizzard:sync-catalog --realm with cached IDs completes the full pipeline (realm fetch, dedup, item lookup)"
    - "Realm auction response is streamed to disk and parsed incrementally, not loaded into memory via ->json()"
  artifacts:
    - path: "app/Console/Commands/SyncCatalogCommand.php"
      provides: "Streaming realm auction fetch matching the commodities pattern"
  key_links:
    - from: "SyncCatalogCommand --realm block"
      to: "Blizzard connected-realm auctions API"
      via: "HTTP sink to temp file + regex stream parse"
      pattern: "sink.*tempnam.*preg_match_all"
---

<objective>
Fix --realm flag on blizzard:sync-catalog hanging/stopping after "Fetching realm auctions for connected-realm 76..." message.

Purpose: The realm auctions endpoint returns a large JSON response (100K+ individual auction listings). The current implementation uses `->json('auctions', [])` which attempts to decode the entire response into memory, causing PHP to exhaust memory or hang indefinitely. The fix is to use the same streaming/sink approach already used for the commodities endpoint.

Output: Updated SyncCatalogCommand where realm auction fetch uses sink-to-file + regex stream parsing instead of in-memory JSON decoding.
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
  <name>Task 1: Switch realm auction fetch from in-memory JSON to streaming file parse</name>
  <files>app/Console/Commands/SyncCatalogCommand.php</files>
  <action>
Replace the realm auctions block (lines 128-172, the `if ($this->option('realm'))` block) with a streaming approach that mirrors the commodities fetch pattern already in the file (lines 76-124).

The new realm block should:

1. Keep the same guard: `if ($this->option('realm'))`
2. Keep the same config lookup: `$connectedRealmId = config('services.blizzard.connected_realm_id');`
3. Keep the same info message: `$this->info("Fetching realm auctions for connected-realm {$connectedRealmId}...");`

4. REPLACE the Http::get + ->json() approach with sink-to-file:
   ```php
   $realmTempFile = tempnam(sys_get_temp_dir(), 'wow_realm_auctions_');

   $realmResponse = Http::withToken($token)
       ->retry(2, 5000, throw: false)
       ->timeout(120)
       ->connectTimeout(15)
       ->sink($realmTempFile)
       ->get("https://{$region}.api.blizzard.com/data/wow/connected-realm/{$connectedRealmId}/auctions", [
           'namespace' => "dynamic-{$region}",
       ]);
   ```

5. On failure, warn and continue (same as before), but also unlink the temp file:
   ```php
   if (! $realmResponse->successful()) {
       $this->warn("Realm auctions fetch failed: HTTP {$realmResponse->status()} — continuing with commodities only.");
       Log::warning('SyncCatalog: realm auctions fetch failed', [
           'status' => $realmResponse->status(),
           'connected_realm_id' => $connectedRealmId,
       ]);
       @unlink($realmTempFile);
   }
   ```

6. On success, stream-parse the temp file using the SAME regex pattern as commodities:
   ```php
   else {
       unset($realmResponse);
       $realmFileSize = filesize($realmTempFile);
       $this->info(sprintf('Realm response saved (%s MB), extracting item IDs...', round($realmFileSize / 1048576, 1)));

       $realmItemIds = [];
       $handle = fopen($realmTempFile, 'r');
       $buffer = '';

       while (! feof($handle)) {
           $buffer .= fread($handle, 65536);

           if (preg_match_all('/"item":\{"id":(\d+)\}/', $buffer, $matches)) {
               foreach ($matches[1] as $id) {
                   $realmItemIds[(int) $id] = true;
               }
               $lastBrace = strrpos($buffer, '}');
               $buffer = $lastBrace !== false ? substr($buffer, $lastBrace + 1) : '';
           }
       }

       fclose($handle);
       @unlink($realmTempFile);

       $realmItemIds = collect(array_keys($realmItemIds))->values();

       // Merge with commodity IDs (dedup)
       $beforeCount = $uniqueIds->count();
       $uniqueIds = $uniqueIds->merge($realmItemIds)->unique()->values();
       $realmOnly = $uniqueIds->count() - $beforeCount;

       $this->info(sprintf(
           'Realm auctions: %s unique items (%s new, not in commodities).',
           number_format($realmItemIds->count()),
           number_format($realmOnly),
       ));

       Log::info('SyncCatalog: realm auctions parsed', [
           'connected_realm_id' => $connectedRealmId,
           'realm_items' => $realmItemIds->count(),
           'new_items' => $realmOnly,
       ]);
   }
   ```

7. The `unset($realmAuctions, $realmResponse)` at the old line 171 should be removed entirely — `$realmAuctions` no longer exists, and `$realmResponse` is already unset inside the success branch.

Key points:
- The regex `/"item":\{"id":(\d+)\}/` is the exact same pattern used for commodities — the realm auction JSON uses the same `"item":{"id":NNNNN}` structure.
- The buffer/chunk approach (64KB reads with regex extraction) prevents memory exhaustion.
- The temp file is always cleaned up (success or failure).
  </action>
  <verify>
    <automated>cd /Users/lancethompson/Github/wow-ah-tracker && grep -c "sink" app/Console/Commands/SyncCatalogCommand.php</automated>
  </verify>
  <done>
  - Realm auction fetch uses ->sink() to write to temp file instead of loading into memory
  - Stream parsing uses fread + regex in 64KB chunks (same pattern as commodities)
  - Temp file is cleaned up in both success and failure paths
  - Merge/dedup logic and log messages preserved
  - Running `blizzard:sync-catalog --realm` no longer hangs after the "Fetching realm auctions" message
  </done>
</task>

</tasks>

<verification>
- `grep -A5 "sink.*realm" app/Console/Commands/SyncCatalogCommand.php` shows sink to temp file
- `grep "json(" app/Console/Commands/SyncCatalogCommand.php` does NOT match inside the realm block (only in processBatch/backfillRarity where responses are small)
- `grep -c "fread" app/Console/Commands/SyncCatalogCommand.php` returns 2 (one for commodities, one for realm)
- Code inspection confirms temp file cleanup in both success and failure paths
</verification>

<success_criteria>
The --realm flag on blizzard:sync-catalog streams the realm auction response to disk and parses it incrementally (matching the commodities pattern), instead of attempting to load the entire response into memory. The command completes the full pipeline: fetch realm auctions, extract unique item IDs, merge with commodity IDs, filter existing, look up new items.
</success_criteria>

<output>
After completion, create `.planning/quick/13-fix-realm-sync-stopping-after-fetching-a/13-SUMMARY.md`
</output>
