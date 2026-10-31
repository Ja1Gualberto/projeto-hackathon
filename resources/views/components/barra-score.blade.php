@props(['percentual'])

@php
    $percentual = max(0, min(100, (int) $percentual));

    [$corBarra, $corTexto] = match (true) {
        $percentual >= 70 => ['bg-score-alto', 'text-score-alto'],
        $percentual >= 40 => ['bg-score-medio', 'text-score-medio'],
        default => ['bg-score-baixo', 'text-score-baixo'],
    };
@endphp

<div role="meter" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $percentual }}" aria-label="Aderência de {{ $percentual }}%" {{ $attributes->merge(['class' => 'flex items-center gap-3']) }}>
    <div class="h-3 flex-1 overflow-hidden rounded-full bg-slate-200">
        <div class="h-full rounded-full {{ $corBarra }} transition-[width] duration-500" style="width: {{ $percentual }}%"></div>
    </div>
    <span class="w-16 text-right text-2xl font-bold tabular-nums {{ $corTexto }}">{{ $percentual }}%</span>
</div>
