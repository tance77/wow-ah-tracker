<?php

declare(strict_types=1);

use App\Models\Shuffle;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public Shuffle $shuffle;

    public function mount(Shuffle $shuffle): void
    {
        abort_unless(auth()->id() === $shuffle->user_id, 403);
        $this->shuffle = $shuffle;
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
        <div class="overflow-hidden bg-wow-dark p-8 shadow-sm sm:rounded-lg">
            <p class="text-center text-gray-500">Step editor coming in Phase 11.</p>
        </div>
    </div>
</div>
