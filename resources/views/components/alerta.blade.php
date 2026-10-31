@props([
    'tipo' => 'info',
    'titulo' => null,
])

@php
    $estilos = [
        'info' => ['classes' => 'border-primaria-200 bg-primaria-50 text-primaria-900', 'icone' => 'info'],
        'sucesso' => ['classes' => 'border-emerald-200 bg-emerald-50 text-emerald-900', 'icone' => 'sucesso'],
        'aviso' => ['classes' => 'border-amber-300 bg-amber-50 text-amber-900', 'icone' => 'alerta'],
        'erro' => ['classes' => 'border-red-200 bg-red-50 text-red-900', 'icone' => 'erro'],
    ];

    $estilo = $estilos[$tipo] ?? $estilos['info'];
@endphp

<div role="{{ $tipo === 'erro' ? 'alert' : 'status' }}" {{ $attributes->merge(['class' => 'flex items-start gap-3 rounded-lg border px-4 py-3 '.$estilo['classes']]) }}>
    <x-icone :nome="$estilo['icone']" class="mt-0.5" />
    <div class="flex flex-col gap-1 text-sm">
        @if ($titulo)
            <p class="font-bold">{{ $titulo }}</p>
        @endif
        <div>{{ $slot }}</div>
    </div>
</div>
