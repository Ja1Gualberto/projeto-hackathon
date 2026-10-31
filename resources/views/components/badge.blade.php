@props(['cor' => 'neutro'])

@php
    $cores = [
        'neutro' => 'bg-slate-100 text-slate-700 ring-slate-300',
        'primaria' => 'bg-primaria-50 text-primaria-800 ring-primaria-200',
        'sucesso' => 'bg-emerald-50 text-emerald-800 ring-emerald-200',
        'aviso' => 'bg-amber-50 text-amber-900 ring-amber-300',
        'perigo' => 'bg-red-50 text-red-800 ring-red-200',
    ];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset '.($cores[$cor] ?? $cores['neutro'])]) }}>
    {{ $slot }}
</span>
