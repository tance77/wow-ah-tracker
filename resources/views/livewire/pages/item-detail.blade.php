<?php

declare(strict_types=1);

use App\Concerns\FormatsAuctionData;
use App\Models\WatchedItem;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    use FormatsAuctionData;

    public WatchedItem $watchedItem;

    public string $timeframe = '7d';

    public function mount(): void
    {
        abort_unless($this->watchedItem->user_id === auth()->id(), 403);

        $this->watchedItem->load([
            'catalogItem' => fn ($q) => $q->with(['priceSnapshots' => fn ($q2) => $q2->latest('polled_at')->limit(2)]),
            'catalogItem:blizzard_item_id,id,name,icon_url,quality_tier,rarity,category',
        ]);

        $this->loadChart();
    }

    #[Computed]
    public function stats(): array
    {
        $now = now();
        $sevenDaySnapshots = $this->watchedItem->priceSnapshots()
            ->where('polled_at', '>=', $now->copy()->subDays(7))
            ->get();

        $thirtyDaySnapshots = $this->watchedItem->priceSnapshots()
            ->where('polled_at', '>=', $now->copy()->subDays(30))
            ->get();

        $twentyFourHourSnapshots = $this->watchedItem->priceSnapshots()
            ->where('polled_at', '>=', $now->copy()->subHours(24))
            ->get();

        $latest = $this->watchedItem->priceSnapshots->first();
        $currentMedian = $latest?->median_price ?? 0;
        $currentMin = $latest?->min_price ?? 0;
        $currentVolume = $latest?->total_volume ?? 0;

        $sevenDayAvg = (int) round((float) ($sevenDaySnapshots->avg('median_price') ?? 0));
        $thirtyDayAvg = (int) round((float) ($thirtyDaySnapshots->avg('median_price') ?? 0));

        $sevenDayMedians = $sevenDaySnapshots->pluck('median_price')->filter();
        $volatility = null;
        if ($sevenDayMedians->count() > 1 && $sevenDayAvg > 0) {
            $variance = $sevenDayMedians->map(fn ($p) => pow($p - $sevenDayAvg, 2))->avg();
            $volatility = round((sqrt($variance) / $sevenDayAvg) * 100, 1);
        }

        $sevenDayLow = $sevenDaySnapshots->min('min_price');
        $sevenDayHigh = $sevenDaySnapshots->max('median_price');
        $thirtyDayLow = $thirtyDaySnapshots->min('min_price');
        $thirtyDayHigh = $thirtyDaySnapshots->max('median_price');

        $signal = $this->rollingSignal($this->watchedItem);
        $rollingAvg = $signal['rollingAvg'];
        $distanceToBuy = null;
        $distanceToSell = null;
        if ($rollingAvg > 0 && $currentMedian > 0) {
            $buyLevel = (int) round($rollingAvg * (1 - $this->watchedItem->buy_threshold / 100));
            $sellLevel = (int) round($rollingAvg * (1 + $this->watchedItem->sell_threshold / 100));
            $distanceToBuy = $currentMedian - $buyLevel;
            $distanceToSell = $sellLevel - $currentMedian;
        }

        $sevenDayAvgVolume = (int) round((float) ($sevenDaySnapshots->avg('total_volume') ?? 0));
        $thirtyDayAvgVolume = (int) round((float) ($thirtyDaySnapshots->avg('total_volume') ?? 0));

        $twentyFourHourVolumes = $twentyFourHourSnapshots->pluck('total_volume')->filter();
        $olderDaySnapshots = $this->watchedItem->priceSnapshots()
            ->where('polled_at', '>=', $now->copy()->subHours(48))
            ->where('polled_at', '<', $now->copy()->subHours(24))
            ->get();
        $olderAvgVolume = (float) ($olderDaySnapshots->avg('total_volume') ?? 0);
        $currentAvgVolume = (float) ($twentyFourHourVolumes->avg() ?? 0);
        $volumeChange = null;
        if ($olderAvgVolume > 0) {
            $volumeChange = round((($currentAvgVolume - $olderAvgVolume) / $olderAvgVolume) * 100, 1);
        }

        return [
            'currentMedian' => $currentMedian,
            'currentMin' => $currentMin,
            'sevenDayAvg' => $sevenDayAvg,
            'volatility' => $volatility,
            'sevenDayLow' => $sevenDayLow,
            'sevenDayHigh' => $sevenDayHigh,
            'thirtyDayLow' => $thirtyDayLow,
            'thirtyDayHigh' => $thirtyDayHigh,
            'distanceToBuy' => $distanceToBuy,
            'distanceToSell' => $distanceToSell,
            'currentVolume' => $currentVolume,
            'sevenDayAvgVolume' => $sevenDayAvgVolume,
            'volumeChange' => $volumeChange,
            'thirtyDayAvgVolume' => $thirtyDayAvgVolume,
            'polledAt' => $latest?->polled_at,
        ];
    }

    public function setTimeframe(string $frame): void
    {
        $this->timeframe = $frame;
        $this->loadChart();
    }

    public function updateThreshold(string $field, int $value): void
    {
        if (! in_array($field, ['buy_threshold', 'sell_threshold'], true)) {
            return;
        }

        $value = max(1, min(100, $value));
        $this->watchedItem->update([$field => $value]);
        $this->watchedItem->refresh();
        unset($this->stats);
        $this->loadChart();
    }

    private function loadChart(): void
    {
        $cutoff = match ($this->timeframe) {
            '24h' => now()->subHours(24),
            '30d' => now()->subDays(30),
            default => now()->subDays(7),
        };

        $snapshots = $this->watchedItem->priceSnapshots()
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

        $rollingAvg = (int) round((float) (
            $this->watchedItem->priceSnapshots()
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

        $annotations = [];
        if ($rollingAvg > 0) {
            if ($this->watchedItem->buy_threshold > 0) {
                $annotations[] = [
                    'level' => (int) round($rollingAvg * (1 - $this->watchedItem->buy_threshold / 100)),
                    'type' => 'buy',
                ];
            }
            if ($this->watchedItem->sell_threshold > 0) {
                $annotations[] = [
                    'level' => (int) round($rollingAvg * (1 + $this->watchedItem->sell_threshold / 100)),
                    'type' => 'sell',
                ];
            }
        }

        $this->dispatch('chart-data-updated',
            median: $median,
            min: $min,
            rollingAvg: $rollingAvgSeries,
            annotations: $annotations,
            timeframe: $this->timeframe,
        );
    }
}; ?>

<x-slot name="header">
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            <a
                href="{{ route('dashboard') }}"
                wire:navigate
                class="text-gray-400 transition-colors hover:text-wow-gold"
            >
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <div class="flex items-center gap-3">
                @if ($watchedItem->catalogItem?->icon_url)
                    <img src="{{ $watchedItem->catalogItem->icon_url }}" alt="" class="h-12 w-12 rounded" loading="lazy" />
                @endif
                <div>
                    <h2 class="flex items-center gap-2 text-xl font-semibold leading-tight {{ $watchedItem->catalogItem?->rarityColorClass() ?? 'text-wow-gold' }}">
                        {{ $watchedItem->catalogItem?->name ?? $watchedItem->name }}
                        <x-tier-pip :tier="$watchedItem->catalogItem?->quality_tier" size="md" />
                    </h2>
                    <div class="flex items-center gap-2 text-sm text-gray-400">
                        @if ($watchedItem->catalogItem?->category)
                            <span class="rounded bg-gray-700/50 px-1.5 py-0.5 text-xs">{{ $watchedItem->catalogItem->category }}</span>
                        @endif
                        <span>ID: {{ $watchedItem->blizzard_item_id }}</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="text-right">
            @if ($watchedItem->catalogItem?->priceSnapshots?->isNotEmpty())
                @php
                    $latestPrice = $watchedItem->catalogItem->priceSnapshots->first()->median_price;
                    $g = intdiv($latestPrice, 10000);
                    $s = intdiv($latestPrice % 10000, 100);
                    $c = $latestPrice % 100;
                @endphp
                <div class="text-2xl font-bold">
                    @if ($g > 0)<span class="text-wow-gold">{{ number_format($g) }}g</span>@endif
                    @if ($s > 0)<span class="text-gray-300">{{ $s }}s</span>@endif
                    @if ($c > 0 || ($g === 0 && $s === 0))<span class="text-amber-700">{{ $c }}c</span>@endif
                </div>
            @endif
            @if ($this->stats['polledAt'])
                <span class="text-xs text-gray-500">{{ $this->stats['polledAt']->diffForHumans() }}</span>
            @endif
        </div>
    </div>
</x-slot>

<div class="py-8">
    <div class="mx-auto max-w-7xl sm:px-6 lg:px-8 space-y-6">

        {{-- Signal Status Bar --}}
        @php $signal = $this->rollingSignal($watchedItem); @endphp
        <div class="rounded-lg px-5 py-3 font-semibold
            {{ $signal['signal'] === 'buy' ? 'bg-green-500/15 text-green-400 ring-1 ring-green-500/30' : '' }}
            {{ $signal['signal'] === 'sell' ? 'bg-red-500/15 text-red-400 ring-1 ring-red-500/30' : '' }}
            {{ $signal['signal'] === 'none' ? 'bg-gray-700/30 text-gray-400 ring-1 ring-gray-600/30' : '' }}
            {{ $signal['signal'] === 'insufficient_data' ? 'bg-amber-500/10 text-amber-400 ring-1 ring-amber-500/30' : '' }}
        ">
            @if ($signal['signal'] === 'buy')
                <div class="flex items-center justify-between">
                    <span class="signal-pulse-buy">BUY SIGNAL -{{ $signal['magnitude'] }}%</span>
                    <span class="text-sm font-normal text-green-400/70">
                        Price {{ $this->formatGold($this->stats['currentMedian']) }} vs
                        7d avg {{ $this->formatGold($signal['rollingAvg']) }}
                        (threshold {{ $watchedItem->buy_threshold }}%)
                    </span>
                </div>
            @elseif ($signal['signal'] === 'sell')
                <div class="flex items-center justify-between">
                    <span class="signal-pulse-sell">SELL SIGNAL +{{ $signal['magnitude'] }}%</span>
                    <span class="text-sm font-normal text-red-400/70">
                        Price {{ $this->formatGold($this->stats['currentMedian']) }} vs
                        7d avg {{ $this->formatGold($signal['rollingAvg']) }}
                        (threshold {{ $watchedItem->sell_threshold }}%)
                    </span>
                </div>
            @elseif ($signal['signal'] === 'insufficient_data')
                Collecting data...
            @else
                No active signals
            @endif
        </div>

        {{-- Key Stats Grid --}}
        @php $stats = $this->stats; @endphp
        <div class="space-y-2">
            {{-- Price Row --}}
            <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                <div class="rounded-lg border border-gray-700/50 bg-wow-dark p-4">
                    <div class="text-xs font-medium uppercase tracking-wider text-gray-500">Current Median</div>
                    <div class="mt-1 text-lg font-semibold text-gray-100">{{ $stats['currentMedian'] ? $this->formatGold($stats['currentMedian']) : '—' }}</div>
                </div>
                <div class="rounded-lg border border-gray-700/50 bg-wow-dark p-4">
                    <div class="text-xs font-medium uppercase tracking-wider text-gray-500">Current Min</div>
                    <div class="mt-1 text-lg font-semibold text-gray-100">{{ $stats['currentMin'] ? $this->formatGold($stats['currentMin']) : '—' }}</div>
                </div>
                <div class="rounded-lg border border-gray-700/50 bg-wow-dark p-4">
                    <div class="text-xs font-medium uppercase tracking-wider text-gray-500">7-Day Average</div>
                    <div class="mt-1 text-lg font-semibold text-gray-100">{{ $stats['sevenDayAvg'] ? $this->formatGold($stats['sevenDayAvg']) : '—' }}</div>
                </div>
                <div class="rounded-lg border border-gray-700/50 bg-wow-dark p-4">
                    <div class="text-xs font-medium uppercase tracking-wider text-gray-500">7d Volatility</div>
                    <div class="mt-1 text-lg font-semibold {{ $stats['volatility'] !== null ? ($stats['volatility'] < 5 ? 'text-green-400' : ($stats['volatility'] <= 15 ? 'text-yellow-400' : 'text-red-400')) : 'text-gray-100' }}">
                        {{ $stats['volatility'] !== null ? $stats['volatility'].'%' : '—' }}
                    </div>
                </div>
            </div>

            {{-- Range Row --}}
            <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                <div class="rounded-lg border border-gray-700/50 bg-wow-dark p-4">
                    <div class="text-xs font-medium uppercase tracking-wider text-gray-500">7-Day Low / High</div>
                    <div class="mt-1 text-sm font-semibold text-gray-100">
                        @if ($stats['sevenDayLow'] && $stats['sevenDayHigh'])
                            {{ $this->formatGold($stats['sevenDayLow']) }} / {{ $this->formatGold($stats['sevenDayHigh']) }}
                        @else
                            —
                        @endif
                    </div>
                </div>
                <div class="rounded-lg border border-gray-700/50 bg-wow-dark p-4">
                    <div class="text-xs font-medium uppercase tracking-wider text-gray-500">30-Day Low / High</div>
                    <div class="mt-1 text-sm font-semibold text-gray-100">
                        @if ($stats['thirtyDayLow'] && $stats['thirtyDayHigh'])
                            {{ $this->formatGold($stats['thirtyDayLow']) }} / {{ $this->formatGold($stats['thirtyDayHigh']) }}
                        @else
                            —
                        @endif
                    </div>
                </div>
                <div class="rounded-lg border border-gray-700/50 bg-wow-dark p-4">
                    <div class="text-xs font-medium uppercase tracking-wider text-gray-500">Distance to Buy</div>
                    <div class="mt-1 text-lg font-semibold text-green-400">
                        {{ $stats['distanceToBuy'] !== null ? $this->formatGold($stats['distanceToBuy']) : '—' }}
                    </div>
                </div>
                <div class="rounded-lg border border-gray-700/50 bg-wow-dark p-4">
                    <div class="text-xs font-medium uppercase tracking-wider text-gray-500">Distance to Sell</div>
                    <div class="mt-1 text-lg font-semibold text-red-400">
                        {{ $stats['distanceToSell'] !== null ? $this->formatGold($stats['distanceToSell']) : '—' }}
                    </div>
                </div>
            </div>

            {{-- Volume Row --}}
            <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                <div class="rounded-lg border border-gray-700/50 bg-wow-dark p-4">
                    <div class="text-xs font-medium uppercase tracking-wider text-gray-500">Current Volume</div>
                    <div class="mt-1 text-lg font-semibold text-gray-100">{{ $stats['currentVolume'] ? number_format($stats['currentVolume']) : '—' }}</div>
                </div>
                <div class="rounded-lg border border-gray-700/50 bg-wow-dark p-4">
                    <div class="text-xs font-medium uppercase tracking-wider text-gray-500">7-Day Avg Volume</div>
                    <div class="mt-1 text-lg font-semibold text-gray-100">{{ $stats['sevenDayAvgVolume'] ? number_format($stats['sevenDayAvgVolume']) : '—' }}</div>
                </div>
                <div class="rounded-lg border border-gray-700/50 bg-wow-dark p-4">
                    <div class="text-xs font-medium uppercase tracking-wider text-gray-500">24h Volume Change</div>
                    <div class="mt-1 text-lg font-semibold {{ $stats['volumeChange'] !== null ? ($stats['volumeChange'] >= 0 ? 'text-green-400' : 'text-red-400') : 'text-gray-100' }}">
                        {{ $stats['volumeChange'] !== null ? ($stats['volumeChange'] >= 0 ? '+' : '').$stats['volumeChange'].'%' : '—' }}
                    </div>
                </div>
                <div class="rounded-lg border border-gray-700/50 bg-wow-dark p-4">
                    <div class="text-xs font-medium uppercase tracking-wider text-gray-500">30-Day Avg Volume</div>
                    <div class="mt-1 text-lg font-semibold text-gray-100">{{ $stats['thirtyDayAvgVolume'] ? number_format($stats['thirtyDayAvgVolume']) : '—' }}</div>
                </div>
            </div>
        </div>

        {{-- Price Chart --}}
        <div class="rounded-lg border border-gray-700/50 bg-wow-dark p-5">
            <div class="mb-4 flex items-center justify-between">
                <h3 class="font-medium text-gray-100">Price History</h3>
                <div class="flex overflow-hidden rounded-md border border-gray-600">
                    @foreach (['24h', '7d', '30d'] as $frame)
                        <button
                            wire:click="setTimeframe('{{ $frame }}')"
                            class="px-3 py-1 text-sm font-medium transition-colors {{ $timeframe === $frame ? 'bg-wow-gold text-wow-darker' : 'bg-gray-700 text-gray-300 hover:bg-gray-600' }}"
                        >
                            {{ $frame }}
                        </button>
                    @endforeach
                </div>
            </div>
            <div id="price-chart" wire:ignore style="height: 450px;"></div>
        </div>

        {{-- Threshold Configuration --}}
        <div class="rounded-lg border border-gray-700/50 bg-wow-dark p-5">
            <h3 class="mb-4 font-medium text-gray-100">Threshold Configuration</h3>
            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                {{-- Buy Threshold --}}
                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <label class="text-sm font-medium text-gray-400">Buy Threshold</label>
                        <div
                            class="inline-flex items-center gap-1"
                            x-data="{ editing: false, saved: false }"
                            x-init="$watch('editing', v => v && $nextTick(() => $refs.buyInput.focus()))"
                        >
                            <span
                                x-show="!editing"
                                @click="editing = true"
                                class="cursor-pointer rounded px-2 py-1 text-lg font-semibold text-green-400 hover:bg-gray-700/50"
                            >{{ $watchedItem->buy_threshold }}%</span>
                            <input
                                type="number"
                                min="1"
                                max="100"
                                x-show="editing"
                                x-ref="buyInput"
                                value="{{ $watchedItem->buy_threshold }}"
                                wire:change="updateThreshold('buy_threshold', $event.target.value)"
                                @blur="editing = false; saved = true; setTimeout(() => saved = false, 1000)"
                                @keydown.enter="$el.blur()"
                                @keydown.escape="editing = false"
                                class="w-20 rounded border border-gray-600 bg-wow-darker px-2 py-1 text-center text-lg font-semibold text-green-400 focus:border-wow-gold focus:outline-none focus:ring-1 focus:ring-wow-gold"
                            />
                            <span x-show="saved" x-transition class="ml-1 text-xs text-green-400">Saved</span>
                        </div>
                    </div>
                    @if ($signal['rollingAvg'] > 0)
                        <div class="text-sm text-gray-500">
                            Buy level: {{ $this->formatGold((int) round($signal['rollingAvg'] * (1 - $watchedItem->buy_threshold / 100))) }}
                            <span class="text-gray-600">({{ $watchedItem->buy_threshold }}% below 7d avg {{ $this->formatGold($signal['rollingAvg']) }})</span>
                        </div>
                    @endif
                </div>

                {{-- Sell Threshold --}}
                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <label class="text-sm font-medium text-gray-400">Sell Threshold</label>
                        <div
                            class="inline-flex items-center gap-1"
                            x-data="{ editing: false, saved: false }"
                            x-init="$watch('editing', v => v && $nextTick(() => $refs.sellInput.focus()))"
                        >
                            <span
                                x-show="!editing"
                                @click="editing = true"
                                class="cursor-pointer rounded px-2 py-1 text-lg font-semibold text-red-400 hover:bg-gray-700/50"
                            >{{ $watchedItem->sell_threshold }}%</span>
                            <input
                                type="number"
                                min="1"
                                max="100"
                                x-show="editing"
                                x-ref="sellInput"
                                value="{{ $watchedItem->sell_threshold }}"
                                wire:change="updateThreshold('sell_threshold', $event.target.value)"
                                @blur="editing = false; saved = true; setTimeout(() => saved = false, 1000)"
                                @keydown.enter="$el.blur()"
                                @keydown.escape="editing = false"
                                class="w-20 rounded border border-gray-600 bg-wow-darker px-2 py-1 text-center text-lg font-semibold text-red-400 focus:border-wow-gold focus:outline-none focus:ring-1 focus:ring-wow-gold"
                            />
                            <span x-show="saved" x-transition class="ml-1 text-xs text-green-400">Saved</span>
                        </div>
                    </div>
                    @if ($signal['rollingAvg'] > 0)
                        <div class="text-sm text-gray-500">
                            Sell level: {{ $this->formatGold((int) round($signal['rollingAvg'] * (1 + $watchedItem->sell_threshold / 100))) }}
                            <span class="text-gray-600">({{ $watchedItem->sell_threshold }}% above 7d avg {{ $this->formatGold($signal['rollingAvg']) }})</span>
                        </div>
                    @endif
                </div>
            </div>
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

    $wire.$on('chart-data-updated', ({ median, min, rollingAvg, annotations, timeframe }) => {
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
                height: 450,
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
                tickAmount: timeframe === '24h' ? 24 : (timeframe === '30d' ? 30 : 7),
                labels: {
                    style: { colors: '#9ca3af' },
                    datetimeUTC: false,
                    format: timeframe === '24h' ? 'HH:mm' : (timeframe === '30d' ? 'MMM dd' : 'MMM dd'),
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
            chart.updateOptions(options, true, true);
        }
    });
})();
</script>
@endscript
