<div class="border-t border-border p-3">
    <div class="flex items-center gap-3 rounded-lg px-2 py-2">
        <div class="flex size-8 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-warning to-[#a94e21] text-xs font-bold text-text-primary">
            {{ Illuminate\Support\Str::of(auth()->user()->name)->substr(0, 2)->upper() }}
        </div>
        <div class="min-w-0 flex-1 leading-tight">
            <p class="truncate text-sm font-medium text-text-primary">{{ auth()->user()->name }}</p>
            <p class="truncate text-xs text-text-tertiary">{{ auth()->user()->roles->first()?->name ?? '—' }}</p>
        </div>
    </div>

    <form method="POST" action="{{ route('logout') }}" class="mt-1">
        @csrf
        <button type="submit" class="flex w-full items-center gap-3 rounded-lg px-2 py-2 text-sm text-text-secondary transition hover:bg-surface hover:text-text-primary">
            <x-icon name="arrow-left-start-on-rectangle" sm />
            Cerrar sesión
        </button>
    </form>
</div>