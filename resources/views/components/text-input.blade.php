@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'border-gray-600 bg-wow-darker text-gray-100 placeholder-gray-500 focus:border-wow-gold focus:ring-wow-gold rounded-md shadow-sm']) }}>
