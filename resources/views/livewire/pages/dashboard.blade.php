<?php

declare(strict_types=1);

use App\Concerns\FormatsAuctionData;
use App\Models\PriceSnapshot;
use App\Models\WatchedItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    use FormatsAuctionData;

    public string $viewMode = 'list';

    public function toggleViewMode(string $mode): void
    {
        $this->viewMode = $mode;
    }

    #[Computed]
    public function watchedItems(): Collection
    {
        $items = auth()->user()->watchedItems()
            ->with([
                'catalogItem' => fn ($q) => $q->with(['priceSnapshots' => fn ($q2) => $q2->latest('polled_at')->limit(2)]),
                'catalogItem:blizzard_item_id,id,name,icon_url,quality_tier,rarity',
            ])
            ->orderBy('name')
            ->get();

        $items->each(function (WatchedItem $item) {
            $item->_signal = $this->rollingSignal($item);
        });

        return $items->sortBy(function (WatchedItem $item) {
            $sig = $item->_signal;
            $hasSignal = in_array($sig['signal'], ['buy', 'sell'], true);
            return [$hasSignal ? 0 : 1, -$sig['magnitude']];
        })->values();
    }

    public function dataFreshness(): string
    {
        $latest = PriceSnapshot::max('polled_at');

        if ($latest === null) {
            return 'Never';
        }

        return Carbon::parse($latest)->diffForHumans();
    }

    public function signalSummary(): string
    {
        $buyCount = 0;
        $sellCount = 0;

        foreach ($this->watchedItems as $item) {
            $sig = $item->_signal ?? ['signal' => 'none'];
            if ($sig['signal'] === 'buy') $buyCount++;
            if ($sig['signal'] === 'sell') $sellCount++;
        }

        if ($buyCount === 0 && $sellCount === 0) {
            return '';
        }

        $parts = [];
        if ($buyCount > 0) $parts[] = "{$buyCount} buy signal" . ($buyCount > 1 ? 's' : '');
        if ($sellCount > 0) $parts[] = "{$sellCount} sell signal" . ($sellCount > 1 ? 's' : '');

        return implode(', ', $parts);
    }
}; ?>

<x-slot name="header">
    <div class="flex items-center justify-between">
        <h2 class="text-xl font-semibold leading-tight text-wow-gold">
            {{ __('Dashboard') }}
        </h2>
        <div class="flex items-center gap-4">
            @if ($summary = $this->signalSummary())
                <span class="text-sm font-medium text-wow-gold">{{ $summary }}</span>
            @endif
            <span class="text-sm text-gray-400">Updated {{ $this->dataFreshness() }}</span>
        </div>
    </div>
</x-slot>

