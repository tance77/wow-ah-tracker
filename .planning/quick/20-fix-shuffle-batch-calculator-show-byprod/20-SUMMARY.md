# Quick Task 20: Fix shuffle batch calculator

## Changes
- `formatGold()`: Added `Math.round()` before modular arithmetic to eliminate floating point display
- Cascade table: Each step now shows byproduct rows with `↳ ItemName (chance% × qty) EV: Xg Ys`
- Byproducts without price data show a yellow "no price data" warning
- Byproduct EV line in profit summary still shows combined total
