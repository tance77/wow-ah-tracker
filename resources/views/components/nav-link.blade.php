@props(['active'])

@php
$classes = ($active ?? false)
            ? 'inline-flex items-center px-1 pt-1 border-b-2 border-wow-gold text-sm font-medium leading-5 text-wow-gold focus:outline-none focus:border-wow-gold-light transition duration-150 ease-in-out'
            : 'inline-flex items-center px-1 pt-1 border-b-2 border-transparent text-sm font-medium leading-5 text-gray-400 hover:text-wow-gold hover:border-wow-gold focus:outline-none focus:text-wow-gold focus:border-wow-gold transition duration-150 ease-in-out';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
