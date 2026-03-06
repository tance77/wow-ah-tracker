<?php

declare(strict_types=1);

use App\Concerns\FormatsAuctionData;
use App\Models\CatalogItem;
use App\Models\PriceSnapshot;
use App\Models\Shuffle;
use App\Models\ShuffleStep;
use App\Models\ShuffleStepByproduct;
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

    // Byproduct form state
    public string $byproductSearch = '';
    public ?int $selectedByproductItemId = null;
    public ?string $selectedByproductName = null;
    public float $newByproductChance = 100;
    public int $newByproductQty = 1;
    public ?int $addingByproductForStep = null;

    public function mount(Shuffle $shuffle): void
    {
        $this->shuffle = $shuffle;
    }

    #[Computed]
    public function steps(): Collection
    {
        return $this->shuffle->steps()
            ->with(['inputCatalogItem', 'outputCatalogItem', 'byproducts'])
            ->get();
    }

    #[Computed]
    public function priceData(): array
    {
        // Collect all unique blizzard_item_ids from steps (including byproducts)
        $itemIds = $this->steps
            ->flatMap(fn ($step) => array_merge(
                [$step->input_blizzard_item_id, $step->output_blizzard_item_id],
                $step->byproducts->pluck('blizzard_item_id')->all()
            ))
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
            'byproducts' => $step->byproducts->map(fn ($bp) => [
                'blizzard_item_id' => $bp->blizzard_item_id,
                'item_name' => $bp->item_name,
                'chance_percent' => (float) $bp->chance_percent,
                'quantity' => $bp->quantity,
            ])->toArray(),
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

    #[Computed]
    public function byproductSuggestions(): array
    {
        if (strlen($this->byproductSearch) < 2) {
            return [];
        }

        $items = CatalogItem::where('name', 'like', "%{$this->byproductSearch}%")
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

    public function selectByproductItem(int $blizzardItemId, string $name): void
    {
        $this->selectedByproductItemId = $blizzardItemId;
        $this->selectedByproductName = $name;
        $this->byproductSearch = '';
        unset($this->byproductSuggestions);
    }

    public function addByproduct(int $stepId): void
    {
        abort_unless($this->shuffle->user_id === auth()->id(), 403);

        if ($this->selectedByproductItemId === null) {
            return;
        }

        if ($this->newByproductChance < 0.01 || $this->newByproductChance > 100) {
            return;
        }

        if ($this->newByproductQty < 1) {
            return;
        }

        $step = $this->shuffle->steps()->findOrFail($stepId);

        $catalogItem = CatalogItem::where('blizzard_item_id', $this->selectedByproductItemId)->first();
        $itemName = $catalogItem?->name ?? $this->selectedByproductName ?? "Item #{$this->selectedByproductItemId}";

        $step->byproducts()->create([
            'blizzard_item_id' => $this->selectedByproductItemId,
            'item_name' => $itemName,
            'chance_percent' => $this->newByproductChance,
            'quantity' => $this->newByproductQty,
        ]);

        $this->autoWatch($this->selectedByproductItemId);

        // Reset form state
        $this->selectedByproductItemId = null;
        $this->selectedByproductName = null;
        $this->byproductSearch = '';
        $this->newByproductChance = 100;
        $this->newByproductQty = 1;
        $this->addingByproductForStep = null;

        unset($this->steps);
        unset($this->priceData);
        unset($this->calculatorSteps);
    }

    public function removeByproduct(int $byproductId): void
    {
        abort_unless($this->shuffle->user_id === auth()->id(), 403);

        $byproduct = ShuffleStepByproduct::whereHas('step', fn ($q) => $q->where('shuffle_id', $this->shuffle->id))
            ->findOrFail($byproductId);

        $blizzardItemId = $byproduct->blizzard_item_id;
        $byproduct->delete();

        // Check if this item is still referenced by any step or byproduct
        $stillReferenced = ShuffleStep::where('input_blizzard_item_id', $blizzardItemId)
            ->orWhere('output_blizzard_item_id', $blizzardItemId)
            ->exists();

        if (! $stillReferenced) {
            $stillReferencedByByproduct = ShuffleStepByproduct::where('blizzard_item_id', $blizzardItemId)->exists();

            if (! $stillReferencedByByproduct) {
                WatchedItem::where('blizzard_item_id', $blizzardItemId)
                    ->whereNotNull('created_by_shuffle_id')
                    ->delete();
            }
        }

        unset($this->steps);
        unset($this->priceData);
        unset($this->calculatorSteps);
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

    public function exportShuffle(): void
    {
        $this->shuffle->load(['steps.inputCatalogItem', 'steps.outputCatalogItem', 'steps.byproducts']);

        $data = [
            'name' => $this->shuffle->name,
            'version' => 1,
            'steps' => $this->shuffle->steps->map(fn ($step) => [
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
        $this->dispatch('shuffle-exported', json: $json);
    }
}; ?>

<x-slot name="header">
    <div
        class="flex items-center justify-between"
        x-data="{ copied: false }"
        x-on:shuffle-exported.window="
            const ta = document.createElement('textarea');
            ta.value = $event.detail.json;
            ta.style.position = 'fixed';
            ta.style.opacity = '0';
            document.body.appendChild(ta);
            ta.select();
            document.execCommand('copy');
            document.body.removeChild(ta);
            copied = true;
            setTimeout(() => copied = false, 2000)
        "
    >
        <div class="flex items-center gap-4">
            <a href="{{ route('shuffles') }}" wire:navigate class="text-sm text-gray-400 transition-colors hover:text-wow-gold">
                &larr; Back to Shuffles
            </a>
            <h2 class="text-xl font-semibold leading-tight text-wow-gold">
                {{ $shuffle->name }}
            </h2>
        </div>
        <button
            wire:click="exportShuffle"
            class="text-sm text-gray-400 transition-colors hover:text-wow-gold"
        >
            <span x-show="!copied">Share</span>
            <span x-show="copied" x-cloak class="text-green-400">Copied!</span>
        </button>
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
                                        <!-- Step Header Row -->
                                        <div class="mb-3 flex items-center">
                                            <span class="text-xs font-semibold uppercase tracking-wider text-gray-500">Step {{ $loopIndex + 1 }}</span>
                                            <div class="ml-auto flex items-center gap-2">
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

                                        <!-- Conversion Ratio Summary -->
                                        <div class="mb-3 border-t border-gray-700/30 pt-2">
                                            <span class="text-xs text-gray-400" x-text="inputQty + ' {{ $step->inputCatalogItem?->name ?? 'Input' }} -> ' + (min === max ? min : min + '-' + max) + ' {{ $step->outputCatalogItem?->name ?? 'Output' }}'"></span>
                                        </div>

                                        <!-- Yield Row -->
                                        <div class="mb-3 flex flex-wrap items-center gap-4 text-sm">
                                            <div class="flex items-center gap-2">
                                                <label class="text-xs text-gray-400">Uses</label>
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
                                                <label class="text-xs text-gray-400">Produces</label>
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

                                        <!-- Byproducts Section -->
                                        @if ($step->byproducts->isNotEmpty() || $addingByproductForStep === $step->id)
                                            <div class="mt-3 border-t border-gray-700/30 pt-3">
                                                <div class="mb-2 flex items-center justify-between">
                                                    <span class="text-xs font-semibold uppercase tracking-wider text-gray-500">Byproducts</span>
                                                </div>

                                                <!-- Existing Byproducts -->
                                                @foreach ($step->byproducts as $bp)
                                                    <div class="mb-1.5 flex items-center gap-2 rounded border border-gray-700/30 bg-wow-dark px-3 py-1.5">
                                                        <span class="text-sm text-gray-200">{{ $bp->item_name }}</span>
                                                        <span class="rounded-full bg-gray-700 px-2 py-0.5 text-xs font-medium text-wow-gold">{{ rtrim(rtrim(number_format((float) $bp->chance_percent, 2), '0'), '.') }}%</span>
                                                        <span class="rounded-full bg-gray-700 px-2 py-0.5 text-xs font-medium text-gray-300">x{{ $bp->quantity }}</span>
                                                        @php
                                                            $bpPrice = $this->priceData[$bp->blizzard_item_id] ?? null;
                                                        @endphp
                                                        @if ($bpPrice && $bpPrice['price'])
                                                            <span class="text-xs text-gray-500">{{ $this->formatGold($bpPrice['price']) }}</span>
                                                        @endif
                                                        <button
                                                            wire:click="removeByproduct({{ $bp->id }})"
                                                            class="ml-auto text-red-600 hover:text-red-400"
                                                            title="Remove byproduct"
                                                        >
                                                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                            </svg>
                                                        </button>
                                                    </div>
                                                @endforeach

                                                <!-- Add Byproduct Form -->
                                                @if ($addingByproductForStep === $step->id)
                                                    <div class="mt-2 rounded border border-gray-700/50 bg-wow-dark p-3">
                                                        <!-- Item Search -->
                                                        <div class="mb-2">
                                                            <label class="mb-1 block text-xs text-gray-400">Item</label>
                                                            @if ($selectedByproductItemId)
                                                                <div class="flex items-center gap-2 rounded border border-wow-gold/40 bg-wow-darker px-3 py-1.5">
                                                                    <span class="flex-1 text-sm text-gray-100">{{ $selectedByproductName }}</span>
                                                                    <button
                                                                        wire:click="$set('selectedByproductItemId', null); $set('selectedByproductName', null)"
                                                                        class="rounded bg-gray-700 px-1.5 py-0.5 text-xs font-medium text-gray-300 transition-colors hover:bg-red-700 hover:text-white"
                                                                    >Clear</button>
                                                                </div>
                                                            @else
                                                                <div
                                                                    class="relative"
                                                                    x-data="{ open: false }"
                                                                    @click.outside="open = false"
                                                                >
                                                                    <input
                                                                        type="text"
                                                                        wire:model.live.debounce.300ms="byproductSearch"
                                                                        @focus="open = true"
                                                                        @input="open = true"
                                                                        placeholder="Search byproduct item..."
                                                                        class="w-full rounded border border-gray-600 bg-wow-darker px-3 py-1.5 text-sm text-gray-100 placeholder-gray-500 focus:border-wow-gold focus:outline-none focus:ring-1 focus:ring-wow-gold"
                                                                    />
                                                                    @if (count($this->byproductSuggestions) > 0)
                                                                        <ul
                                                                            x-show="open"
                                                                            class="absolute z-50 mt-1 max-h-48 w-full overflow-y-auto rounded border border-gray-600 bg-wow-darker shadow-lg"
                                                                            x-cloak
                                                                        >
                                                                            @foreach ($this->byproductSuggestions as $item)
                                                                                <li
                                                                                    wire:click="selectByproductItem({{ $item['blizzard_item_id'] }}, '{{ addslashes($item['name']) }}')"
                                                                                    @click="open = false"
                                                                                    class="flex cursor-pointer items-center gap-2 px-3 py-1.5 text-sm text-gray-200 hover:bg-wow-dark"
                                                                                >
                                                                                    @if ($item['icon_url'])
                                                                                        <img src="{{ $item['icon_url'] }}" alt="" class="h-5 w-5 rounded" loading="lazy" />
                                                                                    @else
                                                                                        <span class="flex h-5 w-5 items-center justify-center rounded bg-gray-700 text-xs text-gray-500">?</span>
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

                                                        <!-- Chance + Qty -->
                                                        <div class="mb-2 flex items-center gap-3">
                                                            <div class="flex items-center gap-1.5">
                                                                <label class="text-xs text-gray-400">Chance %</label>
                                                                <input
                                                                    type="number"
                                                                    min="0.01"
                                                                    max="100"
                                                                    step="0.01"
                                                                    wire:model.number="newByproductChance"
                                                                    class="w-20 rounded border border-gray-600 bg-wow-darker px-2 py-1 text-center text-sm text-gray-100 focus:border-wow-gold focus:outline-none focus:ring-1 focus:ring-wow-gold"
                                                                />
                                                            </div>
                                                            <div class="flex items-center gap-1.5">
                                                                <label class="text-xs text-gray-400">Qty</label>
                                                                <input
                                                                    type="number"
                                                                    min="1"
                                                                    wire:model.number="newByproductQty"
                                                                    class="w-16 rounded border border-gray-600 bg-wow-darker px-2 py-1 text-center text-sm text-gray-100 focus:border-wow-gold focus:outline-none focus:ring-1 focus:ring-wow-gold"
                                                                />
                                                            </div>
                                                        </div>

                                                        <!-- Actions -->
                                                        <div class="flex items-center gap-2">
                                                            <button
                                                                wire:click="addByproduct({{ $step->id }})"
                                                                @disabled(!$selectedByproductItemId)
                                                                class="rounded bg-wow-gold px-3 py-1 text-xs font-semibold text-wow-darker transition-colors hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-40"
                                                            >
                                                                Add
                                                            </button>
                                                            <button
                                                                wire:click="$set('addingByproductForStep', null)"
                                                                class="rounded border border-gray-600 px-3 py-1 text-xs text-gray-400 transition-colors hover:border-gray-500 hover:text-gray-200"
                                                            >
                                                                Cancel
                                                            </button>
                                                        </div>
                                                    </div>
                                                @endif
                                            </div>
                                        @endif

                                        <!-- Add Byproduct Button -->
                                        @if ($addingByproductForStep !== $step->id)
                                            <div class="mt-3 border-t border-gray-700/30 pt-3">
                                                <button
                                                    wire:click="$set('addingByproductForStep', {{ $step->id }})"
                                                    class="rounded-md border border-dashed border-gray-600 px-3 py-1.5 text-xs font-medium text-gray-400 transition-colors hover:border-wow-gold hover:text-wow-gold"
                                                >
                                                    + Add Byproduct
                                                </button>
                                            </div>
                                        @endif

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
                            <h4 class="mb-4 text-sm font-medium text-gray-100">Add Step {{ $this->steps->count() + 1 }}</h4>

                            <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-gray-500">1. Choose Items</p>

                            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">

                                <!-- Input Item Search -->
                                <div>
                                    <label class="mb-1.5 block text-xs font-medium text-gray-400">What do you put in?</label>
                                    @if ($selectedInputItemId)
                                        <!-- Selected Badge -->
                                        <div class="flex items-center gap-2 rounded-md border border-wow-gold/40 bg-wow-dark px-3 py-2">
                                            <span class="flex-1 text-sm text-gray-100">{{ $selectedInputName }}</span>
                                            <button
                                                wire:click="$set('selectedInputItemId', null); $set('selectedInputName', null)"
                                                class="rounded bg-gray-700 px-1.5 py-0.5 text-xs font-medium text-gray-300 transition-colors hover:bg-red-700 hover:text-white"
                                            >Clear</button>
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
                                    <label class="mb-1.5 block text-xs font-medium text-gray-400">What do you get out?</label>
                                    @if ($selectedOutputItemId)
                                        <!-- Selected Badge -->
                                        <div class="flex items-center gap-2 rounded-md border border-wow-gold/40 bg-wow-dark px-3 py-2">
                                            <span class="flex-1 text-sm text-gray-100">{{ $selectedOutputName }}</span>
                                            <button
                                                wire:click="$set('selectedOutputItemId', null); $set('selectedOutputName', null)"
                                                class="rounded bg-gray-700 px-1.5 py-0.5 text-xs font-medium text-gray-300 transition-colors hover:bg-red-700 hover:text-white"
                                            >Clear</button>
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
                            <div class="mt-5 border-t border-gray-700/30 pt-5">
                                <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-gray-500">2. Set Conversion Rate</p>
                            <div
                                class="flex flex-wrap items-center gap-4"
                                x-data="{ rangeMode: false }"
                            >
                                <div class="flex items-center gap-2">
                                    <label class="text-xs text-gray-400">How many input items?</label>
                                    <input
                                        type="number"
                                        min="1"
                                        wire:model.number="newInputQty"
                                        class="w-16 rounded border border-gray-600 bg-wow-dark px-2 py-1 text-center text-sm text-gray-100 focus:border-wow-gold focus:outline-none focus:ring-1 focus:ring-wow-gold"
                                    />
                                </div>

                                <div class="flex items-center gap-2">
                                    <label class="text-xs text-gray-400">Produces</label>
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
                            <p class="mt-2 text-xs text-gray-600">Example: if 5 ore produces 1-3 gems, set input to 5 and yield to 1-3</p>
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
                                    Save Step
                                </button>
                                <button
                                    wire:click="$set('addingStep', false)"
                                    class="rounded-md border border-gray-600 px-4 py-2 text-sm font-medium text-gray-400 transition-colors hover:border-gray-500 hover:text-gray-200 focus:outline-none"
                                >
                                    {{ $this->steps->count() > 0 ? 'Done' : 'Cancel' }}
                                </button>
                            </div>
                        </div>
                    @endif

                </div>
            </div>

            {{-- Batch Calculator Section --}}
            @if ($this->steps->isNotEmpty())
                <div data-calculator-section class="overflow-hidden bg-wow-dark shadow-sm sm:rounded-lg" wire:ignore>
                    <div
                        x-data="batchCalculator(@js($this->priceData), @js($this->calculatorSteps))"
                        class="p-6"
                    >
                        {{-- Section Header --}}
                        <h3 class="mb-4 text-base font-semibold text-wow-gold">Batch Calculator</h3>

                        {{-- Staleness Warning Banner --}}
                        <div
                            x-show="staleItems.length > 0"
                            x-cloak
                            class="mb-4 rounded-md border border-amber-700/50 bg-amber-900/20 px-4 py-3"
                        >
                            <div class="flex items-start gap-2">
                                <svg class="mt-0.5 h-4 w-4 shrink-0 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                                <div class="text-xs text-amber-300">
                                    <span class="font-medium">Stale prices:</span>
                                    <template x-for="(item, i) in staleItems" :key="item.id">
                                        <span>
                                            <span x-text="item.name"></span>
                                            <span class="text-amber-500" x-text="'(' + item.age_minutes + 'm ago)'"></span><template x-if="i < staleItems.length - 1">, </template>
                                        </span>
                                    </template>
                                </div>
                            </div>
                        </div>

                        {{-- Input Quantity --}}
                        <div class="mb-5 flex items-center gap-3">
                            <label class="text-sm font-medium text-gray-300">Input Quantity</label>
                            <input
                                type="number"
                                min="1"
                                x-model.number="batchQty"
                                class="w-24 rounded border border-gray-600 bg-wow-darker px-3 py-1.5 text-center text-sm text-gray-100 focus:border-wow-gold focus:outline-none focus:ring-1 focus:ring-wow-gold"
                            />
                        </div>

                        {{-- Step Breakdown Table --}}
                        <div class="mb-5 overflow-x-auto">
                            <table class="w-full text-xs">
                                <thead>
                                    <tr class="border-b border-gray-700 text-left text-gray-500">
                                        <th class="pb-2 pr-3 font-medium">#</th>
                                        <th class="pb-2 pr-3 font-medium">Input</th>
                                        <th class="pb-2 pr-3 font-medium">Output</th>
                                        <th class="pb-2 font-medium text-right">Yield ratio</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template x-for="(row, i) in cascade" :key="i">
                                        <tr class="border-b border-gray-700/40">
                                            <td class="py-2 pr-3 text-gray-500" x-text="i + 1"></td>
                                            <td class="py-2 pr-3">
                                                <div class="flex items-center gap-1.5">
                                                    <template x-if="row.inputIcon">
                                                        <img :src="row.inputIcon" alt="" class="h-5 w-5 rounded" loading="lazy" />
                                                    </template>
                                                    <span class="text-gray-200" x-text="row.inputName"></span>
                                                    <span class="text-gray-500" x-text="'x' + row.inQty"></span>
                                                </div>
                                            </td>
                                            <td class="py-2 pr-3">
                                                <div class="flex items-center gap-1.5">
                                                    <template x-if="row.outputIcon">
                                                        <img :src="row.outputIcon" alt="" class="h-5 w-5 rounded" loading="lazy" />
                                                    </template>
                                                    <span class="text-gray-200" x-text="row.outputName"></span>
                                                    <template x-if="row.outMin === row.outMax">
                                                        <span class="text-wow-gold" x-text="'x' + row.outMin"></span>
                                                    </template>
                                                    <template x-if="row.outMin !== row.outMax">
                                                        <span class="text-wow-gold" x-text="'x' + row.outMin + ' - ' + row.outMax"></span>
                                                    </template>
                                                </div>
                                            </td>
                                            <td class="py-2 text-right text-gray-400" x-text="row.ratioLabel"></td>
                                        </tr>
                                        <template x-for="(bp, bi) in row.byproducts" :key="'bp-'+i+'-'+bi">
                                            <tr class="border-b border-gray-700/20">
                                                <td class="py-1 pr-3"></td>
                                                <td class="py-1 pr-3" colspan="2">
                                                    <div class="flex items-center gap-1.5 pl-4 text-gray-500 italic">
                                                        <span>↳</span>
                                                        <span x-text="bp.name"></span>
                                                        <span class="text-gray-600" x-text="'(' + bp.chance + '% × ' + bp.qty + ')'"></span>
                                                        <template x-if="bp.hasPrice">
                                                            <span class="text-gray-400" x-text="bp.evMin === bp.evMax ? 'EV: ' + formatGold(bp.evMin) : 'EV: ' + formatGold(bp.evMin) + ' - ' + formatGold(bp.evMax)"></span>
                                                        </template>
                                                        <template x-if="!bp.hasPrice">
                                                            <span class="text-yellow-600">no price data</span>
                                                        </template>
                                                    </div>
                                                </td>
                                                <td class="py-1"></td>
                                            </tr>
                                        </template>
                                    </template>
                                </tbody>
                            </table>
                        </div>

                        {{-- Profit Summary --}}
                        <div class="rounded-md border border-gray-700/50 bg-wow-darker p-4">
                            <h4 class="mb-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Profit Summary</h4>

                            <template x-if="canCalculate">
                                <div class="space-y-2">
                                    {{-- Column headers --}}
                                    <div class="grid grid-cols-3 text-xs text-gray-500">
                                        <span></span>
                                        <span class="text-center">Min</span>
                                        <span class="text-center">Max</span>
                                    </div>
                                    {{-- Total Cost --}}
                                    <div class="grid grid-cols-3 items-center text-sm">
                                        <span class="text-gray-400">Total Cost</span>
                                        <span class="text-center text-gray-300" x-text="formatGold(totalCostMin)"></span>
                                        <span class="text-center text-gray-300" x-text="formatGold(totalCostMax)"></span>
                                    </div>
                                    {{-- Gross Value --}}
                                    <div class="grid grid-cols-3 items-center text-sm">
                                        <span class="text-gray-400">Gross Value</span>
                                        <span class="text-center text-gray-300" x-text="formatGold(grossValueMin)"></span>
                                        <span class="text-center text-gray-300" x-text="formatGold(grossValueMax)"></span>
                                    </div>
                                    {{-- Byproduct EV (shown only when byproducts contribute value) --}}
                                    <template x-if="_byproductEV.min > 0 || _byproductEV.max > 0">
                                        <div class="grid grid-cols-3 items-center text-xs">
                                            <span class="pl-2 text-gray-500 italic">incl. byproduct EV</span>
                                            <span class="text-center text-gray-500" x-text="formatGold(Math.round(_byproductEV.min))"></span>
                                            <span class="text-center text-gray-500" x-text="formatGold(Math.round(_byproductEV.max))"></span>
                                        </div>
                                    </template>
                                    {{-- AH Cut --}}
                                    <div class="grid grid-cols-3 items-center border-b border-gray-700/40 pb-2 text-sm">
                                        <span class="text-gray-500">AH Cut (5%)</span>
                                        <span class="text-center text-gray-500" x-text="'- ' + formatGold(Math.round(grossValueMin * 0.05))"></span>
                                        <span class="text-center text-gray-500" x-text="'- ' + formatGold(Math.round(grossValueMax * 0.05))"></span>
                                    </div>
                                    {{-- Net Profit --}}
                                    <div class="grid grid-cols-3 items-center pt-1 text-sm font-semibold">
                                        <span class="text-gray-300">Net Profit</span>
                                        <span
                                            class="text-center"
                                            :class="netProfitMin >= 0 ? 'text-green-400' : 'text-red-400'"
                                            x-text="formatGold(netProfitMin)"
                                        ></span>
                                        <span
                                            class="text-center"
                                            :class="netProfitMax >= 0 ? 'text-green-400' : 'text-red-400'"
                                            x-text="formatGold(netProfitMax)"
                                        ></span>
                                    </div>
                                    {{-- Break-even --}}
                                    <div class="grid grid-cols-3 items-center border-t border-gray-700/40 pt-2 text-sm">
                                        <span class="text-gray-400">Break-even / unit</span>
                                        <span class="text-center text-wow-gold" x-text="formatGold(breakEven)"></span>
                                        <span class="text-center text-gray-600">---</span>
                                    </div>
                                </div>
                            </template>

                            <template x-if="!canCalculate">
                                <div class="text-sm text-gray-500">
                                    Cannot calculate &mdash; missing prices for:
                                    <span class="text-gray-400" x-text="missingPriceNames.join(', ')"></span>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

                <script>
                    function batchCalculator(prices, steps) {
                        return {
                            batchQty: 1,
                            prices: prices,
                            steps: steps,

                            get cascade() {
                                let qtyMin = this.batchQty;
                                let qtyMax = this.batchQty;
                                return this.steps.map((step, i) => {
                                    const inQty = i === 0 ? qtyMin : qtyMin; // already cascaded
                                    const outMin = Math.floor(qtyMin * step.output_qty_min / Math.max(1, step.input_qty));
                                    const outMax = Math.floor(qtyMax * step.output_qty_max / Math.max(1, step.input_qty));
                                    const ratioMin = (step.output_qty_min / step.input_qty).toFixed(2);
                                    const ratioMax = (step.output_qty_max / step.input_qty).toFixed(2);
                                    const ratioLabel = step.output_qty_min === step.output_qty_max
                                        ? ratioMin + 'x'
                                        : ratioMin + 'x - ' + ratioMax + 'x';
                                    const batchesMin = Math.floor(qtyMin / Math.max(1, step.input_qty));
                                    const batchesMax = Math.floor(qtyMax / Math.max(1, step.input_qty));
                                    const byproducts = (step.byproducts || []).map(bp => {
                                        const bpPrice = this.prices[bp.blizzard_item_id]?.price ?? 0;
                                        return {
                                            name: bp.item_name,
                                            chance: bp.chance_percent,
                                            qty: bp.quantity,
                                            evMin: Math.round(bpPrice * (bp.chance_percent / 100) * bp.quantity * batchesMin),
                                            evMax: Math.round(bpPrice * (bp.chance_percent / 100) * bp.quantity * batchesMax),
                                            hasPrice: bpPrice > 0,
                                        };
                                    });
                                    const row = {
                                        inQty: qtyMin,
                                        outMin,
                                        outMax,
                                        inputName: step.input_name,
                                        outputName: step.output_name,
                                        inputIcon: step.input_icon,
                                        outputIcon: step.output_icon,
                                        ratioLabel,
                                        byproducts,
                                    };
                                    qtyMin = outMin;
                                    qtyMax = outMax;
                                    return row;
                                });
                            },

                            get staleItems() {
                                const seen = new Set();
                                const result = [];
                                for (const step of this.steps) {
                                    for (const id of [step.input_id, step.output_id]) {
                                        if (!seen.has(id)) {
                                            seen.add(id);
                                            const p = this.prices[id];
                                            if (p && p.stale) {
                                                result.push({ id, name: p.item_name, age_minutes: p.age_minutes });
                                            }
                                        }
                                    }
                                }
                                return result;
                            },

                            get canCalculate() {
                                if (this.steps.length === 0) return false;
                                const firstInputId = this.steps[0].input_id;
                                const lastOutputId = this.steps[this.steps.length - 1].output_id;
                                return (this.prices[firstInputId]?.price ?? null) !== null
                                    && (this.prices[lastOutputId]?.price ?? null) !== null;
                            },

                            get missingPriceNames() {
                                if (this.steps.length === 0) return [];
                                const missing = [];
                                const firstInputId = this.steps[0].input_id;
                                const lastOutputId = this.steps[this.steps.length - 1].output_id;
                                if ((this.prices[firstInputId]?.price ?? null) === null) {
                                    missing.push(this.prices[firstInputId]?.item_name ?? 'Input item');
                                }
                                if ((this.prices[lastOutputId]?.price ?? null) === null) {
                                    missing.push(this.prices[lastOutputId]?.item_name ?? 'Output item');
                                }
                                return missing;
                            },

                            get _cascadedQtyMin() {
                                let qty = this.batchQty;
                                for (const step of this.steps) {
                                    qty = Math.floor(qty * step.output_qty_min / Math.max(1, step.input_qty));
                                }
                                return qty;
                            },

                            get _cascadedQtyMax() {
                                let qty = this.batchQty;
                                for (const step of this.steps) {
                                    qty = Math.floor(qty * step.output_qty_max / Math.max(1, step.input_qty));
                                }
                                return qty;
                            },

                            get totalCostMin() {
                                if (!this.canCalculate) return 0;
                                const firstInputId = this.steps[0].input_id;
                                return this.batchQty * (this.prices[firstInputId]?.price ?? 0);
                            },

                            get totalCostMax() {
                                return this.totalCostMin; // cost is fixed (first input qty x price)
                            },

                            get _byproductEV() {
                                // Calculate byproduct expected value across all steps for min/max
                                let evMin = 0;
                                let evMax = 0;
                                let cascadedMin = this.batchQty;
                                let cascadedMax = this.batchQty;
                                for (const step of this.steps) {
                                    const batchesMin = Math.floor(cascadedMin / Math.max(1, step.input_qty));
                                    const batchesMax = Math.floor(cascadedMax / Math.max(1, step.input_qty));
                                    for (const bp of (step.byproducts || [])) {
                                        const bpPrice = this.prices[bp.blizzard_item_id]?.price ?? 0;
                                        evMin += bpPrice * (bp.chance_percent / 100) * bp.quantity * batchesMin;
                                        evMax += bpPrice * (bp.chance_percent / 100) * bp.quantity * batchesMax;
                                    }
                                    cascadedMin = Math.floor(cascadedMin * step.output_qty_min / Math.max(1, step.input_qty));
                                    cascadedMax = Math.floor(cascadedMax * step.output_qty_max / Math.max(1, step.input_qty));
                                }
                                return { min: evMin, max: evMax };
                            },

                            get grossValueMin() {
                                if (!this.canCalculate) return 0;
                                const lastOutputId = this.steps[this.steps.length - 1].output_id;
                                return this._cascadedQtyMin * (this.prices[lastOutputId]?.price ?? 0) + this._byproductEV.min;
                            },

                            get grossValueMax() {
                                if (!this.canCalculate) return 0;
                                const lastOutputId = this.steps[this.steps.length - 1].output_id;
                                return this._cascadedQtyMax * (this.prices[lastOutputId]?.price ?? 0) + this._byproductEV.max;
                            },

                            get netProfitMin() {
                                return Math.round(this.grossValueMin * 0.95) - this.totalCostMin;
                            },

                            get netProfitMax() {
                                return Math.round(this.grossValueMax * 0.95) - this.totalCostMax;
                            },

                            get breakEven() {
                                if (!this.canCalculate || this.batchQty < 1) return 0;
                                return Math.floor(Math.round(this.grossValueMin * 0.95) / this.batchQty);
                            },

                            formatGold(copper) {
                                if (copper === null || copper === undefined) return '--';
                                const neg = copper < 0;
                                const abs = Math.round(Math.abs(copper));
                                const g = Math.floor(abs / 10000);
                                const s = Math.floor((abs % 10000) / 100);
                                const c = abs % 100;
                                let parts = [];
                                if (g > 0) parts.push(g + 'g');
                                if (s > 0) parts.push(s + 's');
                                if (c > 0 || parts.length === 0) parts.push(c + 'c');
                                return (neg ? '-' : '') + parts.join(' ');
                            },
                        };
                    }
                </script>
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
