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
        <div class="grid h-full md:grid-cols-2">
            <div
                class="relative hidden flex-col items-center justify-center gap-4 overflow-hidden bg-[#1a1a2e] p-14 md:flex"
                style="background-image: repeating-linear-gradient(115deg, rgba(0,180,216,0.06) 0 1px, transparent 1px 26px);"
            >
                <div class="flex size-19 items-center justify-center rounded-2xl bg-gradient-to-br from-success to-accent text-[28px] font-extrabold text-accent-text">
                    AG
                </div>
                <div class="text-center leading-tight">
                    <p class="text-[32px] font-extrabold tracking-wide text-text-primary">Aguape</p>
                    <p class="mt-1.5 text-[13px] tracking-wider text-text-secondary uppercase">Monitoreo IoT · Estancia Aguape</p>
                </div>
            </div>

            <div class="flex items-center justify-center p-8">
                <div class="w-full max-w-sm">
                    {{ $slot }}
                </div>
            </div>
        </div>

        <x-toast />
        <x-dialog />
        <x-banner />

        @livewireScripts
    </body>
</html>