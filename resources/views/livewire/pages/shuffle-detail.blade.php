<?php

declare(strict_types=1);

use App\Concerns\FormatsAuctionData;
use App\Models\Shuffle;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    use FormatsAuctionData;

    public Shuffle $shuffle;

    public function mount(Shuffle $shuffle): void
    {
        abort_unless($shuffle->user_id === auth()->id(), 403);
        $this->shuffle = $shuffle;
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

            <!-- Step Editor Placeholder -->
            <div class="overflow-hidden bg-wow-dark shadow-sm sm:rounded-lg">
                <div class="border-b border-gray-700/50 px-6 py-4">
                    <h3 class="font-medium text-gray-100">Steps</h3>
                </div>
                <div class="flex flex-col items-center justify-center px-6 py-16 text-center">
                    <div class="mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-gray-700/50">
                        <svg class="h-6 w-6 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7" />
                        </svg>
                    </div>
                    <p class="text-sm font-medium text-gray-400">Step editor coming soon</p>
                    <p class="mt-1 text-xs text-gray-600">Steps will be configurable in a future update.</p>
                </div>
            </div>

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
