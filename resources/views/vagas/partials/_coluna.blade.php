<template id="modelo-coluna">
    <section data-etapa class="flex min-w-0 flex-col gap-3 rounded-card border border-t-4 border-borda bg-fundo p-3 transition-shadow data-alvo-soltar:ring-2 data-alvo-soltar:ring-primaria-500">
        <header class="flex items-center justify-between gap-2 px-1">
            <h3 data-coluna-titulo class="text-lg font-semibold"></h3>
            <span data-coluna-total class="rounded-full bg-superficie px-2.5 py-0.5 text-sm font-semibold tabular-nums ring-1 ring-borda"></span>
        </header>
        <p data-aviso-triagem class="flex items-center gap-1.5 px-1 text-sm text-texto-suave"><x-icone nome="cadeado" class="size-4" />Só o código aparece nesta coluna.</p>
        <div data-sem-elegiveis>
            <x-estado-vazio icone="grupo" titulo="Nenhum candidato na triagem" texto="Selecione candidatos do banco por competências ou importe currículos para esta vaga. Nenhum candidato entra automaticamente sem vínculo ao processo." />
        </div>
        <p data-coluna-vazia class="rounded-lg border border-dashed border-borda px-3 py-6 text-center text-sm text-texto-suave"></p>
        <ol data-coluna-itens class="flex flex-col gap-3"></ol>
    </section>
</template>
