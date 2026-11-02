@props(['id', 'titulo', 'amplo' => false, 'abrirAoCarregar' => false])

{{-- Abra com um botão data-abrir-modal="{id}"; feche com data-fechar-modal (resources/js/modulos/formularios.js). --}}
<dialog id="{{ $id }}" aria-labelledby="{{ $id }}-titulo" @if ($abrirAoCarregar) data-abrir-ao-carregar @endif {{ $attributes->merge(['class' => 'm-auto w-full rounded-card bg-superficie p-0 text-texto shadow-xl '.($amplo ? 'max-w-3xl' : 'max-w-lg')]) }}>
    <div class="flex flex-wrap items-center justify-between gap-4 border-b border-borda px-6 py-4">
        <h2 id="{{ $id }}-titulo" class="text-lg font-bold">{{ $titulo }}</h2>
        <div class="ml-auto flex items-center gap-3">
            {{ $acoes ?? '' }}
            <button type="button" data-fechar-modal class="rounded-lg p-1 text-texto-suave hover:bg-slate-100 hover:text-texto" aria-label="Fechar">
                <x-icone nome="x" />
            </button>
        </div>
    </div>

    <div class="px-6 py-5">
        {{ $slot }}
    </div>

    @isset($rodape)
        <div class="flex justify-end gap-3 rounded-b-card border-t border-borda bg-fundo px-6 py-4">
            {{ $rodape }}
        </div>
    @endisset
</dialog>
