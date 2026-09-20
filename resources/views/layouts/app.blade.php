<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">

        <title>{{ $title ?? config('app.name') }}</title>

        <tallstackui:script />
        @livewireStyles
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="h-full bg-app-bg font-sans text-text-primary antialiased">
        <div class="flex h-full" x-data="{ sidebarOpen: false }">
            <x-layouts.sidebar />

            <div class="flex min-w-0 flex-1 flex-col">
                <x-layouts.mobile-header />

                <main class="flex-1 overflow-y-auto">
                    {{ $slot }}
                </main>
            </div>
        </div>

        <x-toast />
        <x-dialog />
        <x-banner />

        @livewireScripts
    </body>
</html>