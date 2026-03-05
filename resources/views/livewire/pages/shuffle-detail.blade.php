<?php

declare(strict_types=1);

use App\Concerns\FormatsAuctionData;
use App\Models\CatalogItem;
use App\Models\PriceSnapshot;
use App\Models\Shuffle;
use App\Models\ShuffleStep;
use App\Models\WatchedItem;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    use FormatsAuctionData;

    public Shuffle $shuffle;

    // Add-step form state
    public string $inputSearch = '';
    public string $outputSearch = '';
    public ?int $selectedInputItemId = null;
    public ?int $selectedOutputItemId = null;
    public ?string $selectedInputName = null;
    public ?string $selectedOutputName = null;
    public int $newInputQty = 1;
    public int $newOutputQtyMin = 1;
    public int $newOutputQtyMax = 1;
    public bool $addingStep = false;

    public function mount(Shuffle $shuffle): void
    {
        $this->shuffle = $shuffle;
    }

    #[Computed]
    public function steps(): Collection
    {
        return $this->shuffle->steps()
            ->with(['inputCatalogItem', 'outputCatalogItem'])
            ->get();
    }

    #[Computed]
    public function priceData(): array
    {
        // Collect all unique blizzard_item_ids from steps
        $itemIds = $this->steps
            ->flatMap(fn ($step) => [$step->input_blizzard_item_id, $step->output_blizzard_item_id])
            ->unique()
            ->values();

        if ($itemIds->isEmpty()) {
            return [];
        }

        // Fetch CatalogItems for all item IDs in a single query
        $catalogItems = CatalogItem::whereIn('blizzard_item_id', $itemIds)->get();

        // Fetch latest PriceSnapshot per catalog_item_id in a single query (application-side grouping)
        $catalogItemIds = $catalogItems->pluck('id');
        $snapshots = PriceSnapshot::whereIn('catalog_item_id', $catalogItemIds)
            ->orderByDesc('polled_at')
            ->get()
            ->groupBy('catalog_item_id')
            ->map(fn ($group) => $group->first()); // latest per catalog_item_id

        // Build result keyed by blizzard_item_id
        $result = [];
        foreach ($itemIds as $blizzardItemId) {
            $catalogItem = $catalogItems->firstWhere('blizzard_item_id', $blizzardItemId);
            $snapshot = $catalogItem ? ($snapshots->get($catalogItem->id)) : null;

            if ($snapshot) {
                $ageMinutes = (int) $snapshot->polled_at->diffInMinutes(now());
                $result[$blizzardItemId] = [
                    'price' => $snapshot->median_price,
                    'polled_at' => $snapshot->polled_at->toIso8601String(),
                    'age_minutes' => $ageMinutes,
                    'stale' => $ageMinutes > 60,
                    'item_name' => $catalogItem->display_name,
                ];
            } else {
                $result[$blizzardItemId] = [
                    'price' => null,
                    'polled_at' => null,
                    'age_minutes' => 0,
                    'stale' => false,
                    'item_name' => $catalogItem?->display_name ?? "Item #{$blizzardItemId}",
                ];
            }
        }

        return $result;
    }

    #[Computed]
    public function calculatorSteps(): array
    {
        return $this->steps->map(fn ($step) => [
            'id' => $step->id,
            'input_id' => $step->input_blizzard_item_id,
            'output_id' => $step->output_blizzard_item_id,
            'input_qty' => $step->input_qty,
            'output_qty_min' => $step->output_qty_min,
            'output_qty_max' => $step->output_qty_max,
            'input_name' => $step->inputCatalogItem?->display_name ?? "Item #{$step->input_blizzard_item_id}",
            'output_name' => $step->outputCatalogItem?->display_name ?? "Item #{$step->output_blizzard_item_id}",
            'input_icon' => $step->inputCatalogItem?->icon_url,
            'output_icon' => $step->outputCatalogItem?->icon_url,
        ])->toArray();
    }

    #[Computed]
    public function inputSuggestions(): array
    {
        if (strlen($this->inputSearch) < 2) {
            return [];
        }

        $items = CatalogItem::where('name', 'like', "%{$this->inputSearch}%")
            ->orderBy('name')
            ->orderBy('quality_tier')
            ->get(['id', 'name', 'blizzard_item_id', 'icon_url', 'quality_tier', 'rarity']);

        return $items->groupBy('name')
            ->take(15)
            ->flatMap(function ($group) {
                $icon = $group->firstWhere('icon_url', '!=', null)?->icon_url;
                $rarity = $group->firstWhere('rarity', '!=', null)?->rarity;

                return $group->map(fn ($item) => [
                    'id' => $item->id,
                    'name' => $item->name,
                    'blizzard_item_id' => $item->blizzard_item_id,
                    'icon_url' => $item->icon_url ?? $icon,
                    'quality_tier' => $item->quality_tier,
                    'rarity' => $item->rarity ?? $rarity,
                ]);
            })
            ->values()
            ->toArray();
    }

    #[Computed]
    public function outputSuggestions(): array
    {
        if (strlen($this->outputSearch) < 2) {
            return [];
        }

        $items = CatalogItem::where('name', 'like', "%{$this->outputSearch}%")
            ->orderBy('name')
            ->orderBy('quality_tier')
            ->get(['id', 'name', 'blizzard_item_id', 'icon_url', 'quality_tier', 'rarity']);

        return $items->groupBy('name')
            ->take(15)
            ->flatMap(function ($group) {
                $icon = $group->firstWhere('icon_url', '!=', null)?->icon_url;
                $rarity = $group->firstWhere('rarity', '!=', null)?->rarity;

                return $group->map(fn ($item) => [
                    'id' => $item->id,
                    'name' => $item->name,
                    'blizzard_item_id' => $item->blizzard_item_id,
                    'icon_url' => $item->icon_url ?? $icon,
                    'quality_tier' => $item->quality_tier,
                    'rarity' => $item->rarity ?? $rarity,
                ]);
            })
            ->values()
            ->toArray();
    }

    public function selectInputItem(int $blizzardItemId, string $name): void
    {
        $this->selectedInputItemId = $blizzardItemId;
        $this->selectedInputName = $name;
        $this->inputSearch = '';
        unset($this->inputSuggestions);
    }

    public function selectOutputItem(int $blizzardItemId, string $name): void
    {
        $this->selectedOutputItemId = $blizzardItemId;
        $this->selectedOutputName = $name;
        $this->outputSearch = '';
        unset($this->outputSuggestions);
    }

    public function showAddStepForm(): void
    {
        $this->addingStep = true;

        // Auto-fill input from last step's output if steps exist
        $lastStep = $this->steps->last();
        if ($lastStep) {
            $this->selectedInputItemId = $lastStep->output_blizzard_item_id;
            $this->selectedInputName = $lastStep->outputCatalogItem?->name ?? "Item #{$lastStep->output_blizzard_item_id}";
        } else {
            $this->selectedInputItemId = null;
            $this->selectedInputName = null;
        }

        $this->selectedOutputItemId = null;
        $this->selectedOutputName = null;
        $this->inputSearch = '';
        $this->outputSearch = '';
        $this->newInputQty = 1;
        $this->newOutputQtyMin = 1;
        $this->newOutputQtyMax = 1;
    }

    public function addStep(int $inputBlizzardItemId, int $outputBlizzardItemId, int $inputQty, int $outputQtyMin, int $outputQtyMax): void
    {
        abort_unless($this->shuffle->user_id === auth()->id(), 403);

        $this->validate([
            'newInputQty' => 'min:1',
            'newOutputQtyMin' => 'min:1',
            'newOutputQtyMax' => 'min:1',
        ]);

        if ($inputQty < 1) {
            $this->addError('newInputQty', 'Input quantity must be at least 1.');
            return;
        }

        if ($outputQtyMin < 1) {
            $this->addError('newOutputQtyMin', 'Minimum yield must be at least 1.');
            return;
        }

        if ($outputQtyMax < $outputQtyMin) {
            $this->addError('newOutputQtyMax', 'Maximum yield must be greater than or equal to minimum yield.');
            return;
        }

        $sortOrder = $this->shuffle->steps()->count();

        $this->shuffle->steps()->create([
            'input_blizzard_item_id' => $inputBlizzardItemId,
            'output_blizzard_item_id' => $outputBlizzardItemId,
            'input_qty' => $inputQty,
            'output_qty_min' => $outputQtyMin,
            'output_qty_max' => $outputQtyMax,
            'sort_order' => $sortOrder,
        ]);

        $this->autoWatch($inputBlizzardItemId);
        $this->autoWatch($outputBlizzardItemId);

        // Reset add-step form
        $this->addingStep = false;
        $this->selectedInputItemId = null;
        $this->selectedOutputItemId = null;
        $this->selectedInputName = null;
        $this->selectedOutputName = null;
        $this->inputSearch = '';
        $this->outputSearch = '';
        $this->newInputQty = 1;
        $this->newOutputQtyMin = 1;
        $this->newOutputQtyMax = 1;

        unset($this->steps);
        unset($this->priceData);
        unset($this->calculatorSteps);
    }

    public function saveStep(int $stepId, int $inputQty, int $outputQtyMin, int $outputQtyMax): void
    {
        $step = $this->shuffle->steps()->findOrFail($stepId);

        if ($inputQty < 1) {
            $this->addError("step_{$stepId}_inputQty", 'Input quantity must be at least 1.');
            return;
        }

        if ($outputQtyMin < 1) {
            $this->addError("step_{$stepId}_outputQtyMin", 'Minimum yield must be at least 1.');
            return;
        }

        if ($outputQtyMax < $outputQtyMin) {
            $this->addError("step_{$stepId}_outputQtyMax", 'Maximum yield must be greater than or equal to minimum yield.');
            return;
        }

        $step->update([
            'input_qty' => $inputQty,
            'output_qty_min' => $outputQtyMin,
            'output_qty_max' => $outputQtyMax,
        ]);

        unset($this->steps);
        unset($this->priceData);
        unset($this->calculatorSteps);
    }

    public function deleteStep(int $stepId): void
    {
        $step = $this->shuffle->steps()->findOrFail($stepId);
        $step->delete();

        // Renumber remaining steps contiguously
        $this->shuffle->steps()->get()->each(function (ShuffleStep $s, int $i): void {
            $s->update(['sort_order' => $i]);
        });

        unset($this->steps);
        unset($this->priceData);
        unset($this->calculatorSteps);
    }

    public function moveStepUp(int $stepId): void
    {
        $steps = $this->shuffle->steps()->get();
        $index = $steps->search(fn ($s) => $s->id === $stepId);

        if ($index < 1) {
            return; // Already first
        }

        $current = $steps[$index];
        $previous = $steps[$index - 1];

        $currentOrder = $current->sort_order;
        $current->update(['sort_order' => $previous->sort_order]);
        $previous->update(['sort_order' => $currentOrder]);

        unset($this->steps);
        unset($this->priceData);
        unset($this->calculatorSteps);
    }

    public function moveStepDown(int $stepId): void
    {
        $steps = $this->shuffle->steps()->get();
        $index = $steps->search(fn ($s) => $s->id === $stepId);

        if ($index >= $steps->count() - 1) {
            return; // Already last
        }

        $current = $steps[$index];
        $next = $steps[$index + 1];

        $currentOrder = $current->sort_order;
        $current->update(['sort_order' => $next->sort_order]);
        $next->update(['sort_order' => $currentOrder]);

        unset($this->steps);
        unset($this->priceData);
        unset($this->calculatorSteps);
    }

    private function autoWatch(int $blizzardItemId): void
    {
        $catalogItem = CatalogItem::where('blizzard_item_id', $blizzardItemId)->first();
        $name = $catalogItem?->name ?? "Item #{$blizzardItemId}";

        auth()->user()->watchedItems()->firstOrCreate(
            ['blizzard_item_id' => $blizzardItemId],
            [
                'name' => $name,
                'buy_threshold' => null,
                'sell_threshold' => null,
                'created_by_shuffle_id' => $this->shuffle->id,
            ]
        );
    }

    public function renameShuffle(string $name): void
    {
        $name = trim($name);

        if (strlen($name) < 1) {
            return;
        }

        $this->shuffle->update(['name' => $name]);
        $this->shuffle->refresh();
    }

    public function deleteShuffle(): void
    {
        $this->shuffle->delete();
        $this->redirect(route('shuffles'), navigate: true);
    }
}; ?>

