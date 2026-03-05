<?php

declare(strict_types=1);

use App\Concerns\FormatsAuctionData;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
new #[Layout('layouts.app')] class extends Component
{
    use FormatsAuctionData;

    public string $importJson = '';

    #[Computed]
    public function shuffles(): Collection
    {
        return auth()->user()->shuffles()
            ->with([
                'steps.inputCatalogItem',
                'steps.outputCatalogItem',
            ])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function createShuffle(): void
    {
        $shuffle = auth()->user()->shuffles()->create([
            'name' => 'New Shuffle',
        ]);

        $this->redirect(route('shuffles.show', $shuffle), navigate: true);
    }

    public function renameShuffle(int $id, string $name): void
    {
        $name = trim($name);

        if (strlen($name) < 1) {
            return;
        }

        $shuffle = auth()->user()->shuffles()->findOrFail($id);
        $shuffle->update(['name' => $name]);

        unset($this->shuffles);
    }

    public function deleteShuffle(int $id): void
    {
        $shuffle = auth()->user()->shuffles()->findOrFail($id);
        $shuffle->delete();

        unset($this->shuffles);
    }

    public function cloneShuffle(int $id): void
    {
        $original = auth()->user()->shuffles()->findOrFail($id);

        $clone = auth()->user()->shuffles()->create([
            'name' => $original->name . ' (Copy)',
        ]);

        $steps = $original->steps()->with('byproducts')->get();
        $watchItemIds = [];

        foreach ($steps as $step) {
            $newStep = $clone->steps()->create([
                'input_blizzard_item_id' => $step->input_blizzard_item_id,
                'output_blizzard_item_id' => $step->output_blizzard_item_id,
                'input_qty' => $step->input_qty,
                'output_qty_min' => $step->output_qty_min,
                'output_qty_max' => $step->output_qty_max,
                'sort_order' => $step->sort_order,
            ]);

            foreach ($step->byproducts as $bp) {
                $newStep->byproducts()->create([
                    'blizzard_item_id' => $bp->blizzard_item_id,
                    'item_name' => $bp->item_name,
                    'chance_percent' => $bp->chance_percent,
                    'quantity' => $bp->quantity,
                ]);

                $watchItemIds[] = $bp->blizzard_item_id;
            }

            $watchItemIds[] = $step->input_blizzard_item_id;
            $watchItemIds[] = $step->output_blizzard_item_id;
        }

        // Auto-watch all unique item IDs
        foreach (array_unique($watchItemIds) as $blizzardItemId) {
            $catalogItem = \App\Models\CatalogItem::where('blizzard_item_id', $blizzardItemId)->first();
            $name = $catalogItem?->name ?? "Item #{$blizzardItemId}";

            auth()->user()->watchedItems()->firstOrCreate(
                ['blizzard_item_id' => $blizzardItemId],
                [
                    'name' => $name,
                    'buy_threshold' => null,
                    'sell_threshold' => null,
                    'created_by_shuffle_id' => $clone->id,
                ]
            );
        }

        $this->redirect(route('shuffles.show', $clone), navigate: true);
    }

    public function exportShuffle(int $id): void
    {
        $shuffle = auth()->user()->shuffles()->findOrFail($id);
        $shuffle->load(['steps.inputCatalogItem', 'steps.outputCatalogItem', 'steps.byproducts']);

        $data = [
            'name' => $shuffle->name,
            'version' => 1,
            'steps' => $shuffle->steps->map(fn ($step) => [
                'input_blizzard_item_id' => $step->input_blizzard_item_id,
                'input_item_name' => $step->inputCatalogItem?->name ?? "Item #{$step->input_blizzard_item_id}",
                'output_blizzard_item_id' => $step->output_blizzard_item_id,
                'output_item_name' => $step->outputCatalogItem?->name ?? "Item #{$step->output_blizzard_item_id}",
                'input_qty' => $step->input_qty,
                'output_qty_min' => $step->output_qty_min,
                'output_qty_max' => $step->output_qty_max,
                'sort_order' => $step->sort_order,
                'byproducts' => $step->byproducts->map(fn ($bp) => [
                    'blizzard_item_id' => $bp->blizzard_item_id,
                    'item_name' => $bp->item_name,
                    'chance_percent' => $bp->chance_percent,
                    'quantity' => $bp->quantity,
                ])->values()->all(),
            ])->values()->all(),
        ];

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $this->dispatch('shuffle-exported', json: $json, shuffleId: $id);
    }

    public function importShuffle(): void
    {
        if (! $this->importFile) {
            $this->addError('importFile', 'Please select a JSON file to import.');
            return;
        }

        $contents = file_get_contents($this->importFile->getRealPath());
        $data = json_decode($contents, true);

        if (! is_array($data) || ! isset($data['name']) || ! is_string($data['name']) || ! isset($data['steps']) || ! is_array($data['steps'])) {
            $this->addError('importFile', 'Invalid shuffle JSON format. File must contain "name" and "steps".');
            return;
        }

        $shuffle = auth()->user()->shuffles()->create([
            'name' => $data['name'] . ' (Imported)',
        ]);

        $watchItemIds = [];

        foreach ($data['steps'] as $stepData) {
            $step = $shuffle->steps()->create([
                'input_blizzard_item_id' => $stepData['input_blizzard_item_id'],
                'output_blizzard_item_id' => $stepData['output_blizzard_item_id'],
                'input_qty' => $stepData['input_qty'] ?? 1,
                'output_qty_min' => $stepData['output_qty_min'] ?? 1,
                'output_qty_max' => $stepData['output_qty_max'] ?? 1,
                'sort_order' => $stepData['sort_order'] ?? 0,
            ]);

            $watchItemIds[] = $stepData['input_blizzard_item_id'];
            $watchItemIds[] = $stepData['output_blizzard_item_id'];

            foreach ($stepData['byproducts'] ?? [] as $bpData) {
                $step->byproducts()->create([
                    'blizzard_item_id' => $bpData['blizzard_item_id'],
                    'item_name' => $bpData['item_name'] ?? "Item #{$bpData['blizzard_item_id']}",
                    'chance_percent' => $bpData['chance_percent'] ?? 100,
                    'quantity' => $bpData['quantity'] ?? 1,
                ]);

                $watchItemIds[] = $bpData['blizzard_item_id'];
            }
        }

        // Auto-watch all unique item IDs
        foreach (array_unique($watchItemIds) as $blizzardItemId) {
            $catalogItem = \App\Models\CatalogItem::where('blizzard_item_id', $blizzardItemId)->first();
            $name = $catalogItem?->name ?? "Item #{$blizzardItemId}";

            auth()->user()->watchedItems()->firstOrCreate(
                ['blizzard_item_id' => $blizzardItemId],
                [
                    'name' => $name,
                    'buy_threshold' => null,
                    'sell_threshold' => null,
                    'created_by_shuffle_id' => $shuffle->id,
                ]
            );
        }

        $this->importFile = null;

        $this->redirect(route('shuffles.show', $shuffle), navigate: true);
    }
}; ?>

<x-slot name="header">
    <h2 class="text-xl font-semibold leading-tight text-wow-gold">
        {{ __('Shuffles') }}
    </h2>
</x-slot>

<div
    class="py-12"
    x-data="{ copiedId: null }"
    x-on:shuffle-exported.window="
        navigator.clipboard.writeText($event.detail[0].json);
        copiedId = $event.detail[0].shuffleId;
        setTimeout(() => copiedId = null, 2000)
    "
>
    <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">

        <div class="overflow-hidden bg-wow-dark shadow-sm sm:rounded-lg">

            @if ($this->shuffles->isEmpty())
                <!-- Empty State -->
                <div class="flex flex-col items-center justify-center p-16 text-center">
                    <p class="mb-2 text-lg font-medium text-gray-300">No shuffles yet</p>
                    <p class="mb-6 max-w-md text-sm text-gray-500">
                        Shuffles are item conversion chains — track the profitability of crafting or transmuting items through multiple steps. Create one to get started.
                    </p>
                    <div class="flex items-center gap-3">
                        <button
                            wire:click="createShuffle"
                            class="rounded-md bg-wow-gold px-4 py-2 text-sm font-semibold text-wow-darker transition-colors hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-wow-gold focus:ring-offset-2 focus:ring-offset-wow-dark"
                        >
                            Create Shuffle
                        </button>
                        <label
                            class="cursor-pointer rounded-md border border-gray-600 px-4 py-2 text-sm font-medium text-gray-300 transition-colors hover:border-wow-gold hover:text-wow-gold focus:outline-none"
                            x-data
                        >
                            Import Shuffle
                            <input type="file" accept=".json" wire:model="importFile" class="hidden" x-ref="importInput" @change="$nextTick(() => $wire.importShuffle())" />
                        </label>
                    </div>
                </div>
            @else
                <!-- New Shuffle + Import Buttons -->
                <div class="flex items-center justify-end gap-3 px-6 pt-4">
                    <label
                        class="cursor-pointer rounded-md border border-gray-600 px-4 py-2 text-sm font-medium text-gray-300 transition-colors hover:border-wow-gold hover:text-wow-gold focus:outline-none"
                        x-data
                    >
                        Import Shuffle
                        <input type="file" accept=".json" wire:model="importFile" class="hidden" @change="$nextTick(() => $wire.importShuffle())" />
                    </label>
                    <button wire:click="createShuffle" class="rounded-md bg-wow-gold px-4 py-2 text-sm font-semibold text-wow-darker transition-colors hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-wow-gold focus:ring-offset-2 focus:ring-offset-wow-dark">
                        New Shuffle
                    </button>
                </div>
                <!-- Shuffles Table -->
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-700/50 text-left text-xs font-medium uppercase tracking-wide text-gray-400">
                                <th class="px-6 py-3">Shuffle Name</th>
                                <th class="px-6 py-3">Chain Preview</th>
                                <th class="px-6 py-3 text-center">Steps</th>
                                <th class="px-6 py-3 text-center">Profitability</th>
                                <th class="px-6 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-700/50">
                            @foreach ($this->shuffles as $shuffle)
                                @php
                                    $profit = $shuffle->profitPerUnit();
                                    $stepCount = $shuffle->steps->count();
                                    $chainPreview = $stepCount > 0
                                        ? $shuffle->steps->map(fn ($s) => $s->inputCatalogItem?->name ?? 'Unknown')->join(' → ')
                                            . ' → '
                                            . ($shuffle->steps->last()->outputCatalogItem?->name ?? 'Unknown')
                                        : null;
                                @endphp
                                <tr
                                    wire:key="shuffle-{{ $shuffle->id }}"
                                    class="group cursor-pointer text-gray-200 transition-colors hover:bg-wow-darker/50"
                                    onclick="window.location='{{ route('shuffles.show', $shuffle) }}'"
                                >
                                    <!-- Shuffle Name (inline editable) -->
                                    <td class="px-6 py-4" onclick="event.stopPropagation()">
                                        <div
                                            class="inline-flex items-center gap-2"
                                            x-data="{ editing: false, name: @js($shuffle->name) }"
                                            x-init="$watch('editing', v => v && $nextTick(() => $refs.nameInput.select()))"
                                        >
                                            <span
                                                x-show="!editing"
                                                @click="editing = true"
                                                class="cursor-pointer rounded px-1 py-0.5 font-medium text-gray-100 hover:text-wow-gold"
                                                title="Click to rename"
                                            >{{ $shuffle->name }}</span>
                                            <input
                                                type="text"
                                                x-show="editing"
                                                x-ref="nameInput"
                                                x-model="name"
                                                @keydown.enter="$wire.renameShuffle({{ $shuffle->id }}, name); editing = false"
                                                @keydown.escape="name = @js($shuffle->name); editing = false"
                                                @blur="$wire.renameShuffle({{ $shuffle->id }}, name); editing = false"
                                                class="w-48 rounded border border-gray-600 bg-wow-darker px-2 py-0.5 text-gray-100 focus:border-wow-gold focus:outline-none focus:ring-1 focus:ring-wow-gold"
                                            />
                                        </div>
                                    </td>

                                    <!-- Chain Preview -->
                                    <td class="px-6 py-4 text-gray-400">
                                        @if ($chainPreview)
                                            <span class="text-xs">{{ $chainPreview }}</span>
                                        @else
                                            <span class="text-xs italic text-gray-600">No steps yet</span>
                                        @endif
                                    </td>

                                    <!-- Step Count -->
                                    <td class="px-6 py-4 text-center">
                                        @if ($stepCount > 0)
                                            <span class="rounded-full bg-gray-700 px-2 py-0.5 text-xs font-medium text-gray-300">
                                                {{ $stepCount }} {{ Str::plural('step', $stepCount) }}
                                            </span>
                                        @else
                                            <span class="text-xs text-gray-600">—</span>
                                        @endif
                                    </td>

                                    <!-- Profitability Badge -->
                                    <td class="px-6 py-4 text-center">
                                        @if ($profit === null)
                                            <span class="inline-flex items-center gap-1.5 text-xs text-gray-500">
                                                <span class="h-2 w-2 rounded-full bg-gray-600"></span>
                                                <span>—</span>
                                            </span>
                                        @elseif ($profit >= 0)
                                            <span class="inline-flex items-center gap-1.5 text-xs font-medium text-green-400">
                                                <span class="h-2 w-2 rounded-full bg-green-400"></span>
                                                <span>+{{ $this->formatGold($profit) }}</span>
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1.5 text-xs font-medium text-red-400">
                                                <span class="h-2 w-2 rounded-full bg-red-400"></span>
                                                <span>{{ $this->formatGold($profit) }}</span>
                                            </span>
                                        @endif
                                    </td>

                                    <!-- Actions -->
                                    <td class="px-6 py-4 text-right" onclick="event.stopPropagation()">
                                        <button
                                            wire:click="exportShuffle({{ $shuffle->id }})"
                                            class="text-sm text-gray-400 transition-colors hover:text-wow-gold focus:outline-none"
                                        >
                                            <span x-show="copiedId !== {{ $shuffle->id }}">Share</span>
                                            <span x-show="copiedId === {{ $shuffle->id }}" x-cloak class="text-green-400">Copied!</span>
                                        </button>
                                        <button
                                            wire:click="cloneShuffle({{ $shuffle->id }})"
                                            class="ml-3 text-sm text-gray-400 transition-colors hover:text-wow-gold focus:outline-none"
                                        >
                                            Clone
                                        </button>
                                        <button
                                            x-data
                                            @click="$dispatch('open-modal', 'confirm-delete-{{ $shuffle->id }}')"
                                            class="ml-3 text-sm text-red-400 transition-colors hover:text-red-300 focus:outline-none"
                                        >
                                            Delete
                                        </button>
                                    </td>
                                </tr>

                                <!-- Delete Confirmation Modal -->
                                <x-modal name="confirm-delete-{{ $shuffle->id }}" focusable>
                                    <div class="p-6">
                                        <h2 class="text-lg font-medium text-gray-100">
                                            Delete "{{ $shuffle->name }}"?
                                        </h2>
                                        <p class="mt-2 text-sm text-gray-400">
                                            This will permanently delete the shuffle and all its steps. Any auto-watched items that were added for this shuffle and are not used by other shuffles will also be removed from your watchlist.
                                        </p>
                                        <div class="mt-6 flex justify-end gap-3">
                                            <button
                                                x-on:click="$dispatch('close')"
                                                class="rounded-md border border-gray-600 px-4 py-2 text-sm font-medium text-gray-300 transition-colors hover:border-gray-500 hover:text-gray-200 focus:outline-none"
                                            >
                                                Cancel
                                            </button>
                                            <button
                                                wire:click="deleteShuffle({{ $shuffle->id }})"
                                                x-on:click="$dispatch('close')"
                                                class="rounded-md bg-red-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-red-500 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 focus:ring-offset-wow-dark"
                                            >
                                                Delete Shuffle
                                            </button>
                                        </div>
                                    </div>
                                </x-modal>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>
