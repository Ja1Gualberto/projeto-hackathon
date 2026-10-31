@props([
    'titulo',
    'subtitulo' => null,
])

<header {{ $attributes->merge(['class' => 'flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between']) }}>
    <div class="flex flex-col gap-1">
        <h1 class="text-3xl font-bold tracking-tight text-texto">{{ $titulo }}</h1>
        @if ($subtitulo)
            <p class="text-texto-suave">{{ $subtitulo }}</p>
        @endif
    </div>
    @if ($slot->isNotEmpty())
        <div class="flex flex-wrap gap-3">{{ $slot }}</div>
    @endif
</header>