<div class="py-12">
    <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">

        @if ($this->watchedItems->isEmpty())
            {{-- Empty State --}}
            <div class="flex flex-col items-center justify-center rounded-lg border border-gray-700/50 bg-wow-dark p-16 text-center">
                <p class="mb-4 text-lg text-gray-400">No items tracked yet</p>
                <a
                    href="{{ route('watchlist') }}"
                    wire:navigate
                    class="rounded-md bg-wow-gold px-4 py-2 text-sm font-semibold text-wow-darker transition-colors hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-wow-gold focus:ring-offset-2 focus:ring-offset-wow-dark"
                >
                    Go to Watchlist &rarr;
                </a>
            </div>
        @else
            {{-- View Toggle --}}
            <div class="mb-4 flex justify-end">
                <div class="flex items-center gap-1 rounded-md border border-gray-600 p-0.5">
                    <button
                        wire:click="toggleViewMode('grid')"
                        class="rounded p-1 transition-colors {{ $viewMode === 'grid' ? 'text-wow-gold' : 'text-gray-500 hover:text-gray-300' }}"
                        title="Grid view"
                    >
                        <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20"><path d="M5 3a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2V5a2 2 0 00-2-2H5zM5 11a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2v-2a2 2 0 00-2-2H5zM11 5a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V5zM11 13a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                    </button>
                    <button
                        wire:click="toggleViewMode('list')"
                        class="rounded p-1 transition-colors {{ $viewMode === 'list' ? 'text-wow-gold' : 'text-gray-500 hover:text-gray-300' }}"
                        title="List view"
                    >
                        <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M3 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd"/></svg>
                    </button>
                </div>
            </div>

            @if ($viewMode === 'grid')
                {{-- Card Grid --}}
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3" wire:loading.class="opacity-50">
                    @foreach ($this->watchedItems as $item)
                        @php $sig = $item->_signal; @endphp
                        <a
                            href="{{ route('item.detail', $item) }}"
                            wire:navigate
                            wire:key="card-{{ $item->id }}"
                            class="block rounded-lg border bg-wow-dark p-5 transition-colors hover:border-wow-gold/50
                                {{ $sig['signal'] === 'buy' ? 'border-green-500/60' : ($sig['signal'] === 'sell' ? 'border-red-500/60' : 'border-gray-700/50') }}"
                        >
                            <div class="mb-3 flex items-start justify-between">
                                <div class="flex items-center gap-2">
                                    @if ($item->catalogItem?->icon_url)
                                        <img src="{{ $item->catalogItem->icon_url }}" alt="" class="h-8 w-8 rounded" loading="lazy" />
                                    @endif
                                    <h3 class="flex items-center gap-1.5 font-medium {{ $item->catalogItem?->rarityColorClass() ?? 'text-gray-100' }}">{{ $item->catalogItem?->name ?? $item->name }} <x-tier-pip :tier="$item->catalogItem?->quality_tier" /></h3>

                                    @if ($sig['signal'] === 'buy')
                                        <span class="signal-pulse-buy rounded-full bg-green-500/20 px-2 py-0.5 text-xs font-semibold text-green-400 ring-1 ring-green-500/50">
                                            BUY -{{ $sig['magnitude'] }}%
                                        </span>
                                    @elseif ($sig['signal'] === 'sell')
                                        <span class="signal-pulse-sell rounded-full bg-red-500/20 px-2 py-0.5 text-xs font-semibold text-red-400 ring-1 ring-red-500/50">
                                            SELL +{{ $sig['magnitude'] }}%
                                        </span>
                                    @elseif ($sig['signal'] === 'insufficient_data')
                                        <span class="rounded-full bg-gray-700/50 px-2 py-0.5 text-xs italic text-gray-500">
                                            Collecting data
                                        </span>
                                    @endif
                                </div>

                                @if ($item->catalogItem?->priceSnapshots?->isNotEmpty())
                                    @php
                                        $trend = $this->trendDirection($item);
                                        $pct = $this->trendPercent($item);
                                    @endphp
                                    <span class="flex items-center gap-1 text-sm {{ $trend === 'up' ? 'text-green-400' : ($trend === 'down' ? 'text-red-400' : 'text-gray-500') }}">
                                        @if ($trend === 'up')
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/></svg>
                                        @elseif ($trend === 'down')
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                        @else
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14"/></svg>
                                        @endif
                                        @if ($pct !== null)
                                            {{ $pct > 0 ? '+' : '' }}{{ $pct }}%
                                        @endif
                                    </span>
                                @endif
                            </div>

                            @if (!$item->catalogItem?->priceSnapshots?->isNotEmpty())
                                <p class="text-sm italic text-gray-500">Awaiting first snapshot</p>
                            @else
                                @php
                                    $latestPrice = $item->catalogItem->priceSnapshots->first()->median_price;
                                    $g = intdiv($latestPrice, 10000);
                                    $s = intdiv($latestPrice % 10000, 100);
                                    $c = $latestPrice % 100;
                                @endphp
                                <div class="text-lg font-semibold">
                                    @if ($g > 0)
                                        <span class="text-wow-gold">{{ number_format($g) }}g</span>
                                    @endif
                                    @if ($s > 0)
                                        <span class="text-gray-300">{{ $s }}s</span>
                                    @endif
                                    @if ($c > 0 || ($g === 0 && $s === 0))
                                        <span class="text-amber-700">{{ $c }}c</span>
                                    @endif
                                </div>
                            @endif
                        </a>
                    @endforeach

                    {{-- Loading skeleton --}}
                    <div wire:loading class="col-span-full">
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            @for ($i = 0; $i < 3; $i++)
                                <div class="animate-pulse rounded-lg border border-gray-700/50 bg-wow-dark p-5">
                                    <div class="mb-3 h-5 w-2/3 rounded bg-gray-700"></div>
                                    <div class="h-7 w-1/2 rounded bg-gray-700"></div>
                                </div>
                            @endfor
                        </div>
                    </div>
                </div>
            @else
                {{-- List View --}}
                <div class="overflow-hidden rounded-lg border border-gray-700/50" wire:loading.class="opacity-50">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b border-gray-700/50 bg-wow-dark/50 text-left text-xs font-medium uppercase tracking-wider text-gray-400">
                                <th class="px-4 py-3">Item</th>
                                <th class="px-4 py-3">Price</th>
                                <th class="px-4 py-3">Trend</th>
                                <th class="px-4 py-3">Signal</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-700/30">
                            @foreach ($this->watchedItems as $item)
                                @php $sig = $item->_signal; @endphp
                                <tr
                                    wire:key="row-{{ $item->id }}"
                                    class="bg-wow-dark transition-colors hover:bg-gray-800/50
                                        {{ $sig['signal'] === 'buy' ? 'border-l-2 border-l-green-500' : ($sig['signal'] === 'sell' ? 'border-l-2 border-l-red-500' : 'border-l-2 border-l-transparent') }}"
                                >
                                    <td class="px-4 py-3">
                                        <a href="{{ route('item.detail', $item) }}" wire:navigate class="flex items-center gap-2 hover:text-wow-gold">
                                            @if ($item->catalogItem?->icon_url)
                                                <img src="{{ $item->catalogItem->icon_url }}" alt="" class="h-6 w-6 rounded" loading="lazy" />
                                            @endif
                                            <span class="flex items-center gap-1.5 font-medium {{ $item->catalogItem?->rarityColorClass() ?? 'text-gray-100' }}">{{ $item->catalogItem?->name ?? $item->name }} <x-tier-pip :tier="$item->catalogItem?->quality_tier" /></span>
                                        </a>
                                    </td>
                                    <td class="px-4 py-3">
                                        @if (!$item->catalogItem?->priceSnapshots?->isNotEmpty())
                                            <span class="text-sm italic text-gray-500">—</span>
                                        @else
                                            @php
                                                $latestPrice = $item->catalogItem->priceSnapshots->first()->median_price;
                                                $g = intdiv($latestPrice, 10000);
                                                $s = intdiv($latestPrice % 10000, 100);
                                                $c = $latestPrice % 100;
                                            @endphp
                                            <a href="{{ route('item.detail', $item) }}" wire:navigate class="font-medium hover:text-wow-gold">
                                                @if ($g > 0)<span class="text-wow-gold">{{ number_format($g) }}g</span>@endif
                                                @if ($s > 0)<span class="text-gray-300">{{ $s }}s</span>@endif
                                                @if ($c > 0 || ($g === 0 && $s === 0))<span class="text-amber-700">{{ $c }}c</span>@endif
                                            </a>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        @if ($item->catalogItem?->priceSnapshots?->isNotEmpty())
                                            @php
                                                $trend = $this->trendDirection($item);
                                                $pct = $this->trendPercent($item);
                                            @endphp
                                            <span class="flex items-center gap-1 text-sm {{ $trend === 'up' ? 'text-green-400' : ($trend === 'down' ? 'text-red-400' : 'text-gray-500') }}">
                                                @if ($trend === 'up')
                                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/></svg>
                                                @elseif ($trend === 'down')
                                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                                @else
                                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14"/></svg>
                                                @endif
                                                @if ($pct !== null)
                                                    {{ $pct > 0 ? '+' : '' }}{{ $pct }}%
                                                @endif
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        @if ($sig['signal'] === 'buy')
                                            <span class="signal-pulse-buy rounded-full bg-green-500/20 px-2 py-0.5 text-xs font-semibold text-green-400 ring-1 ring-green-500/50">
                                                BUY -{{ $sig['magnitude'] }}%
                                            </span>
                                        @elseif ($sig['signal'] === 'sell')
                                            <span class="signal-pulse-sell rounded-full bg-red-500/20 px-2 py-0.5 text-xs font-semibold text-red-400 ring-1 ring-red-500/50">
                                                SELL +{{ $sig['magnitude'] }}%
                                            </span>
                                        @elseif ($sig['signal'] === 'insufficient_data')
                                            <span class="rounded-full bg-gray-700/50 px-2 py-0.5 text-xs italic text-gray-500">
                                                Collecting data
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

        @endif

    </div>
</div>
