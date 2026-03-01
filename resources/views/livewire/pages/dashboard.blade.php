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

    public string $timeframe = '7d';

    #[Computed]
    public function watchedItems(): Collection
    {
        return auth()->user()->watchedItems()
            ->with(['priceSnapshots' => fn ($q) => $q->latest('polled_at')->limit(2)])
            ->orderBy('name')
            ->get();
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

        $snapshots = auth()->user()
            ->watchedItems()
            ->findOrFail($this->selectedItemId)
            ->priceSnapshots()
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

        $this->dispatch('chart-data-updated', median: $median, min: $min);
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
}; ?>

<x-slot name="header">
    <div class="flex items-center justify-between">
        <h2 class="text-xl font-semibold leading-tight text-wow-gold">
            {{ __('Dashboard') }}
        </h2>
        <span class="text-sm text-gray-400">Updated {{ $this->dataFreshness() }}</span>
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
            {{-- Card Grid --}}
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3" wire:loading.class="opacity-50">
                @foreach ($this->watchedItems as $item)
                    <div
                        wire:key="card-{{ $item->id }}"
                        wire:click="selectItem({{ $item->id }})"
                        class="cursor-pointer rounded-lg border border-gray-700/50 bg-wow-dark p-5 transition-colors hover:border-wow-gold/50 {{ $selectedItemId === $item->id ? 'ring-2 ring-wow-gold' : '' }}"
                    >
                        <div class="mb-3 flex items-start justify-between">
                            <h3 class="font-medium text-gray-100">{{ $item->name }}</h3>
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
                            <p class="text-sm text-gray-500 italic">Awaiting first snapshot</p>
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

            {{-- Chart Panel --}}
            <div
                x-show="$wire.selectedItemId !== null"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 translate-y-2"
                x-transition:enter-end="opacity-100 translate-y-0"
                class="mt-6 rounded-lg border border-gray-700/50 bg-wow-dark p-6"
                style="display: none;"
                wire:ignore.self
            >
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="font-medium text-gray-100" x-text="$wire.selectedItemId ? '{{ $this->watchedItems->pluck('name', 'id')->toJson() }}'[$wire.selectedItemId] ?? '' : ''">
                    </h3>
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
                </div>
                <div id="price-chart" wire:ignore></div>
            </div>
        @endif

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

    $wire.$on('chart-data-updated', ({ median, min }) => {
        const options = {
            series: [
                { name: 'Median', data: median },
                { name: 'Min',    data: min },
            ],
            chart: {
                type: 'line',
                height: 300,
                background: '#1a1a2e',
                toolbar: { show: false },
                animations: { enabled: true },
            },
            noData: {
                text: 'No price data for this timeframe',
                style: { color: '#9ca3af', fontSize: '14px' },
            },
            theme: { mode: 'dark' },
            colors: ['#f7a325', '#60a5fa'],
            stroke: { curve: 'smooth', width: 2 },
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
                    const ts = w.globals.seriesX[0][dataPointIndex];
                    const date = new Date(ts);
                    const timeStr = date.toLocaleDateString() + ' ' + date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                    return '<div class="px-3 py-2 text-sm">'
                        + '<div class="text-gray-400 mb-1">' + timeStr + '</div>'
                        + '<div><strong>Median:</strong> ' + formatGoldJs(medianVal) + '</div>'
                        + '<div><strong>Min:</strong> ' + formatGoldJs(minVal) + '</div>'
                        + '</div>';
                },
            },
            grid: { borderColor: '#374151' },
        };

        const el = document.querySelector('#price-chart');
        if (!el) return;

        // Livewire re-renders replace the DOM element, orphaning the old chart instance
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
