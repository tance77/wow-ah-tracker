@props(['active'])

@php
$classes = ($active ?? false)
            ? 'block w-full ps-3 pe-4 py-2 border-l-4 border-wow-gold text-start text-base font-medium text-wow-gold bg-wow-darker focus:outline-none focus:text-wow-gold-light focus:bg-wow-darker focus:border-wow-gold-light transition duration-150 ease-in-out'
            : 'block w-full ps-3 pe-4 py-2 border-l-4 border-transparent text-start text-base font-medium text-gray-400 hover:text-wow-gold hover:bg-wow-darker hover:border-wow-gold focus:outline-none focus:text-wow-gold focus:bg-wow-darker focus:border-wow-gold transition duration-150 ease-in-out';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
