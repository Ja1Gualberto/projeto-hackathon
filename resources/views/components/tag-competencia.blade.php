@props([
    'nome',
    'origem' => null,
    'faltante' => false,
    'peso' => null,
])

@php
    $origem = is_string($origem) ? \App\Enums\OrigemCompetencia::tryFrom($origem) : $origem;
@endphp

@if ($faltante)
    {{-- "Não identificado no currículo" é diferente de "não possui" (FR-007). --}}
    <span title="Não identificado no currículo" {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 rounded-md border border-dashed border-slate-400 bg-superficie px-2 py-1 text-xs font-medium text-texto-suave']) }}>
        <x-icone nome="x" class="size-3.5" />
        {{ $nome }}
        @if ($peso)
            <span class="font-semibold">· peso {{ $peso }}</span>
        @endif
    </span>
@else
    @php
        $classes = $origem === \App\Enums\OrigemCompetencia::Entrevista
            ? 'bg-emerald-50 text-emerald-800 ring-emerald-200'
            : 'bg-primaria-50 text-primaria-800 ring-primaria-200';
    @endphp

    <span @if ($origem) title="{{ $origem->rotulo() }}" @endif {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 rounded-md px-2 py-1 text-xs font-semibold ring-1 ring-inset '.$classes]) }}>
        {{ $nome }}
        @if ($origem)
            <span class="font-normal opacity-80">· {{ $origem->rotuloCurto() }}</span>
        @endif
        @if ($peso)
            <span class="font-normal opacity-80">· peso {{ $peso }}</span>
        @endif
    </span>
@endif
