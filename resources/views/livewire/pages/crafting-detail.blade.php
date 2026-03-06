<?php

declare(strict_types=1);

use App\Actions\RecipeProfitAction;
use App\Models\Profession;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public Profession $profession;

    public function mount(Profession $profession): void
    {
        $this->profession = $profession;
    }

    #[Computed]
    public function recipeData(): array
    {
        $this->profession->load([
            'recipes.reagents.catalogItem.latestPriceSnapshot',
            'recipes.craftedItemSilver.latestPriceSnapshot',
            'recipes.craftedItemGold.latestPriceSnapshot',
        ]);

        $action = new RecipeProfitAction();
        $oldestPolledAt = null;

        $recipes = $this->profession->recipes->map(function ($recipe) use ($action, &$oldestPolledAt) {
            $profit = $action($recipe);

            // Track oldest polled_at for staleness banner
            foreach ($recipe->reagents as $reagent) {
                $polledAt = $reagent->catalogItem?->latestPriceSnapshot?->polled_at;
                if ($polledAt && ($oldestPolledAt === null || $polledAt->lt($oldestPolledAt))) {
                    $oldestPolledAt = $polledAt;
                }
            }

            foreach (['craftedItemSilver', 'craftedItemGold'] as $rel) {
                $polledAt = $recipe->$rel?->latestPriceSnapshot?->polled_at;
                if ($polledAt && ($oldestPolledAt === null || $polledAt->lt($oldestPolledAt))) {
                    $oldestPolledAt = $polledAt;
                }
            }

            // Build reagent breakdown for expansion
            $reagents = $recipe->reagents->map(fn ($r) => [
                'name' => $r->catalogItem?->display_name ?? 'Unknown',
                'quantity' => $r->quantity,
                'unit_price' => $r->catalogItem?->latestPriceSnapshot?->median_price,
                'subtotal' => $r->catalogItem?->latestPriceSnapshot
                    ? $r->quantity * $r->catalogItem->latestPriceSnapshot->median_price
                    : null,
            ])->all();

            return [
                'id' => $recipe->id,
                'name' => $recipe->name,
                'is_commodity' => $recipe->is_commodity,
                'reagent_cost' => $profit['reagent_cost'],
                'profit_silver' => $profit['profit_silver'],
                'profit_gold' => $profit['profit_gold'],
                'median_profit' => $profit['median_profit'],
                'has_missing_prices' => $profit['has_missing_prices'],
                'reagents' => $reagents,
            ];
        })->all();

        $staleMinutes = $oldestPolledAt ? (int) $oldestPolledAt->diffInMinutes(now()) : null;

        return [
            'recipes' => $recipes,
            'stale' => $staleMinutes !== null && $staleMinutes > 60,
            'stale_minutes' => $staleMinutes,
        ];
    }
}; ?>

