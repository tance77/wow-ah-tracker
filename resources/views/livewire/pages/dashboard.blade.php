<?php

declare(strict_types=1);

use App\Models\WatchedItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public ?int $selectedItemId = null;

    public string $viewMode = 'list';

    public string $timeframe = '7d';

    public function toggleViewMode(string $mode): void
    {
        $this->viewMode = $mode;
    }

    #[Computed]
    public function watchedItems(): Collection
    {
        $items = auth()->user()->watchedItems()
            ->with([
                'priceSnapshots' => fn ($q) => $q->latest('polled_at')->limit(2),
                'catalogItem:blizzard_item_id,name,icon_url,quality_tier',
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

    public function selectItem(int $id): void
    {
        if ($this->selectedItemId === $id) {
            $this->selectedItemId = null;
        } else {
            $this->selectedItemId = $id;
            $this->loadChart();
        }
    }

    public function setTimeframe(string $frame): void
    {
        $this->timeframe = $frame;
        $this->loadChart();
    }

    private function loadChart(): void
    {
        $cutoff = match ($this->timeframe) {
            '24h' => now()->subHours(24),
            '30d' => now()->subDays(30),
            default => now()->subDays(7),
        };

        $item = auth()->user()
            ->watchedItems()
            ->findOrFail($this->selectedItemId);

        $snapshots = $item->priceSnapshots()
            ->where('polled_at', '>=', $cutoff)
            ->orderBy('polled_at')
            ->get(['polled_at', 'median_price', 'min_price']);

        $median = $snapshots->map(fn ($s) => [
            'x' => $s->polled_at->timestamp * 1000,
            'y' => $s->median_price,
        ])->values()->toArray();

        $min = $snapshots->map(fn ($s) => [
            'x' => $s->polled_at->timestamp * 1000,
            'y' => $s->min_price,
        ])->values()->toArray();

        // Rolling average: 7-day avg as a flat horizontal reference line
        $rollingAvg = (int) round((float) (
            $item->priceSnapshots()
                ->where('polled_at', '>=', now()->subDays(7))
                ->avg('median_price') ?? 0
        ));

        $rollingAvgSeries = [];
        if ($rollingAvg > 0 && count($median) >= 2) {
            $rollingAvgSeries = [
                ['x' => $median[0]['x'], 'y' => $rollingAvg],
                ['x' => $median[count($median) - 1]['x'], 'y' => $rollingAvg],
            ];
        } elseif ($rollingAvg > 0 && count($median) === 1) {
            $rollingAvgSeries = [
                ['x' => $median[0]['x'], 'y' => $rollingAvg],
            ];
        }

        // Threshold annotation lines (only when rolling avg is meaningful)
        $annotations = [];
        if ($rollingAvg > 0) {
            if ($item->buy_threshold > 0) {
                $annotations[] = [
                    'level' => (int) round($rollingAvg * (1 - $item->buy_threshold / 100)),
                    'type' => 'buy',
                ];
            }
            if ($item->sell_threshold > 0) {
                $annotations[] = [
                    'level' => (int) round($rollingAvg * (1 + $item->sell_threshold / 100)),
                    'type' => 'sell',
                ];
            }
        }

        $this->dispatch('chart-data-updated',
            median: $median,
            min: $min,
            rollingAvg: $rollingAvgSeries,
            annotations: $annotations,
        );
    }

    public function formatGold(int $copper): string
    {
        $g = intdiv($copper, 10000);
        $s = intdiv($copper % 10000, 100);
        $c = $copper % 100;

        $parts = [];
        if ($g > 0) {
            $parts[] = number_format($g).'g';
        }
        if ($s > 0) {
            $parts[] = $s.'s';
        }
        if ($c > 0 || $parts === []) {
            $parts[] = $c.'c';
        }

        return implode(' ', $parts);
    }

    public function trendDirection(WatchedItem $item): string
    {
        $snapshots = $item->priceSnapshots;

        if ($snapshots->count() < 2) {
            return 'flat';
        }

        $current = $snapshots->first()->median_price;
        $previous = $snapshots->last()->median_price;

        if ($current > $previous) {
            return 'up';
        }

        if ($current < $previous) {
            return 'down';
        }

        return 'flat';
    }

    public function trendPercent(WatchedItem $item): ?float
    {
        $snapshots = $item->priceSnapshots;

        if ($snapshots->count() < 2) {
            return null;
        }

        $current = $snapshots->first()->median_price;
        $previous = $snapshots->last()->median_price;

        if ($previous === 0) {
            return null;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    public function dataFreshness(): string
    {
        $latest = auth()->user()
            ->watchedItems()
            ->join('price_snapshots', 'watched_items.id', '=', 'price_snapshots.watched_item_id')
            ->max('price_snapshots.polled_at');

        if ($latest === null) {
            return 'Never';
        }

        return Carbon::parse($latest)->diffForHumans();
    }

    public function rollingSignal(WatchedItem $item): array
    {
        $sevenDayQuery = $item->priceSnapshots()
            ->where('polled_at', '>=', now()->subDays(7));

        $snapshotCount = $sevenDayQuery->count();

        if ($snapshotCount < 96) {
            return ['signal' => 'insufficient_data', 'magnitude' => 0.0, 'rollingAvg' => 0];
        }

        $rollingAvg = (int) round((float) (
            $item->priceSnapshots()
                ->where('polled_at', '>=', now()->subDays(7))
                ->avg('median_price') ?? 0
        ));

        if ($rollingAvg === 0) {
            return ['signal' => 'none', 'magnitude' => 0.0, 'rollingAvg' => 0];
        }

        $currentPrice = $item->priceSnapshots->first()?->median_price ?? 0;

        $buyLevel = (int) round($rollingAvg * (1 - $item->buy_threshold / 100));
        $sellLevel = (int) round($rollingAvg * (1 + $item->sell_threshold / 100));

        if ($currentPrice <= $buyLevel && $currentPrice > 0) {
            $magnitude = round((($rollingAvg - $currentPrice) / $rollingAvg) * 100, 1);
            return ['signal' => 'buy', 'magnitude' => $magnitude, 'rollingAvg' => $rollingAvg];
        }

        if ($currentPrice >= $sellLevel) {
            $magnitude = round((($currentPrice - $rollingAvg) / $rollingAvg) * 100, 1);
            return ['signal' => 'sell', 'magnitude' => $magnitude, 'rollingAvg' => $rollingAvg];
        }

        return ['signal' => 'none', 'magnitude' => 0.0, 'rollingAvg' => $rollingAvg];
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
                        <div
                            wire:key="card-{{ $item->id }}"
                            wire:click="selectItem({{ $item->id }})"
                            class="cursor-pointer rounded-lg border bg-wow-dark p-5 transition-colors hover:border-wow-gold/50
                                {{ $selectedItemId === $item->id ? 'ring-2 ring-wow-gold' : '' }}
                                {{ $sig['signal'] === 'buy' ? 'border-green-500/60' : ($sig['signal'] === 'sell' ? 'border-red-500/60' : 'border-gray-700/50') }}"
                        >
                            <div class="mb-3 flex items-start justify-between">
                                <div class="flex items-center gap-2">
                                    @if ($item->catalogItem?->icon_url)
                                        <img src="{{ $item->catalogItem->icon_url }}" alt="" class="h-8 w-8 rounded" loading="lazy" />
                                    @endif
                                    <h3 class="font-medium text-gray-100">{{ $item->catalogItem?->display_name ?? $item->name }}</h3>

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

                                @if ($item->priceSnapshots->isNotEmpty())
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

                            @if ($item->priceSnapshots->isEmpty())
                                <p class="text-sm italic text-gray-500">Awaiting first snapshot</p>
                            @else
                                @php
                                    $latestPrice = $item->priceSnapshots->first()->median_price;
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
                        </div>
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
                                    wire:click="selectItem({{ $item->id }})"
                                    class="cursor-pointer bg-wow-dark transition-colors hover:bg-gray-800/50
                                        {{ $selectedItemId === $item->id ? 'ring-2 ring-wow-gold ring-inset' : '' }}
                                        {{ $sig['signal'] === 'buy' ? 'border-l-2 border-l-green-500' : ($sig['signal'] === 'sell' ? 'border-l-2 border-l-red-500' : 'border-l-2 border-l-transparent') }}"
                                >
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-2">
                                            @if ($item->catalogItem?->icon_url)
                                                <img src="{{ $item->catalogItem->icon_url }}" alt="" class="h-6 w-6 rounded" loading="lazy" />
                                            @endif
                                            <span class="font-medium text-gray-100">{{ $item->catalogItem?->display_name ?? $item->name }}</span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        @if ($item->priceSnapshots->isEmpty())
                                            <span class="text-sm italic text-gray-500">—</span>
                                        @else
                                            @php
                                                $latestPrice = $item->priceSnapshots->first()->median_price;
                                                $g = intdiv($latestPrice, 10000);
                                                $s = intdiv($latestPrice % 10000, 100);
                                                $c = $latestPrice % 100;
                                            @endphp
                                            <span class="font-medium">
                                                @if ($g > 0)<span class="text-wow-gold">{{ number_format($g) }}g</span>@endif
                                                @if ($s > 0)<span class="text-gray-300">{{ $s }}s</span>@endif
                                                @if ($c > 0 || ($g === 0 && $s === 0))<span class="text-amber-700">{{ $c }}c</span>@endif
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        @if ($item->priceSnapshots->isNotEmpty())
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

    {{-- Chart Panel (sticky bottom) --}}
    <div
        x-show="$wire.selectedItemId !== null"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 translate-y-full"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 translate-y-full"
        class="fixed inset-x-0 bottom-0 z-40 border-t border-gray-700/50 bg-wow-dark p-4 shadow-2xl"
        style="display: none;"
        wire:ignore.self
    >
        <div class="mx-auto max-w-7xl">
            <div class="mb-3 flex items-center justify-between">
                <h3 class="font-medium text-gray-100" x-text="$wire.selectedItemId ? ({{ $this->watchedItems->pluck('name', 'id')->toJson() }})[$wire.selectedItemId] ?? '' : ''">
                </h3>
                <div class="flex items-center gap-3">
                    {{-- Timeframe Toggle --}}
                    <div class="flex overflow-hidden rounded-md border border-gray-600">
                        @foreach (['24h', '7d', '30d'] as $frame)
                            <button
                                wire:click="setTimeframe('{{ $frame }}')"
                                class="px-3 py-1 text-sm font-medium transition-colors"
                                :class="$wire.timeframe === '{{ $frame }}' ? 'bg-wow-gold text-wow-darker' : 'bg-gray-700 text-gray-300 hover:bg-gray-600'"
                            >
                                {{ $frame }}
                            </button>
                        @endforeach
                    </div>
                    {{-- Close button --}}
                    <button
                        wire:click="selectItem($wire.selectedItemId)"
                        class="rounded p-1 text-gray-400 transition-colors hover:text-gray-200"
                        title="Close chart"
                    >
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
            </div>
            <div id="price-chart" wire:ignore style="height: 250px;"></div>
        </div>
    </div>
</div>

@script
<script>
(function () {
    let chart = null;

    function formatGoldJs(copper) {
        if (copper === null || copper === undefined) return '\u2014';
        const g = Math.floor(copper / 10000);
        const s = Math.floor((copper % 10000) / 100);
        const c = copper % 100;
        const parts = [];
        if (g > 0) parts.push(g.toLocaleString() + 'g');
        if (s > 0) parts.push(s + 's');
        if (c > 0 || parts.length === 0) parts.push(c + 'c');
        return parts.join(' ');
    }

    $wire.$on('chart-data-updated', ({ median, min, rollingAvg, annotations }) => {
        // Build yaxis annotation lines for buy/sell thresholds
        const yaxisAnnotations = (annotations || []).map(a => ({
            y: a.level,
            borderColor: a.type === 'buy' ? '#22c55e' : '#ef4444',
            strokeDashArray: 6,
            label: {
                text: a.type === 'buy' ? 'Buy Threshold' : 'Sell Threshold',
                position: 'left',
                style: {
                    background: 'transparent',
                    color: a.type === 'buy' ? '#22c55e' : '#ef4444',
                    fontSize: '11px',
                },
            },
        }));

        const options = {
            series: [
                { name: 'Median',      data: median },
                { name: 'Min',         data: min },
                { name: '7d Avg',      data: rollingAvg || [] },
            ],
            annotations: { yaxis: yaxisAnnotations },
            chart: {
                type: 'line',
                height: 250,
                background: '#1a1a2e',
                toolbar: { show: false },
                animations: { enabled: true },
            },
            noData: {
                text: 'No price data for this timeframe',
                style: { color: '#9ca3af', fontSize: '14px' },
            },
            theme: { mode: 'dark' },
            colors: ['#f7a325', '#60a5fa', '#a78bfa'],
            stroke: {
                curve: 'smooth',
                width: [2, 2, 2],
                dashArray: [0, 0, 6],
            },
            markers: { size: 0 },
            xaxis: {
                type: 'datetime',
                labels: {
                    style: { colors: '#9ca3af' },
                    datetimeUTC: false,
                },
            },
            yaxis: {
                labels: {
                    style: { colors: '#9ca3af' },
                    formatter: (val) => Math.floor(val / 10000).toLocaleString() + 'g',
                },
            },
            tooltip: {
                theme: 'dark',
                custom: ({ series, seriesIndex, dataPointIndex, w }) => {
                    const medianVal = series[0] ? series[0][dataPointIndex] : null;
                    const minVal = series[1] ? series[1][dataPointIndex] : null;
                    const avgVal = series[2] ? series[2][dataPointIndex] : null;
                    const ts = w.globals.seriesX[0][dataPointIndex];
                    const date = new Date(ts);
                    const timeStr = date.toLocaleDateString() + ' ' + date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                    let html = '<div class="px-3 py-2 text-sm">'
                        + '<div class="text-gray-400 mb-1">' + timeStr + '</div>'
                        + '<div><strong>Median:</strong> ' + formatGoldJs(medianVal) + '</div>'
                        + '<div><strong>Min:</strong> ' + formatGoldJs(minVal) + '</div>';
                    if (avgVal !== null && avgVal !== undefined) {
                        html += '<div><strong>7d Avg:</strong> ' + formatGoldJs(avgVal) + '</div>';
                    }
                    html += '</div>';
                    return html;
                },
            },
            grid: { borderColor: '#374151' },
        };

        const el = document.querySelector('#price-chart');
        if (!el) return;

        if (chart !== null && !document.body.contains(chart.el)) {
            chart.destroy();
            chart = null;
        }

        if (chart === null) {
            chart = new ApexCharts(el, options);
            chart.render();
        } else {
            chart.updateOptions(options);
        }
    });
})();
</script>
@endscript
