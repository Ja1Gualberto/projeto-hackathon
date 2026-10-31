@props(['id', 'titulo'])

{{-- Abra com um botão data-abrir-modal="{id}"; feche com data-fechar-modal (resources/js/modulos/formularios.js). --}}
<dialog id="{{ $id }}" aria-labelledby="{{ $id }}-titulo" {{ $attributes->merge(['class' => 'm-auto w-full max-w-lg rounded-card bg-superficie p-0 text-texto shadow-xl']) }}>
    <div class="flex items-start justify-between gap-4 border-b border-borda px-6 py-4">
        <h2 id="{{ $id }}-titulo" class="text-lg font-bold">{{ $titulo }}</h2>
        <button type="button" data-fechar-modal class="rounded-lg p-1 text-texto-suave hover:bg-slate-100 hover:text-texto" aria-label="Fechar">
            <x-icone nome="x" />
        </button>
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
