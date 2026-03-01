<?php

declare(strict_types=1);

use App\Models\CatalogItem;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public string $search = '';

    public string $manualItemId = '';

    #[Computed]
    public function watchedItems(): Collection
    {
        return auth()->user()->watchedItems()->orderBy('name')->get();
    }

    #[Computed]
    public function catalogSuggestions(): array
    {
        if (strlen($this->search) < 2) {
            return [];
        }

        return CatalogItem::where('name', 'like', "%{$this->search}%")
            ->limit(15)
            ->orderBy('name')
            ->get(['id', 'name', 'blizzard_item_id'])
            ->toArray();
    }

    public function addFromCatalog(int $catalogId): void
    {
        $catalog = CatalogItem::findOrFail($catalogId);

        auth()->user()->watchedItems()->firstOrCreate(
            ['blizzard_item_id' => $catalog->blizzard_item_id],
            ['name' => $catalog->name, 'buy_threshold' => 10, 'sell_threshold' => 10]
        );

        $this->search = '';
    }

    public function addManual(): void
    {
        $this->validate(['manualItemId' => 'required|integer|min:1']);

        $id = (int) $this->manualItemId;

        auth()->user()->watchedItems()->firstOrCreate(
            ['blizzard_item_id' => $id],
            ['name' => "Item #{$id}", 'buy_threshold' => 10, 'sell_threshold' => 10]
        );

        $this->manualItemId = '';
    }

    public function removeItem(int $id): void
    {
        auth()->user()->watchedItems()->findOrFail($id)->delete();
    }

    public function updateThreshold(int $id, string $field, int $value): void
    {
        if (! in_array($field, ['buy_threshold', 'sell_threshold'], true)) {
            return;
        }

        $item = auth()->user()->watchedItems()->findOrFail($id);
        $item->update([$field => max(1, min(100, $value))]);
    }
}; ?>

