<?php

declare(strict_types=1);

use App\Models\Profession;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public Profession $profession;

    public function mount(Profession $profession): void
    {
        $this->profession = $profession;
    }
}; ?>

<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-200 leading-tight">
            {{ $profession->name }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <p class="text-gray-400">Detail page coming soon</p>
        </div>
    </div>
</div>
