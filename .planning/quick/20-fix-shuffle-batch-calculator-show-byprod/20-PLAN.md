# Quick Task 20: Fix shuffle batch calculator

## Fixes
1. Round copper in formatGold() to eliminate floating point artifacts (26g 9s 69.28c → 26g 9s 69c)
2. Show byproduct EV per step in cascade table with chance/qty breakdown
3. Show "no price data" warning when byproduct item has no price
