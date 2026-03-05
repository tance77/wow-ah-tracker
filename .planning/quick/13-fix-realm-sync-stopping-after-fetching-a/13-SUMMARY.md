---
phase: quick-13
plan: 01
subsystem: cli/sync
tags: [fix, streaming, memory, blizzard-api]
dependency_graph:
  requires: []
  provides: [streaming-realm-auction-fetch]
  affects: [blizzard:sync-catalog]
tech_stack:
  added: []
  patterns: [sink-to-file, regex-stream-parse, chunked-fread]
key_files:
  modified:
    - app/Console/Commands/SyncCatalogCommand.php
decisions: []
metrics:
  duration: 45s
  completed: "2026-03-05T18:56:12Z"
  tasks_completed: 1
  tasks_total: 1
---

# Quick Task 13: Fix Realm Sync Stopping After Fetching Auctions

Streaming realm auction fetch via sink-to-file + 64KB chunked regex parsing, matching the existing commodities pattern.

## What Changed

The `--realm` flag on `blizzard:sync-catalog` was hanging/crashing after printing "Fetching realm auctions for connected-realm 76..." because the realm auctions endpoint returns a large JSON response (100K+ individual auction listings). The code used `->json('auctions', [])` which attempted to decode the entire response into memory.

**Fix:** Replaced the in-memory JSON decode with the same streaming approach already used for the commodities endpoint:

1. `->sink($realmTempFile)` writes the HTTP response directly to a temp file
2. `fread($handle, 65536)` reads the file in 64KB chunks
3. `preg_match_all('/"item":\{"id":(\d+)\}/', ...)` extracts item IDs from each chunk
4. Temp file is cleaned up in both success and failure paths via `@unlink()`

The merge/dedup logic, log messages, and info output are preserved unchanged.

## Task Commits

| Task | Commit | Description |
|------|--------|-------------|
| 1 | 18e1cc5 | Stream realm auction response to disk instead of loading into memory |

## Deviations from Plan

None - plan executed exactly as written.

## Verification

- `grep -c "sink"` returns 2 (commodities + realm)
- `grep -c "fread"` returns 2 (commodities + realm)
- `json()` calls only appear in processBatch/backfillRarity (small responses), not in realm block
- Temp file cleanup confirmed in both success (line 172) and failure (line 149) paths
