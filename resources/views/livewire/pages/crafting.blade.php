<?php

declare(strict_types=1);

use App\Actions\RecipeProfitAction;
use App\Concerns\FormatsAuctionData;
use App\Models\Profession;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    use FormatsAuctionData;

    #[Computed]
    public function professions(): Collection
    {
        // Eager load all relationships needed by RecipeProfitAction
        $professions = Profession::with([
            'recipes.reagents.catalogItem.priceSnapshots' => fn ($q) => $q->latest('polled_at')->limit(1),
            'recipes.craftedItemSilver.priceSnapshots' => fn ($q) => $q->latest('polled_at')->limit(1),
            'recipes.craftedItemGold.priceSnapshots' => fn ($q) => $q->latest('polled_at')->limit(1),
        ])->get();

        $action = new RecipeProfitAction();

        return $professions->map(function (Profession $profession) use ($action) {
            // Compute profit for ALL recipes once (avoid double-compute pitfall)
            $allProfits = $profession->recipes->map(fn ($recipe) => [
                'recipe' => $recipe,
                'profit' => $action($recipe),
            ]);

            // Top 5: exclude missing prices, sort by median_profit desc
            $profession->_top_recipes = $allProfits
                ->filter(fn ($r) => $r['profit']['median_profit'] !== null)
                ->sortByDesc(fn ($r) => $r['profit']['median_profit'])
                ->take(5)
                ->values();

            $profession->_total_recipes = $profession->recipes->count();
            $profession->_profitable_count = $allProfits
                ->filter(fn ($r) => $r['profit']['median_profit'] !== null && $r['profit']['median_profit'] > 0)
                ->count();
            $profession->_best_profit = $profession->_top_recipes->first()['profit']['median_profit'] ?? null;

            return $profession;
        })->sortByDesc('_best_profit')->values();
    }

    #[Computed]
    public function totalRecipes(): int
    {
        return $this->professions->sum('_total_recipes');
    }

    #[Computed]
    public function profitableRecipes(): int
    {
        return $this->professions->sum('_profitable_count');
    }
}; ?>

<div>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold leading-tight text-wow-gold">
                {{ __('Crafting') }}
            </h2>
            <div class="text-sm text-gray-400">
                {{ $this->professions->count() }} professions &bull; {{ $this->totalRecipes }} recipes &bull; {{ $this->profitableRecipes }} profitable
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($this->professions as $profession)
                    <a
                        href="{{ route('crafting.show', $profession) }}"
                        wire:navigate
                        wire:key="profession-{{ $profession->id }}"
                        class="block rounded-lg border border-gray-700/50 bg-wow-dark p-5 transition-colors hover:border-wow-gold/50"
                    >
                        {{-- Card Header --}}
                        <div class="mb-3 flex items-center gap-3">
                            @if ($profession->icon_url)
                                <img src="{{ $profession->icon_url }}" alt="{{ $profession->name }}" class="h-10 w-10 rounded" loading="lazy" />
                            @else
                                <div class="flex h-10 w-10 items-center justify-center rounded bg-gray-700/50 text-gray-500">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                                    </svg>
                                </div>
                            @endif
                            <div>
                                <h3 class="text-lg font-semibold text-wow-gold">{{ $profession->name }}</h3>
                                <span class="text-xs text-gray-400">{{ $profession->_profitable_count }} of {{ $profession->_total_recipes }} profitable</span>
                            </div>
                        </div>

                        {{-- Recipe List --}}
                        <div class="mt-2">
                            @if ($profession->_top_recipes->isEmpty())
                                <p class="text-sm italic text-gray-500">No profitable recipes</p>
                            @else
                                <ol class="space-y-1">
                                    @foreach ($profession->_top_recipes as $index => $entry)
                                        <li class="flex items-center justify-between gap-2">
                                            <span class="flex items-center gap-1.5 truncate text-sm text-gray-300">
                                                <span class="text-xs text-gray-500">{{ $index + 1 }}.</span>
                                                {{ $entry['recipe']->name }}
                                            </span>
                                            @if ($entry['profit']['median_profit'] >= 0)
                                                <span class="whitespace-nowrap text-sm text-green-400">+{{ $this->formatGold($entry['profit']['median_profit']) }}</span>
                                            @else
                                                <span class="whitespace-nowrap text-sm text-red-400">{{ $this->formatGold($entry['profit']['median_profit']) }}</span>
                                            @endif
                                        </li>
                                    @endforeach
                                </ol>
                            @endif
                        </div>

                        {{-- Card Footer --}}
                        <div class="mt-3 border-t border-gray-700/30 pt-2">
                            <span class="text-xs text-gray-500">{{ $profession->_profitable_count }} of {{ $profession->_total_recipes }} profitable</span>
                        </div>
                    </a>
                @endforeach
            </div>
        </div>
    </div>
</div>
