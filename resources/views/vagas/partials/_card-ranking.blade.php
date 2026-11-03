<template id="modelo-card-ranking">
    <article data-codigo class="flex flex-col gap-3 rounded-card border border-borda p-4 shadow-xs transition-shadow hover:shadow-md motion-reduce:transition-none">
        <div class="flex items-start gap-3">
            <span class="flex h-7 min-w-7 shrink-0 items-center justify-center rounded-full bg-primaria-50 px-2 text-sm font-bold text-primaria-800 tabular-nums"><span class="sr-only">Posição </span><span data-posicao></span></span>
            <div class="flex min-w-0 flex-col">
                <h4 data-card-titulo class="text-lg leading-snug font-semibold tracking-tight wrap-anywhere"><a href="#" data-link-perfil draggable="false" class="hover:text-primaria-700 hover:underline focus-visible:text-primaria-700"></a></h4>
                <p data-codigo-revelado class="font-mono text-sm text-texto-suave"></p>
            </div>
        </div>
        <x-badge data-situacao class="self-start" />
        <div data-score role="meter" aria-valuemin="0" aria-valuemax="100" class="flex items-center gap-3">
            <div class="h-3 flex-1 overflow-hidden rounded-full bg-slate-200"><div data-score-barra class="h-full rounded-full transition-[width] duration-500"></div></div>
            <span data-score-texto class="w-16 text-right text-2xl font-bold tabular-nums"></span>
        </div>
        <div data-card-detalhes hidden class="flex flex-col gap-3 border-t border-borda pt-3">
            <div class="flex flex-wrap items-center gap-2">
                <span data-tipo-servidor class="inline-flex items-center gap-1.5 rounded-full bg-servidor-100 px-2.5 py-1 text-xs font-bold text-servidor-800 ring-1 ring-servidor-300 ring-inset"><x-icone nome="vaga" class="size-3.5" />Servidor</span>
                <span data-tipo-estagiario class="inline-flex items-center gap-1.5 rounded-full bg-estagiario-100 px-2.5 py-1 text-xs font-bold text-estagiario-800 ring-1 ring-estagiario-300 ring-inset"><x-icone nome="formacao" class="size-3.5" />Estagiário</span>
                <span data-area class="text-sm text-texto-suave"></span>
            </div>
            <p data-explicacao class="text-sm leading-relaxed"></p>
            <div data-competencias class="flex flex-wrap items-center gap-1.5"></div>
            <div class="flex flex-wrap items-center justify-between gap-3 text-sm text-texto-suave">
                <p data-experiencia></p>
                @include('vagas.partials._selo-energia')
            </div>
        </div>
        <div class="flex items-end justify-between gap-2 border-t border-borda pt-2">
            <div class="flex min-w-0 flex-1 flex-wrap gap-2">
                <form method="POST" data-mover-etapa class="flex flex-wrap gap-2">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="contexto" value="quadro">
                </form>
                <x-botao variante="secundario" tamanho="sm" data-adicionar-anotacao hidden aria-haspopup="dialog" aria-controls="anotar-entrevista-quadro">Adicionar anotação</x-botao>
            </div>
            <button type="button" data-expandir-card aria-expanded="false" class="group ml-auto inline-flex size-9 shrink-0 cursor-pointer items-center justify-center rounded-lg text-primaria-700 transition-colors hover:bg-primaria-50 motion-reduce:transition-none">
                <x-icone nome="seta" class="rotate-90 transition-transform group-aria-expanded:-rotate-90 motion-reduce:transition-none" />
            </button>
        </div>
    </article>
</template>

<template id="modelo-tag-competencia">
    <span class="inline-flex items-center gap-1.5 rounded-md px-2 py-1 text-xs">
        <span data-tag-faltante hidden><x-icone nome="x" class="size-3.5" /></span>
        <span data-tag-nome></span><span data-tag-origem class="font-normal opacity-80"></span><span data-tag-peso class="font-normal opacity-80"></span>
    </span>
</template>
