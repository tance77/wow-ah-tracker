# Quick Task 27: Fix recipe list profit columns not rendering and default sort to highest profit

## Summary

Fixed Tier 1, Tier 2, and Median Profit columns not showing any values in the recipe list table. The default sort was already set to highest profit (desc) — no change needed.

## Root Cause

`<template x-if>` inside `<tr>` doesn't work in HTML. Browsers hoist `<template>` tags out of table elements per HTML parsing rules, so the `<td>` elements wrapped in `<template x-if>` never appeared in the DOM.

## Fix

Replaced `<template x-if="recipe.is_commodity">` wrapping each `<td>` with `x-show="recipe.is_commodity"` directly on the `<td>` elements. Same for the "Realm AH — not tracked" fallback.

## Commit

`ba7896a` — fix(quick-27): replace template x-if with x-show for profit columns in recipe table
