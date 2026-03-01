<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-wow-gold">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            <div class="overflow-hidden bg-wow-dark shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-100">
                    <p class="text-lg">
                        You're tracking
                        <span class="font-semibold text-wow-gold">{{ auth()->user()->watchedItems()->count() }}</span>
                        {{ Str::plural('item', auth()->user()->watchedItems()->count()) }}.
                    </p>
                    <a href="{{ route('watchlist') }}" wire:navigate class="mt-2 inline-block text-wow-gold-light hover:text-wow-gold underline text-sm">
                        Manage your watchlist &rarr;
                    </a>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
