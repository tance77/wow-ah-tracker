<!DOCTYPE html>
<html class="dark" lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'AH Tracker') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-gray-100 antialiased bg-wow-darker min-h-screen">
        <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0">
            <div class="mb-6">
                <a href="/" wire:navigate class="text-wow-gold text-2xl font-bold tracking-wide hover:text-wow-gold-light transition-colors">
                    AH Tracker
                </a>
            </div>

            <div class="w-full sm:max-w-md px-6 py-8 bg-wow-dark shadow-xl border border-gray-700/50 sm:rounded-xl overflow-hidden">
                {{ $slot }}
            </div>
        </div>
    </body>
</html>
