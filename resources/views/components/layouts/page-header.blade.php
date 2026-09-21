@props(['establecimiento', 'title'])

<header class="flex flex-wrap items-center justify-between gap-4 border-b border-border px-6 py-5 md:px-8">
    <div>
        <h1 class="text-xl font-bold text-text-primary">{{ $title }}</h1>
        <p class="text-sm text-text-secondary">
            {{ $establecimiento?->nombre ?? 'Aguape' }}
            @if ($establecimiento?->ubicacion)
                · {{ $establecimiento->ubicacion }}
            @endif
        </p>
    </div>

    @isset($actions)
        <div class="flex items-center gap-3">
            {{ $actions }}
        </div>
    @endisset
</header>