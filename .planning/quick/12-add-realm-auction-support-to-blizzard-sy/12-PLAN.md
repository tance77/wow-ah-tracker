---
phase: quick-12
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - app/Console/Commands/SyncCatalogCommand.php
  - config/services.php
autonomous: true
requirements: [QUICK-12]
must_haves:
  truths:
    - "Running blizzard:sync-catalog --realm fetches BoE items from the connected-realm auctions endpoint"
    - "Realm auction item IDs are deduplicated against existing catalog entries before item lookup"
    - "Default connected realm ID is Sargeras (configurable via config/env)"
  artifacts:
    - path: "app/Console/Commands/SyncCatalogCommand.php"
      provides: "--realm flag and realm auction fetching logic"
    - path: "config/services.php"
      provides: "connected_realm_id config key"
  key_links:
    - from: "SyncCatalogCommand --realm"
      to: "Blizzard connected-realm auctions API"
      via: "HTTP GET with dynamic namespace"
      pattern: "connected-realm.*auctions"
---

<objective>
Add a --realm flag to blizzard:sync-catalog that also fetches item IDs from the connected-realm auctions endpoint (for BoE gear not on the commodities endpoint). Default realm is Sargeras.

Purpose: BoE items (gear, weapons, armor) only appear on realm-specific auction endpoints, not the commodities endpoint. This adds them to the catalog so they can be tracked.
Output: Updated SyncCatalogCommand with --realm support, Sargeras as default connected realm.
</objective>

<execution_context>
@/Users/lancethompson/.claude/get-shit-done/workflows/execute-plan.md
@/Users/lancethompson/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@app/Console/Commands/SyncCatalogCommand.php
@app/Services/BlizzardTokenService.php
@config/services.php
@app/Models/CatalogItem.php
</context>

<tasks>

<task type="auto">
  <name>Task 1: Add connected realm config and --realm flag with auction fetching</name>
  <files>config/services.php, app/Console/Commands/SyncCatalogCommand.php</files>
  <action>
1. In config/services.php, add `connected_realm_id` to the blizzard config array:
   ```
   'connected_realm_id' => env('BLIZZARD_CONNECTED_REALM_ID', 76),
   ```
   Note: Sargeras connected realm ID is 76 in the US region. This is the connected-realm ID (not the realm ID). Verify by checking the Blizzard API docs — the endpoint is /data/wow/connected-realm/{connectedRealmId}/auctions.

2. In SyncCatalogCommand, add a new option to the signature:
   ```
   {--realm : Also fetch item IDs from the connected-realm auctions endpoint (for BoE gear)}
   ```

3. In the handle() method, AFTER the commodities fetch completes and uniqueIds is populated (around line 124, after the cache block closes), add a conditional block for --realm:

   ```php
   // Step 1b: Optionally fetch realm auctions for BoE items
   if ($this->option('realm')) {
       $connectedRealmId = config('services.blizzard.connected_realm_id');
       $this->info("Fetching realm auctions for connected-realm {$connectedRealmId}...");

       $realmResponse = Http::withToken($token)
           ->retry(2, 5000, throw: false)
           ->timeout(120)
           ->connectTimeout(15)
           ->get("https://{$region}.api.blizzard.com/data/wow/connected-realm/{$connectedRealmId}/auctions", [
               'namespace' => "dynamic-{$region}",
           ]);

       if ($realmResponse->successful()) {
           $realmAuctions = $realmResponse->json('auctions', []);
           $realmItemIds = collect($realmAuctions)
               ->pluck('item.id')
               ->unique()
               ->values();

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
       } else {
           $this->warn("Realm auctions fetch failed: HTTP {$realmResponse->status()} — continuing with commodities only.");
           Log::warning('SyncCatalog: realm auctions fetch failed', [
               'status' => $realmResponse->status(),
               'connected_realm_id' => $connectedRealmId,
           ]);
       }

       unset($realmAuctions, $realmResponse);
   }
   ```

   Place this BEFORE the "Step 2: Filter out existing items" section so the merged IDs flow into the existing dedup + processBatch pipeline.

4. Update the command $description to mention realm auctions:
   ```
   'Import commodity and realm auction items from the Blizzard Auction House API into the catalog'
   ```

5. Important: The realm auction response JSON structure has `auctions` array where each entry has `item.id` (same pattern as commodities). The response is smaller than commodities (~50-100MB vs ~200MB) so no need for streaming — the standard Http response with ->json() is fine.

6. Important: Do NOT use streaming/sink for realm auctions. The commodities endpoint returns 200MB+ (hence the streaming approach), but realm auctions are much smaller and can be loaded into memory directly with ->json().
  </action>
  <verify>
    <automated>cd /Users/lancethompson/Github/wow-ah-tracker && php artisan blizzard:sync-catalog --help | grep -E "realm|connected"</automated>
  </verify>
  <done>
  - `php artisan blizzard:sync-catalog --realm` flag appears in help output
  - config/services.php has connected_realm_id key defaulting to Sargeras
  - Realm auction IDs are merged with commodity IDs before dedup/lookup
  - Non-blocking: if realm fetch fails, command continues with commodities only
  </done>
</task>

</tasks>

<verification>
- `php artisan blizzard:sync-catalog --help` shows --realm flag with description
- `grep connected_realm_id config/services.php` returns the config line
- `grep "connected-realm" app/Console/Commands/SyncCatalogCommand.php` returns the API URL
- Code inspection: realm IDs are merged into uniqueIds BEFORE the existing dedup step
</verification>

<success_criteria>
The --realm flag exists and when used, fetches item IDs from the Blizzard connected-realm auctions endpoint, merges them with commodity IDs, and processes all through the existing catalog import pipeline. Default realm is Sargeras.
</success_criteria>

<output>
After completion, create `.planning/quick/12-add-realm-auction-support-to-blizzard-sy/12-SUMMARY.md`
</output>
