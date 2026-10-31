@props([
    'variante' => 'primario',
    'tamanho' => 'md',
    'href' => null,
    'type' => 'button',
])

@php
    $variantes = [
        'primario' => 'bg-primaria-600 text-white shadow-sm hover:bg-primaria-700',
        'secundario' => 'bg-superficie text-primaria-700 ring-1 ring-borda ring-inset hover:bg-primaria-50',
        'perigo' => 'bg-red-700 text-white shadow-sm hover:bg-red-800',
        'fantasma' => 'text-primaria-700 hover:bg-primaria-50',
    ];

    $tamanhos = [
        'sm' => 'px-3 py-1.5 text-sm',
        'md' => 'px-4 py-2 text-sm',
        'lg' => 'px-5 py-3 text-base',
    ];

    $classes = 'inline-flex items-center justify-center gap-2 rounded-lg font-semibold transition-colors duration-150 disabled:cursor-not-allowed disabled:opacity-60 '
        .($variantes[$variante] ?? $variantes['primario']).' '
        .($tamanhos[$tamanho] ?? $tamanhos['md']);
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</button>
@endif
