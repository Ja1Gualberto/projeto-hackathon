<div data-ajax-erros role="alert" tabindex="-1" hidden class="rounded-lg border border-red-300 bg-red-50 p-3 text-sm text-red-800"></div>

<section aria-labelledby="dados-vaga-titulo" class="flex flex-col gap-5">
    <h2 id="dados-vaga-titulo" class="text-lg font-semibold">Dados da vaga</h2>
    <div class="flex flex-col gap-1.5">
        <label for="campo-titulo" class="text-sm font-semibold text-texto">Título <span class="text-red-700" aria-hidden="true">*</span></label>
        <input id="campo-titulo" name="titulo" type="text" maxlength="120" required placeholder="Ex.: Desenvolvimento de Sistemas" class="block w-full rounded-lg border border-borda-campo bg-superficie px-3 py-2 text-texto shadow-xs transition-colors focus:border-primaria-600 focus:ring-2 focus:ring-primaria-400 focus:outline-none motion-reduce:transition-none">
    </div>
    <div class="grid gap-5 sm:grid-cols-2">
        <div class="flex flex-col gap-1.5">
            <label for="campo-area" class="text-sm font-semibold text-texto">Área <span class="text-red-700" aria-hidden="true">*</span></label>
            <select id="campo-area" name="area" required class="block w-full rounded-lg border border-borda-campo bg-superficie px-3 py-2 text-texto shadow-xs transition-colors focus:border-primaria-600 focus:ring-2 focus:ring-primaria-400 focus:outline-none motion-reduce:transition-none"><option value="">Selecione a área</option></select>
        </div>
    </div>
    <div class="flex flex-col gap-1.5">
        <label for="campo-descricao" class="text-sm font-semibold text-texto">Descrição</label>
        <textarea id="campo-descricao" name="descricao" rows="4" maxlength="1000" aria-describedby="campo-descricao-ajuda" class="block w-full rounded-lg border border-borda-campo bg-superficie px-3 py-2 text-texto shadow-xs transition-colors focus:border-primaria-600 focus:ring-2 focus:ring-primaria-400 focus:outline-none motion-reduce:transition-none"></textarea>
        <p id="campo-descricao-ajuda" class="text-sm text-texto-suave">Opcional, até 1000 caracteres.</p>
    </div>
</section>

<section aria-labelledby="competencias-vaga-titulo" class="flex flex-col gap-5 border-t border-borda pt-6">
    <div class="flex flex-col gap-1">
        <h2 id="competencias-vaga-titulo" class="text-lg font-semibold">Competências e pesos <span class="text-red-700" aria-hidden="true">*</span></h2>
        <p id="regra-pesos" class="max-w-3xl text-sm leading-relaxed text-texto-suave">
            Dê um peso a cada competência pedida: <strong class="font-semibold text-texto">1 desejável</strong>,
            <strong class="font-semibold text-texto">2 importante</strong> ou <strong class="font-semibold text-texto">3 essencial</strong>.
            A aderência é a soma dos pesos que o candidato atende dividida pela soma de todos. Idiomas aceitam níveis superiores.
        </p>
        <p id="erro-competencias" data-erro-campo="competencias" hidden class="mt-1 text-sm font-medium text-red-700"></p>
    </div>
    <div class="max-w-sm">
        <label for="filtrar-competencias-vaga" class="mb-1.5 block text-sm font-medium">Filtrar competências</label>
        <input id="filtrar-competencias-vaga" type="search" data-filtrar-chips="#competencias-vaga" placeholder="Ex.: Python" class="w-full rounded-lg border border-borda-campo px-3 py-2 text-sm">
    </div>
    <div id="competencias-vaga" class="flex flex-col gap-6">
        <fieldset class="min-w-0" aria-describedby="regra-pesos"><legend class="mb-3 font-semibold">Competências técnicas (hard skills)</legend><div data-catalogo="hard" class="grid gap-x-5 gap-y-2 sm:grid-cols-2 xl:grid-cols-3"></div></fieldset>
        <fieldset class="min-w-0" aria-describedby="regra-pesos"><legend class="mb-3 font-semibold">Competências comportamentais (soft skills)</legend><div data-catalogo="soft" class="grid gap-x-5 gap-y-2 sm:grid-cols-2 xl:grid-cols-3"></div></fieldset>
    </div>
</section>

<section aria-labelledby="energia-vaga-titulo" class="flex flex-col gap-5 border-t border-borda pt-6">
    <div><h2 id="energia-vaga-titulo" class="text-lg font-semibold">Padrão de energia desejado</h2><p class="mt-2 text-sm leading-relaxed text-texto-suave">Opcional: preencha os três eixos ou deixe todos em “Não definir”. A proximidade é informativa e não altera a aderência ou a ordenação.</p></div>
    <div data-eixos-energia class="grid gap-5 sm:grid-cols-3"></div>
</section>

<template id="modelo-peso-competencia">
    <div data-chip class="flex min-w-0 flex-col gap-1">
        <div class="flex items-center justify-between gap-3 rounded-lg border border-borda px-3 py-2 has-[option:checked:not([value=''])]:border-primaria-600 has-[option:checked:not([value=''])]:bg-primaria-50">
            <label class="min-w-0 text-sm font-medium wrap-anywhere"></label>
            <select class="shrink-0 rounded-md border border-borda-campo bg-superficie py-1 pr-7 pl-2 text-sm focus:border-primaria-600 focus:ring-2 focus:ring-primaria-400 focus:outline-none">
                <option value="">Não usar</option><option value="1">1 · Desejável</option><option value="2">2 · Importante</option><option value="3">3 · Essencial</option>
            </select>
        </div>
        <p data-erro-campo hidden class="text-sm font-medium text-red-700"></p>
    </div>
</template>
<template id="modelo-eixo-energia">
    <div class="flex flex-col gap-1.5">
        <label class="text-sm font-semibold text-texto"></label>
        <select class="block w-full rounded-lg border border-borda-campo bg-superficie px-3 py-2 text-texto shadow-xs transition-colors focus:border-primaria-600 focus:ring-2 focus:ring-primaria-400 focus:outline-none motion-reduce:transition-none">
            <option value="">Não definir</option><option value="1">1</option><option value="2">2</option><option value="3">3 · Neutro</option><option value="4">4</option><option value="5">5</option>
        </select>
    </div>
</template>