<x-slot name="header">
    <div class="flex items-center gap-4">
        <a href="{{ route('shuffles') }}" wire:navigate class="text-sm text-gray-400 transition-colors hover:text-wow-gold">
            &larr; Back to Shuffles
        </a>
        <h2 class="text-xl font-semibold leading-tight text-wow-gold">
            {{ $shuffle->name }}
        </h2>
    </div>
</x-slot>

<div class="py-12">
    <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
        <div class="space-y-6">

            <!-- Page Header Card: Name + Badge + Delete -->
            <div class="overflow-hidden bg-wow-dark p-6 shadow-sm sm:rounded-lg">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">

                    <!-- Name (inline editable) + Profitability Badge -->
                    <div class="flex flex-wrap items-center gap-4">
                        <div
                            class="inline-flex items-center gap-2"
                            x-data="{ editing: false, saved: false, name: @js($shuffle->name) }"
                            x-init="$watch('editing', v => v && $nextTick(() => $refs.nameInput.select()))"
                        >
                            <span
                                x-show="!editing"
                                @click="editing = true"
                                class="cursor-pointer rounded px-1 py-0.5 text-xl font-semibold text-gray-100 hover:text-wow-gold"
                                title="Click to rename"
                            >{{ $shuffle->name }}</span>
                            <input
                                type="text"
                                x-show="editing"
                                x-ref="nameInput"
                                x-model="name"
                                @keydown.enter="$wire.renameShuffle(name); editing = false; saved = true; setTimeout(() => saved = false, 1500)"
                                @keydown.escape="name = @js($shuffle->name); editing = false"
                                @blur="$wire.renameShuffle(name); editing = false; saved = true; setTimeout(() => saved = false, 1500)"
                                class="rounded border border-gray-600 bg-wow-darker px-2 py-0.5 text-xl font-semibold text-gray-100 focus:border-wow-gold focus:outline-none focus:ring-1 focus:ring-wow-gold"
                                style="min-width: 16rem;"
                            />
                            <span x-show="saved" x-transition class="text-xs text-green-400">Saved</span>
                        </div>

                        <!-- Profitability Badge -->
                        @php $profit = $shuffle->profitPerUnit(); @endphp
                        @if ($profit === null)
                            <span class="inline-flex items-center gap-1.5 text-sm text-gray-500">
                                <span class="h-2.5 w-2.5 rounded-full bg-gray-600"></span>
                                <span>No profit data</span>
                            </span>
                        @elseif ($profit >= 0)
                            <span class="inline-flex items-center gap-1.5 text-sm font-medium text-green-400">
                                <span class="h-2.5 w-2.5 rounded-full bg-green-400"></span>
                                <span>+{{ $this->formatGold($profit) }} per unit</span>
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1.5 text-sm font-medium text-red-400">
                                <span class="h-2.5 w-2.5 rounded-full bg-red-400"></span>
                                <span>{{ $this->formatGold($profit) }} per unit</span>
                            </span>
                        @endif
                    </div>

                    <!-- Delete Button -->
                    <div class="flex shrink-0 items-center">
                        <button
                            x-data
                            @click="$dispatch('open-modal', 'confirm-delete-shuffle')"
                            class="rounded-md border border-red-700 px-3 py-1.5 text-sm font-medium text-red-400 transition-colors hover:border-red-500 hover:text-red-300 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 focus:ring-offset-wow-dark"
                        >
                            Delete Shuffle
                        </button>
                    </div>
                </div>
            </div>

            <!-- Step Editor -->
            <div class="overflow-hidden bg-wow-dark shadow-sm sm:rounded-lg">
                <div class="flex items-center justify-between border-b border-gray-700/50 px-6 py-4">
                    <div class="flex items-center gap-3">
                        <h3 class="font-medium text-gray-100">Steps</h3>
                        @if ($this->steps->isNotEmpty())
                            <span class="inline-flex items-center rounded-full bg-gray-700 px-2 py-0.5 text-xs font-medium text-gray-300">
                                {{ $this->steps->count() }}
                            </span>
                        @endif
                    </div>
                </div>

                <div class="p-6">

                    @if ($this->steps->isEmpty() && !$addingStep)
                        <!-- Empty State -->
                        <div class="flex flex-col items-center justify-center py-10 text-center">
                            <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-gray-700/50">
                                <svg class="h-6 w-6 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7" />
                                </svg>
                            </div>
                            <p class="mb-1 text-sm font-medium text-gray-400">No steps yet</p>
                            <p class="mb-6 text-xs text-gray-600">Add your first conversion step to start building a chain.</p>
                            <button
                                wire:click="showAddStepForm"
                                class="rounded-md border border-wow-gold px-4 py-2 text-sm font-medium text-wow-gold transition-colors hover:bg-wow-gold hover:text-wow-darker focus:outline-none focus:ring-2 focus:ring-wow-gold focus:ring-offset-2 focus:ring-offset-wow-dark"
                            >
                                + Add First Step
                            </button>
                        </div>
                    @else
                        <!-- Step Cards -->
                        <div class="space-y-0">
                            @foreach ($this->steps as $loopIndex => $step)
                                <!-- Step Card -->
                                <div wire:key="step-{{ $step->id }}">
                                    <div
                                        class="rounded-lg border border-gray-700/50 bg-wow-darker p-4"
                                        x-data="{
                                            inputQty: {{ $step->input_qty }},
                                            min: {{ $step->output_qty_min }},
                                            max: {{ $step->output_qty_max }},
                                            rangeMode: {{ $step->output_qty_min !== $step->output_qty_max ? 'true' : 'false' }},
                                            saving: false,
                                            error: '',
                                            saveYield() {
                                                this.error = '';
                                                if (this.inputQty < 1) { this.error = 'Input qty must be at least 1.'; return; }
                                                if (this.min < 1) { this.error = 'Min yield must be at least 1.'; return; }
                                                if (!this.rangeMode) { this.max = this.min; }
                                                if (this.max < this.min) { this.error = 'Max must be >= min.'; return; }
                                                this.saving = true;
                                                $wire.saveStep({{ $step->id }}, this.inputQty, this.min, this.max)
                                                    .then(() => { this.saving = false; });
                                            }
                                        }"
                                    >
                                        <!-- Item Row -->
                                        <div class="mb-3 flex flex-wrap items-center gap-3">
                                            <!-- Input Item -->
                                            <div class="flex items-center gap-2">
                                                @if ($step->inputCatalogItem?->icon_url)
                                                    <img src="{{ $step->inputCatalogItem->icon_url }}" alt="" class="h-8 w-8 rounded" loading="lazy" />
                                                @else
                                                    <span class="flex h-8 w-8 items-center justify-center rounded bg-gray-700 text-xs text-gray-500">?</span>
                                                @endif
                                                <div class="flex items-center gap-1.5">
                                                    <span class="text-sm font-medium text-gray-100">{{ $step->inputCatalogItem?->name ?? "Item #{$step->input_blizzard_item_id}" }}</span>
                                                    <!-- Watched indicator -->
                                                    @php
                                                        $inputWatched = auth()->user()->watchedItems()->where('blizzard_item_id', $step->input_blizzard_item_id)->exists();
                                                    @endphp
                                                    @if ($inputWatched)
                                                        <svg class="h-3.5 w-3.5 text-wow-gold/60" fill="currentColor" viewBox="0 0 20 20" title="Watched">
                                                            <path d="M10 12a2 2 0 100-4 2 2 0 000 4z" />
                                                            <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd" />
                                                        </svg>
                                                    @endif
                                                </div>
                                            </div>

                                            <!-- Chain Arrow -->
                                            <svg class="h-4 w-4 shrink-0 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3" />
                                            </svg>

                                            <!-- Output Item -->
                                            <div class="flex items-center gap-2">
                                                @if ($step->outputCatalogItem?->icon_url)
                                                    <img src="{{ $step->outputCatalogItem->icon_url }}" alt="" class="h-8 w-8 rounded" loading="lazy" />
                                                @else
                                                    <span class="flex h-8 w-8 items-center justify-center rounded bg-gray-700 text-xs text-gray-500">?</span>
                                                @endif
                                                <div class="flex items-center gap-1.5">
                                                    <span class="text-sm font-medium text-gray-100">{{ $step->outputCatalogItem?->name ?? "Item #{$step->output_blizzard_item_id}" }}</span>
                                                    <!-- Watched indicator -->
                                                    @php
                                                        $outputWatched = auth()->user()->watchedItems()->where('blizzard_item_id', $step->output_blizzard_item_id)->exists();
                                                    @endphp
                                                    @if ($outputWatched)
                                                        <svg class="h-3.5 w-3.5 text-wow-gold/60" fill="currentColor" viewBox="0 0 20 20" title="Watched">
                                                            <path d="M10 12a2 2 0 100-4 2 2 0 000 4z" />
                                                            <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd" />
                                                        </svg>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Yield Row -->
                                        <div class="mb-3 flex flex-wrap items-center gap-4 text-sm">
                                            <div class="flex items-center gap-2">
                                                <label class="text-xs text-gray-400">Input qty:</label>
                                                <input
                                                    type="number"
                                                    min="1"
                                                    x-model.number="inputQty"
                                                    @blur="saveYield()"
                                                    @keydown.enter="$el.blur()"
                                                    class="w-16 rounded border border-gray-600 bg-wow-dark px-2 py-1 text-center text-sm text-gray-100 focus:border-wow-gold focus:outline-none focus:ring-1 focus:ring-wow-gold"
                                                />
                                            </div>

                                            <div class="flex items-center gap-2">
                                                <label class="text-xs text-gray-400">Yield:</label>
                                                <input
                                                    type="number"
                                                    min="1"
                                                    x-model.number="min"
                                                    @blur="saveYield()"
                                                    @keydown.enter="$el.blur()"
                                                    class="w-16 rounded border border-gray-600 bg-wow-dark px-2 py-1 text-center text-sm text-gray-100 focus:border-wow-gold focus:outline-none focus:ring-1 focus:ring-wow-gold"
                                                />
                                                <template x-if="rangeMode">
                                                    <span class="flex items-center gap-2">
                                                        <span class="text-xs text-gray-500">to</span>
                                                        <input
                                                            type="number"
                                                            min="1"
                                                            x-model.number="max"
                                                            @blur="saveYield()"
                                                            @keydown.enter="$el.blur()"
                                                            class="w-16 rounded border border-gray-600 bg-wow-dark px-2 py-1 text-center text-sm text-gray-100 focus:border-wow-gold focus:outline-none focus:ring-1 focus:ring-wow-gold"
                                                        />
                                                        <button
                                                            type="button"
                                                            @click="rangeMode = false; max = min; saveYield()"
                                                            class="text-xs text-gray-500 hover:text-wow-gold"
                                                        >Fixed</button>
                                                    </span>
                                                </template>
                                                <template x-if="!rangeMode">
                                                    <button
                                                        type="button"
                                                        @click="rangeMode = true"
                                                        class="text-xs text-gray-500 hover:text-wow-gold"
                                                    >Set range</button>
                                                </template>
                                            </div>

                                            <span x-show="saving" class="text-xs text-gray-500">Saving...</span>
                                            <span x-show="error" x-text="error" class="text-xs text-red-400"></span>
                                        </div>

                                        <!-- Action Buttons Row -->
                                        <div class="flex items-center gap-2">
                                            <button
                                                wire:click="moveStepUp({{ $step->id }})"
                                                {{ $loopIndex === 0 ? 'disabled' : '' }}
                                                class="rounded border border-gray-600 p-1 text-gray-400 transition-colors hover:border-gray-500 hover:text-gray-200 disabled:cursor-not-allowed disabled:opacity-30 focus:outline-none"
                                                title="Move up"
                                            >
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
                                                </svg>
                                            </button>
                                            <button
                                                wire:click="moveStepDown({{ $step->id }})"
                                                {{ $loopIndex === $this->steps->count() - 1 ? 'disabled' : '' }}
                                                class="rounded border border-gray-600 p-1 text-gray-400 transition-colors hover:border-gray-500 hover:text-gray-200 disabled:cursor-not-allowed disabled:opacity-30 focus:outline-none"
                                                title="Move down"
                                            >
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                                </svg>
                                            </button>
                                            <button
                                                wire:click="deleteStep({{ $step->id }})"
                                                class="rounded border border-red-800 p-1 text-red-500 transition-colors hover:border-red-600 hover:text-red-400 focus:outline-none"
                                                title="Delete step"
                                            >
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Chain Flow Arrow between cards -->
                                    @if (!$loop->last)
                                        <div class="flex justify-center py-2">
                                            <svg class="h-5 w-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                            </svg>
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>

                        <!-- Add Step Button (shown when not adding) -->
                        @if (!$addingStep)
                            <div class="mt-4">
                                <button
                                    wire:click="showAddStepForm"
                                    class="rounded-md border border-gray-600 px-4 py-2 text-sm font-medium text-gray-400 transition-colors hover:border-wow-gold hover:text-wow-gold focus:outline-none focus:ring-2 focus:ring-wow-gold focus:ring-offset-2 focus:ring-offset-wow-dark"
                                >
                                    + Add Step
                                </button>
                            </div>
                        @endif
                    @endif

                    <!-- Add Step Form -->
                    @if ($addingStep)
                        <div class="mt-4 rounded-lg border border-wow-gold/30 bg-wow-darker p-5">
                            <h4 class="mb-4 text-sm font-medium text-gray-100">Add Conversion Step</h4>

                            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">

                                <!-- Input Item Search -->
                                <div>
                                    <label class="mb-1.5 block text-xs font-medium text-gray-400">Input Item</label>
                                    @if ($selectedInputItemId)
                                        <!-- Selected Badge -->
                                        <div class="flex items-center gap-2 rounded-md border border-wow-gold/40 bg-wow-dark px-3 py-2">
                                            <span class="flex-1 text-sm text-gray-100">{{ $selectedInputName }}</span>
                                            <button
                                                wire:click="$set('selectedInputItemId', null); $set('selectedInputName', null)"
                                                class="text-gray-500 hover:text-gray-300"
                                            >&times;</button>
                                        </div>
                                    @else
                                        <div
                                            class="relative"
                                            x-data="{ open: false }"
                                            @click.outside="open = false"
                                        >
                                            <input
                                                type="text"
                                                wire:model.live.debounce.200ms="inputSearch"
                                                @focus="open = true"
                                                @input="open = true"
                                                placeholder="Search input item..."
                                                class="w-full rounded-md border border-gray-600 bg-wow-dark px-3 py-2 text-sm text-gray-100 placeholder-gray-500 focus:border-wow-gold focus:outline-none focus:ring-1 focus:ring-wow-gold"
                                            />
                                            @if (count($this->inputSuggestions) > 0)
                                                <ul
                                                    x-show="open"
                                                    class="absolute z-50 mt-1 max-h-48 w-full overflow-y-auto rounded-md border border-gray-600 bg-wow-darker shadow-lg"
                                                    x-cloak
                                                >
                                                    @foreach ($this->inputSuggestions as $item)
                                                        <li
                                                            wire:click="selectInputItem({{ $item['blizzard_item_id'] }}, '{{ addslashes($item['name']) }}')"
                                                            @click="open = false"
                                                            class="flex cursor-pointer items-center gap-2 px-3 py-2 text-sm text-gray-200 hover:bg-wow-dark"
                                                        >
                                                            @if ($item['icon_url'])
                                                                <img src="{{ $item['icon_url'] }}" alt="" class="h-6 w-6 rounded" loading="lazy" />
                                                            @else
                                                                <span class="flex h-6 w-6 items-center justify-center rounded bg-gray-700 text-xs text-gray-500">?</span>
                                                            @endif
                                                            <span class="{{ match($item['rarity'] ?? null) {
                                                                'POOR' => 'text-rarity-poor',
                                                                'COMMON' => 'text-rarity-common',
                                                                'UNCOMMON' => 'text-rarity-uncommon',
                                                                'RARE' => 'text-rarity-rare',
                                                                'EPIC' => 'text-rarity-epic',
                                                                'LEGENDARY' => 'text-rarity-legendary',
                                                                default => 'text-gray-200',
                                                            } }}">{{ $item['name'] }}</span>
                                                            <x-tier-pip :tier="$item['quality_tier'] ?? null" />
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            @endif
                                        </div>
                                    @endif
                                </div>

                                <!-- Output Item Search -->
                                <div>
                                    <label class="mb-1.5 block text-xs font-medium text-gray-400">Output Item</label>
                                    @if ($selectedOutputItemId)
                                        <!-- Selected Badge -->
                                        <div class="flex items-center gap-2 rounded-md border border-wow-gold/40 bg-wow-dark px-3 py-2">
                                            <span class="flex-1 text-sm text-gray-100">{{ $selectedOutputName }}</span>
                                            <button
                                                wire:click="$set('selectedOutputItemId', null); $set('selectedOutputName', null)"
                                                class="text-gray-500 hover:text-gray-300"
                                            >&times;</button>
                                        </div>
                                    @else
                                        <div
                                            class="relative"
                                            x-data="{ open: false }"
                                            @click.outside="open = false"
                                        >
                                            <input
                                                type="text"
                                                wire:model.live.debounce.200ms="outputSearch"
                                                @focus="open = true"
                                                @input="open = true"
                                                placeholder="Search output item..."
                                                class="w-full rounded-md border border-gray-600 bg-wow-dark px-3 py-2 text-sm text-gray-100 placeholder-gray-500 focus:border-wow-gold focus:outline-none focus:ring-1 focus:ring-wow-gold"
                                            />
                                            @if (count($this->outputSuggestions) > 0)
                                                <ul
                                                    x-show="open"
                                                    class="absolute z-50 mt-1 max-h-48 w-full overflow-y-auto rounded-md border border-gray-600 bg-wow-darker shadow-lg"
                                                    x-cloak
                                                >
                                                    @foreach ($this->outputSuggestions as $item)
                                                        <li
                                                            wire:click="selectOutputItem({{ $item['blizzard_item_id'] }}, '{{ addslashes($item['name']) }}')"
                                                            @click="open = false"
                                                            class="flex cursor-pointer items-center gap-2 px-3 py-2 text-sm text-gray-200 hover:bg-wow-dark"
                                                        >
                                                            @if ($item['icon_url'])
                                                                <img src="{{ $item['icon_url'] }}" alt="" class="h-6 w-6 rounded" loading="lazy" />
                                                            @else
                                                                <span class="flex h-6 w-6 items-center justify-center rounded bg-gray-700 text-xs text-gray-500">?</span>
                                                            @endif
                                                            <span class="{{ match($item['rarity'] ?? null) {
                                                                'POOR' => 'text-rarity-poor',
                                                                'COMMON' => 'text-rarity-common',
                                                                'UNCOMMON' => 'text-rarity-uncommon',
                                                                'RARE' => 'text-rarity-rare',
                                                                'EPIC' => 'text-rarity-epic',
                                                                'LEGENDARY' => 'text-rarity-legendary',
                                                                default => 'text-gray-200',
                                                            } }}">{{ $item['name'] }}</span>
                                                            <x-tier-pip :tier="$item['quality_tier'] ?? null" />
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <!-- Yield Configuration -->
                            <div
                                class="mt-4 flex flex-wrap items-center gap-4"
                                x-data="{ rangeMode: false }"
                            >
                                <div class="flex items-center gap-2">
                                    <label class="text-xs text-gray-400">Input qty:</label>
                                    <input
                                        type="number"
                                        min="1"
                                        wire:model.number="newInputQty"
                                        class="w-16 rounded border border-gray-600 bg-wow-dark px-2 py-1 text-center text-sm text-gray-100 focus:border-wow-gold focus:outline-none focus:ring-1 focus:ring-wow-gold"
                                    />
                                </div>

                                <div class="flex items-center gap-2">
                                    <label class="text-xs text-gray-400">Yield:</label>
                                    <input
                                        type="number"
                                        min="1"
                                        wire:model.number="newOutputQtyMin"
                                        class="w-16 rounded border border-gray-600 bg-wow-dark px-2 py-1 text-center text-sm text-gray-100 focus:border-wow-gold focus:outline-none focus:ring-1 focus:ring-wow-gold"
                                    />
                                    <template x-if="rangeMode">
                                        <span class="flex items-center gap-2">
                                            <span class="text-xs text-gray-500">to</span>
                                            <input
                                                type="number"
                                                min="1"
                                                wire:model.number="newOutputQtyMax"
                                                class="w-16 rounded border border-gray-600 bg-wow-dark px-2 py-1 text-center text-sm text-gray-100 focus:border-wow-gold focus:outline-none focus:ring-1 focus:ring-wow-gold"
                                            />
                                            <button
                                                type="button"
                                                @click="rangeMode = false; $wire.set('newOutputQtyMax', $wire.newOutputQtyMin)"
                                                class="text-xs text-gray-500 hover:text-wow-gold"
                                            >Fixed</button>
                                        </span>
                                    </template>
                                    <template x-if="!rangeMode">
                                        <button
                                            type="button"
                                            @click="rangeMode = true"
                                            class="text-xs text-gray-500 hover:text-wow-gold"
                                        >Set range</button>
                                    </template>
                                </div>
                            </div>

                            @error('newInputQty') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
                            @error('newOutputQtyMin') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
                            @error('newOutputQtyMax') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror

                            <!-- Form Actions -->
                            <div class="mt-5 flex items-center gap-3">
                                <button
                                    wire:click="addStep({{ $selectedInputItemId ?? 'null' }}, {{ $selectedOutputItemId ?? 'null' }}, {{ $newInputQty }}, {{ $newOutputQtyMin }}, {{ $newOutputQtyMax }})"
                                    @disabled(!$selectedInputItemId || !$selectedOutputItemId)
                                    class="rounded-md bg-wow-gold px-4 py-2 text-sm font-semibold text-wow-darker transition-colors hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-wow-gold focus:ring-offset-2 focus:ring-offset-wow-dark disabled:cursor-not-allowed disabled:opacity-40"
                                >
                                    Add Step
                                </button>
                                <button
                                    wire:click="$set('addingStep', false)"
                                    class="rounded-md border border-gray-600 px-4 py-2 text-sm font-medium text-gray-400 transition-colors hover:border-gray-500 hover:text-gray-200 focus:outline-none"
                                >
                                    Cancel
                                </button>
                            </div>
                        </div>
                    @endif

                </div>
            </div>

            {{-- Batch Calculator Section (Plan 02 will populate the Alpine.js calculator UI) --}}
            @if ($this->steps->isNotEmpty())
                <div data-calculator-section class="overflow-hidden bg-wow-dark p-6 shadow-sm sm:rounded-lg">
                    <h3 class="text-base font-semibold text-wow-gold">Profit Calculator</h3>
                    {{-- Alpine.js calculator UI ships in Phase 12 Plan 02 --}}
                </div>
            @endif

        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <x-modal name="confirm-delete-shuffle" focusable>
        <div class="p-6">
            <h2 class="text-lg font-medium text-gray-100">
                Delete "{{ $shuffle->name }}"?
            </h2>
            <p class="mt-2 text-sm text-gray-400">
                This will delete all steps. Auto-watched items not used by other shuffles will also be removed from your watchlist.
            </p>
            <div class="mt-6 flex justify-end gap-3">
                <button
                    x-on:click="$dispatch('close')"
                    class="rounded-md border border-gray-600 px-4 py-2 text-sm font-medium text-gray-300 transition-colors hover:border-gray-500 hover:text-gray-200 focus:outline-none"
                >
                    Cancel
                </button>
                <button
                    wire:click="deleteShuffle"
                    x-on:click="$dispatch('close')"
                    class="rounded-md bg-red-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-red-500 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 focus:ring-offset-wow-dark"
                >
                    Delete
                </button>
            </div>
        </div>
    </x-modal>
</div>
