@props(['tipo'])

@php
    $tipo = $tipo instanceof \App\Enums\TipoCandidato ? $tipo : \App\Enums\TipoCandidato::from($tipo);
@endphp

{{-- Servidor e estagiário se diferenciam por cor, ícone e texto (FR-006), não só pela cor. --}}
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-bold uppercase tracking-wide ring-1 ring-inset '.$tipo->cor()]) }}>
    <x-icone :nome="$tipo->icone()" class="size-3.5" />
    {{ $tipo->rotulo() }}
</span>
