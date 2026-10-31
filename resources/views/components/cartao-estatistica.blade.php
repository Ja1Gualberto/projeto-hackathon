@props([
    'rotulo',
    'valor',
    'detalhe' => null,
    'icone' => null,
])

<div {{ $attributes->merge(['class' => 'flex items-start gap-4 rounded-card bg-superficie p-6 shadow-sm ring-1 ring-borda']) }}>
    @if ($icone)
        <span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-primaria-50 text-primaria-700">
            <x-icone :nome="$icone" class="size-6" />
        </span>
    @endif
    <div class="flex flex-col gap-1">
        <p class="text-sm font-semibold text-texto-suave">{{ $rotulo }}</p>
        <p class="text-4xl font-bold tabular-nums text-texto">{{ $valor }}</p>
        @if ($detalhe)
            <p class="text-sm text-texto-suave">{{ $detalhe }}</p>
        @endif
    </div>
</div>
