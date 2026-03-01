<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center px-4 py-2 bg-wow-gold border border-transparent rounded-md font-semibold text-xs text-wow-darker uppercase tracking-widest hover:bg-wow-gold-light focus:bg-wow-gold-light active:bg-wow-gold-dark focus:outline-none focus:ring-2 focus:ring-wow-gold focus:ring-offset-2 focus:ring-offset-wow-darker transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
