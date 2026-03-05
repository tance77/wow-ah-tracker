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
            'recipes.reagents.catalogItem.priceSnapshots' => fn ($q) => $q->latest('polled_at')->limit(1),
            'recipes.craftedItemSilver.priceSnapshots' => fn ($q) => $q->latest('polled_at')->limit(1),
            'recipes.craftedItemGold.priceSnapshots' => fn ($q) => $q->latest('polled_at')->limit(1),
        ]);

        $action = new RecipeProfitAction();
        $oldestPolledAt = null;

        $recipes = $this->profession->recipes->map(function ($recipe) use ($action, &$oldestPolledAt) {
            $profit = $action($recipe);

            // Track oldest polled_at for staleness banner
            foreach ($recipe->reagents as $reagent) {
                $polledAt = $reagent->catalogItem?->priceSnapshots->first()?->polled_at;
                if ($polledAt && ($oldestPolledAt === null || $polledAt->lt($oldestPolledAt))) {
                    $oldestPolledAt = $polledAt;
                }
            }

            foreach (['craftedItemSilver', 'craftedItemGold'] as $rel) {
                $polledAt = $recipe->$rel?->priceSnapshots->first()?->polled_at;
                if ($polledAt && ($oldestPolledAt === null || $polledAt->lt($oldestPolledAt))) {
                    $oldestPolledAt = $polledAt;
                }
            }

            // Build reagent breakdown for expansion
            $reagents = $recipe->reagents->map(fn ($r) => [
                'name' => $r->catalogItem?->display_name ?? 'Unknown',
                'quantity' => $r->quantity,
                'unit_price' => $r->catalogItem?->priceSnapshots->first()?->median_price,
                'subtotal' => $r->catalogItem?->priceSnapshots->first()
                    ? $r->quantity * $r->catalogItem->priceSnapshots->first()->median_price
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
        <h2 class="font-semibold text-xl text-gray-200 leading-tight">
            {{ $profession->name }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div x-data="{ ...@js($this->recipeData) }">
                <template x-for="recipe in recipes" :key="recipe.id">
                    <div x-text="recipe.name"></div>
                </template>
            </div>
        </div>
    </div>
</div>
