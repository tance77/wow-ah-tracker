@props(['tier', 'size' => 'sm'])

@php
    $svgClass = $size === 'sm' ? 'h-3 w-3' : 'h-3.5 w-3.5';
    $textClass = $size === 'sm' ? 'text-[10px]' : 'text-xs';
@endphp

@if ($tier)
    <span class="inline-flex items-center gap-0.5 rounded bg-gray-700/50 px-1 py-0.5 {{ $textClass }} font-medium">
        @switch($tier)
            @case(1)
                <svg class="{{ $svgClass }}" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="10" cy="10" r="7" fill="#CD7F32"/>
                    <circle cx="10" cy="10" r="7" fill="url(#t1grad-{{ $tier }})"/>
                    <circle cx="10" cy="10" r="5.5" stroke="#A0522D" stroke-width="0.5" stroke-opacity="0.5"/>
                    <circle cx="8.5" cy="8" r="2" fill="#DFA06E" opacity="0.5"/>
                    <defs><radialGradient id="t1grad-{{ $tier }}" cx="0.35" cy="0.35" r="0.65"><stop stop-color="#E8A862" stop-opacity="0.6"/><stop offset="1" stop-color="#8B4513" stop-opacity="0.4"/></radialGradient></defs>
                </svg>
                <span class="text-amber-600">T{{ $tier }}</span>
                @break
            @case(2)
                <svg class="{{ $svgClass }}" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="10" cy="10" r="7" fill="#C0C0C0"/>
                    <circle cx="10" cy="10" r="7" fill="url(#t2grad-{{ $tier }})"/>
                    <circle cx="10" cy="10" r="5.5" stroke="#808080" stroke-width="0.5" stroke-opacity="0.5"/>
                    <circle cx="8.5" cy="8" r="2" fill="#E8E8E8" opacity="0.5"/>
                    <defs><radialGradient id="t2grad-{{ $tier }}" cx="0.35" cy="0.35" r="0.65"><stop stop-color="#E8E8E8" stop-opacity="0.6"/><stop offset="1" stop-color="#808080" stop-opacity="0.4"/></radialGradient></defs>
                </svg>
                <span class="text-gray-300">T{{ $tier }}</span>
                @break
            @case(3)
                <svg class="{{ $svgClass }}" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="10" cy="10" r="7" fill="#FFD700"/>
                    <circle cx="10" cy="10" r="7" fill="url(#t3grad-{{ $tier }})"/>
                    <circle cx="10" cy="10" r="5.5" stroke="#DAA520" stroke-width="0.5" stroke-opacity="0.5"/>
                    <circle cx="8.5" cy="8" r="2" fill="#FFF3A0" opacity="0.5"/>
                    <defs><radialGradient id="t3grad-{{ $tier }}" cx="0.35" cy="0.35" r="0.65"><stop stop-color="#FFF3A0" stop-opacity="0.6"/><stop offset="1" stop-color="#B8860B" stop-opacity="0.4"/></radialGradient></defs>
                </svg>
                <span class="text-wow-gold">T{{ $tier }}</span>
                @break
        @endswitch
    </span>
@endif