<div>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <a href="{{ route('crafting') }}" wire:navigate class="text-gray-400 transition-colors hover:text-wow-gold">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                </a>
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-wow-gold">
                        {{ $profession->name }}
                    </h2>
                    <span class="text-xs text-gray-400">{{ $profession->recipes->count() }} recipes</span>
                </div>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            <div x-data="{
                ...@js($this->recipeData),
                sortBy: 'median_profit',
                sortDir: 'desc',
                searchQuery: '',
                expandedRow: null,

                formatGold(copper) {
                    if (copper === null || copper === undefined) return '\u2014';
                    const neg = copper < 0;
                    copper = Math.abs(copper);
                    const g = Math.floor(copper / 10000);
                    const s = Math.floor((copper % 10000) / 100);
                    const c = copper % 100;
                    let parts = [];
                    if (g > 0) parts.push(g.toLocaleString() + 'g');
                    if (s > 0) parts.push(s + 's');
                    if (c > 0 || parts.length === 0) parts.push(c + 'c');
                    return (neg ? '-' : '') + parts.join(' ');
                },

                get sortedRecipes() {
                    let filtered = this.recipes.filter(r =>
                        r.name.toLowerCase().includes(this.searchQuery.toLowerCase())
                    );

                    const normal = filtered.filter(r => r.is_commodity && !r.has_missing_prices);
                    const bottom = filtered.filter(r => !r.is_commodity || r.has_missing_prices);

                    const dir = this.sortDir === 'asc' ? 1 : -1;
                    const sorter = (a, b) => {
                        let av = a[this.sortBy], bv = b[this.sortBy];
                        if (this.sortBy === 'name') return dir * av.localeCompare(bv);
                        if (av === null) return 1;
                        if (bv === null) return -1;
                        return dir * (av - bv);
                    };

                    normal.sort(sorter);
                    bottom.sort(sorter);
                    return [...normal, ...bottom];
                },

                toggleSort(col) {
                    if (this.sortBy === col) {
                        this.sortDir = this.sortDir === 'asc' ? 'desc' : 'asc';
                    } else {
                        this.sortBy = col;
                        this.sortDir = col === 'name' ? 'asc' : 'desc';
                    }
                },

                toggleExpand(id) {
                    this.expandedRow = this.expandedRow === id ? null : id;
                }
            }" x-cloak>

                {{-- Staleness Banner --}}
                <div x-show="stale"
                     class="mb-4 rounded-md border border-amber-700/50 bg-amber-900/20 px-4 py-3">
                    <div class="flex items-start gap-2">
                        <svg class="mt-0.5 h-4 w-4 shrink-0 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z" />
                        </svg>
                        <span class="text-xs text-amber-300">
                            Price data may be stale &mdash; last updated <span x-text="stale_minutes"></span> minutes ago
                        </span>
                    </div>
                </div>

                {{-- Search Filter --}}
                <div class="mb-4">
                    <input
                        type="text"
                        x-model="searchQuery"
                        placeholder="Search recipes..."
                        class="w-full rounded-lg border border-gray-700/50 bg-wow-darker px-4 py-2 text-sm text-gray-200 placeholder-gray-500 focus:border-wow-gold/50 focus:outline-none focus:ring-1 focus:ring-wow-gold/50 sm:w-72"
                    />
                </div>

                {{-- Recipe Count --}}
                <div class="mb-3 text-xs text-gray-400">
                    Showing <span x-text="sortedRecipes.length"></span> of <span x-text="recipes.length"></span> recipes
                </div>

                {{-- Table --}}
                <div class="overflow-x-auto rounded-lg border border-gray-700/50 bg-wow-dark">
                    <table class="min-w-[600px] w-full">
                        <thead>
                            <tr class="border-b border-gray-700/50">
                                <th @click="toggleSort('name')" class="cursor-pointer select-none px-4 pb-2 pt-3 text-left text-xs font-medium uppercase tracking-wider text-gray-400">
                                    <span class="inline-flex items-center gap-1">
                                        Recipe Name
                                        <template x-if="sortBy === 'name'">
                                            <svg class="h-3 w-3" :class="sortDir === 'asc' ? '' : 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
                                            </svg>
                                        </template>
                                    </span>
                                </th>
                                <th @click="toggleSort('reagent_cost')" class="cursor-pointer select-none px-4 pb-2 pt-3 text-right text-xs font-medium uppercase tracking-wider text-gray-400">
                                    <span class="inline-flex items-center justify-end gap-1">
                                        Reagent Cost
                                        <template x-if="sortBy === 'reagent_cost'">
                                            <svg class="h-3 w-3" :class="sortDir === 'asc' ? '' : 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
                                            </svg>
                                        </template>
                                    </span>
                                </th>
                                <th @click="toggleSort('profit_silver')" class="cursor-pointer select-none px-4 pb-2 pt-3 text-right text-xs font-medium uppercase tracking-wider text-gray-400">
                                    <span class="inline-flex items-center justify-end gap-1">
                                        Tier 1
                                        <template x-if="sortBy === 'profit_silver'">
                                            <svg class="h-3 w-3" :class="sortDir === 'asc' ? '' : 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
                                            </svg>
                                        </template>
                                    </span>
                                </th>
                                <th @click="toggleSort('profit_gold')" class="cursor-pointer select-none px-4 pb-2 pt-3 text-right text-xs font-medium uppercase tracking-wider text-gray-400">
                                    <span class="inline-flex items-center justify-end gap-1">
                                        Tier 2
                                        <template x-if="sortBy === 'profit_gold'">
                                            <svg class="h-3 w-3" :class="sortDir === 'asc' ? '' : 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
                                            </svg>
                                        </template>
                                    </span>
                                </th>
                                <th @click="toggleSort('median_profit')" class="cursor-pointer select-none px-4 pb-2 pt-3 text-right text-xs font-medium uppercase tracking-wider text-gray-400">
                                    <span class="inline-flex items-center justify-end gap-1">
                                        Median Profit
                                        <template x-if="sortBy === 'median_profit'">
                                            <svg class="h-3 w-3" :class="sortDir === 'asc' ? '' : 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
                                            </svg>
                                        </template>
                                    </span>
                                </th>
                            </tr>
                        </thead>
                        <template x-for="recipe in sortedRecipes" :key="recipe.id">
                            <tbody>
                                <tr @click="toggleExpand(recipe.id)"
                                    class="cursor-pointer border-b border-gray-700/40 transition-colors hover:bg-wow-darker/50"
                                    :class="{ 'opacity-50': !recipe.is_commodity }">
                                    {{-- Name + warning badge --}}
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-2">
                                            <svg :class="{ 'rotate-90': expandedRow === recipe.id }"
                                                 class="h-4 w-4 shrink-0 text-gray-500 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                            </svg>
                                            <span class="text-sm text-gray-200" x-text="recipe.name"></span>
                                            <template x-if="recipe.has_missing_prices">
                                                <span class="whitespace-nowrap rounded bg-amber-900/40 px-1.5 py-0.5 text-xs text-amber-400">
                                                    missing prices
                                                </span>
                                            </template>
                                        </div>
                                    </td>
                                    {{-- Reagent cost (always shown) --}}
                                    <td class="px-4 py-3 text-right text-sm text-gray-300"
                                        x-text="formatGold(recipe.reagent_cost)"></td>
                                    {{-- Profit columns: always render 3 cells, switch content via Alpine --}}
                                    <td class="px-4 py-3 text-right text-sm">
                                        <span x-show="!recipe.is_commodity" class="text-xs italic text-gray-500">Realm AH &mdash; not tracked</span>
                                        <span x-show="recipe.is_commodity"
                                            :class="recipe.has_missing_prices ? 'text-gray-500' : (recipe.profit_silver > 0 ? 'text-green-400' : (recipe.profit_silver < 0 ? 'text-red-400' : 'text-gray-500'))"
                                            x-text="recipe.has_missing_prices ? '\u2014' : formatGold(recipe.profit_silver)"></span>
                                    </td>
                                    <td class="px-4 py-3 text-right text-sm">
                                        <span x-show="recipe.is_commodity"
                                            :class="recipe.has_missing_prices ? 'text-gray-500' : (recipe.profit_gold > 0 ? 'text-green-400' : (recipe.profit_gold < 0 ? 'text-red-400' : 'text-gray-500'))"
                                            x-text="recipe.has_missing_prices ? '\u2014' : formatGold(recipe.profit_gold)"></span>
                                    </td>
                                    <td class="px-4 py-3 text-right text-sm">
                                        <span x-show="recipe.is_commodity"
                                            :class="recipe.has_missing_prices ? 'text-gray-500' : (recipe.median_profit > 0 ? 'text-green-400' : (recipe.median_profit < 0 ? 'text-red-400' : 'text-gray-500'))"
                                            x-text="recipe.has_missing_prices ? '\u2014' : formatGold(recipe.median_profit)"></span>
                                    </td>
                                </tr>
                                {{-- Expansion row --}}
                                <template x-if="expandedRow === recipe.id">
                                    <tr class="bg-wow-darker/30">
                                        <td colspan="5" class="px-8 py-3">
                                            <div class="text-xs text-gray-400 mb-2 font-medium uppercase tracking-wider">Reagent Breakdown</div>
                                            <table class="w-full">
                                                <template x-for="reagent in recipe.reagents" :key="reagent.name">
                                                    <tr class="text-sm text-gray-300">
                                                        <td class="py-1 pr-4">
                                                            <span class="text-gray-400" x-text="reagent.quantity + 'x'"></span>
                                                            <span x-text="reagent.name" class="ml-1"></span>
                                                        </td>
                                                        <td class="py-1 pr-4 text-right text-gray-400">
                                                            <span x-text="reagent.unit_price !== null ? ('@ ' + formatGold(reagent.unit_price)) : ''" class="whitespace-nowrap"></span>
                                                            <template x-if="reagent.unit_price === null">
                                                                <span class="italic text-gray-500">no price</span>
                                                            </template>
                                                        </td>
                                                        <td class="py-1 text-right">
                                                            <span x-text="reagent.subtotal !== null ? ('= ' + formatGold(reagent.subtotal)) : '\u2014'" class="whitespace-nowrap"
                                                                  :class="reagent.subtotal !== null ? 'text-gray-300' : 'text-gray-500'"></span>
                                                        </td>
                                                    </tr>
                                                </template>
                                            </table>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </template>
                    </table>

                    {{-- Empty State --}}
                    <div x-show="sortedRecipes.length === 0" class="px-4 py-8 text-center text-sm text-gray-500">
                        No recipes found
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
