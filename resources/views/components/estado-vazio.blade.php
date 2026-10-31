@props([
    'icone' => 'busca',
    'titulo',
    'texto' => null,
])

<div {{ $attributes->merge(['class' => 'flex flex-col items-center gap-3 rounded-card border border-dashed border-borda bg-superficie px-6 py-12 text-center']) }}>
    <span class="flex size-12 items-center justify-center rounded-full bg-primaria-50 text-primaria-600">
        <x-icone :nome="$icone" class="size-6" />
    </span>
    <p class="text-lg font-bold text-texto">{{ $titulo }}</p>
    @if ($texto)
        <p class="max-w-md text-texto-suave">{{ $texto }}</p>
    @endif
    @if ($slot->isNotEmpty())
        <div class="mt-2 flex flex-wrap justify-center gap-3">{{ $slot }}</div>
    @endif
</div>