<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-wow-gold">
            {{ __('Watchlist') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">

            <!-- Add Items Section -->
            <div class="mb-6 overflow-hidden bg-wow-dark shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="mb-4 text-sm font-medium text-gray-300 uppercase tracking-wide">Add Items to Watchlist</h3>
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start">

                        <!-- Combobox Search -->
                        <div
                            class="relative flex-1"
                            x-data="{ open: false }"
                            @click.outside="open = false"
                        >
                            <input
                                type="text"
                                wire:model.live.debounce.200ms="search"
                                @focus="open = true"
                                @input="open = true"
                                placeholder="Search items by name..."
                                x-ref="searchInput"
                                class="w-full rounded-md border border-gray-600 bg-wow-darker px-3 py-2 text-gray-100 placeholder-gray-500 focus:border-wow-gold focus:ring-wow-gold sm:text-sm"
                            />
                            <ul
                                x-show="open && $wire.catalogSuggestions.length > 0"
                                class="absolute z-50 mt-1 max-h-60 w-full overflow-y-auto rounded-md border border-gray-600 bg-wow-darker shadow-lg"
                                x-cloak
                            >
                                @foreach ($this->catalogSuggestions as $item)
                                    <li
                                        wire:click="addFromCatalog({{ $item['id'] }})"
                                        @click="open = false"
                                        class="cursor-pointer px-3 py-2 text-sm text-gray-200 hover:bg-wow-dark"
                                    >
                                        {{ $item['name'] }}
                                        <span class="text-xs text-gray-500">ID: {{ $item['blizzard_item_id'] }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>

                        <!-- Manual ID Entry -->
                        <div class="flex items-start gap-2">
                            <input
                                type="number"
                                wire:model="manualItemId"
                                min="1"
                                placeholder="Blizzard Item ID"
                                class="w-44 rounded-md border border-gray-600 bg-wow-darker px-3 py-2 text-gray-100 placeholder-gray-500 focus:border-wow-gold focus:ring-wow-gold sm:text-sm"
                            />
                            <button
                                wire:click="addManual"
                                class="rounded-md border border-gray-600 bg-wow-darker px-4 py-2 text-sm font-medium text-gray-300 transition-colors hover:border-wow-gold hover:text-wow-gold focus:outline-none focus:ring-2 focus:ring-wow-gold focus:ring-offset-2 focus:ring-offset-wow-dark"
                            >
                                Add by ID
                            </button>
                        </div>
                    </div>

                    @error('manualItemId')
                        <p class="mt-2 text-sm text-red-400">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <!-- Watchlist Table / Empty State -->
            <div class="overflow-hidden bg-wow-dark shadow-sm sm:rounded-lg">
                @if ($this->watchedItems->isEmpty())
                    <!-- Empty State -->
                    <div class="flex flex-col items-center justify-center p-16 text-center">
                        <p class="mb-4 text-gray-400">No items on your watchlist yet</p>
                        <button
                            @click="$refs.searchInput.focus()"
                            x-on:click="document.querySelector('[x-ref=\'searchInput\']').focus()"
                            class="rounded-md bg-wow-gold px-4 py-2 text-sm font-semibold text-wow-darker transition-colors hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-wow-gold focus:ring-offset-2 focus:ring-offset-wow-dark"
                        >
                            Add your first item
                        </button>
                    </div>
                @else
                    <!-- Watchlist Table -->
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-gray-700/50 text-left text-xs font-medium uppercase tracking-wide text-gray-400">
                                    <th class="px-6 py-3">Item Name</th>
                                    <th class="px-6 py-3 text-center">Buy Threshold (%)</th>
                                    <th class="px-6 py-3 text-center">Sell Threshold (%)</th>
                                    <th class="px-6 py-3 text-right">Remove</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-700/50">
                                @foreach ($this->watchedItems as $item)
                                    <tr wire:key="item-{{ $item->id }}" class="text-gray-200 transition-colors hover:bg-wow-darker/50">
                                        <!-- Item Name -->
                                        <td class="px-6 py-4">
                                            <span class="font-medium">{{ $item->name }}</span>
                                            <span class="block text-xs text-gray-500">ID: {{ $item->blizzard_item_id }}</span>
                                        </td>

                                        <!-- Buy Threshold -->
                                        <td class="px-6 py-4 text-center">
                                            <div
                                                class="inline-flex items-center gap-1"
                                                x-data="{ editing: false, saved: false }"
                                                x-init="$watch('editing', v => v && $nextTick(() => $refs.buyInput.focus()))"
                                            >
                                                <span
                                                    x-show="!editing"
                                                    @click="editing = true"
                                                    class="cursor-pointer rounded px-2 py-1 hover:text-wow-gold"
                                                >{{ $item->buy_threshold }}%</span>
                                                <input
                                                    type="number"
                                                    min="1"
                                                    max="100"
                                                    x-show="editing"
                                                    x-ref="buyInput"
                                                    value="{{ $item->buy_threshold }}"
                                                    wire:change="updateThreshold({{ $item->id }}, 'buy_threshold', $event.target.value)"
                                                    @blur="editing = false; saved = true; setTimeout(() => saved = false, 1000)"
                                                    @keydown.enter="$el.blur()"
                                                    @keydown.escape="editing = false"
                                                    class="w-16 rounded border border-gray-600 bg-wow-darker px-2 py-1 text-center text-gray-100 focus:border-wow-gold focus:outline-none focus:ring-1 focus:ring-wow-gold"
                                                />
                                                <span
                                                    x-show="saved"
                                                    x-transition
                                                    class="ml-1 text-xs text-green-400"
                                                >Saved</span>
                                            </div>
                                        </td>

                                        <!-- Sell Threshold -->
                                        <td class="px-6 py-4 text-center">
                                            <div
                                                class="inline-flex items-center gap-1"
                                                x-data="{ editing: false, saved: false }"
                                                x-init="$watch('editing', v => v && $nextTick(() => $refs.sellInput.focus()))"
                                            >
                                                <span
                                                    x-show="!editing"
                                                    @click="editing = true"
                                                    class="cursor-pointer rounded px-2 py-1 hover:text-wow-gold"
                                                >{{ $item->sell_threshold }}%</span>
                                                <input
                                                    type="number"
                                                    min="1"
                                                    max="100"
                                                    x-show="editing"
                                                    x-ref="sellInput"
                                                    value="{{ $item->sell_threshold }}"
                                                    wire:change="updateThreshold({{ $item->id }}, 'sell_threshold', $event.target.value)"
                                                    @blur="editing = false; saved = true; setTimeout(() => saved = false, 1000)"
                                                    @keydown.enter="$el.blur()"
                                                    @keydown.escape="editing = false"
                                                    class="w-16 rounded border border-gray-600 bg-wow-darker px-2 py-1 text-center text-gray-100 focus:border-wow-gold focus:outline-none focus:ring-1 focus:ring-wow-gold"
                                                />
                                                <span
                                                    x-show="saved"
                                                    x-transition
                                                    class="ml-1 text-xs text-green-400"
                                                >Saved</span>
                                            </div>
                                        </td>

                                        <!-- Remove -->
                                        <td class="px-6 py-4 text-right">
                                            <button
                                                wire:click="removeItem({{ $item->id }})"
                                                class="text-sm text-red-400 transition-colors hover:text-red-300 focus:outline-none"
                                            >
                                                Remove
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
