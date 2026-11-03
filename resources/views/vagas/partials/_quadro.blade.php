<template id="modelo-quadro">
    <div class="flex flex-col gap-6">
        <header class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div class="flex min-w-0 flex-col gap-2">
                <div class="flex flex-wrap items-center gap-2">
                    <x-badge data-status />
                    <span data-area class="text-sm text-texto-suave"></span>
                </div>
                <h1 data-titulo tabindex="-1" data-foco-apos-ajax class="text-3xl font-bold tracking-tight text-texto focus:outline-none"></h1>
                <p data-descricao class="max-w-3xl leading-relaxed text-texto-suave"></p>
            </div>
            <div class="flex shrink-0 flex-wrap gap-3">
                <x-botao href="#" variante="secundario" data-atualizar-quadro>Atualizar quadro</x-botao>
                <x-botao href="#" variante="secundario" data-editar-vaga>Editar vaga</x-botao>
                <form method="POST" data-encerrar-vaga data-confirmar="Encerrar esta vaga? O quadro ficará disponível somente para consulta.">
                    @csrf
                    @method('PATCH')
                    <x-botao type="submit" variante="perigo">Encerrar vaga</x-botao>
                </form>
            </div>
        </header>
        <div data-adicionar-candidatos class="flex flex-wrap gap-3">
            <x-botao href="#" data-selecionar-candidatos>Selecionar candidatos do banco</x-botao>
            <x-botao href="#" variante="secundario" data-importar-curriculos>Importar currículos para esta vaga</x-botao>
        </div>
        <div data-vaga-encerrada class="flex flex-col gap-1 border-l-4 border-borda pl-4 text-sm text-texto-suave">
            <p class="font-semibold text-texto">Vaga encerrada — somente consulta</p>
            <p>Encerrada em <span data-encerrada-em></span>. O quadro e o histórico continuam disponíveis para consulta; a vaga não aceita mais edições nem movimentações.</p>
        </div>
        <section aria-labelledby="requisitos-titulo" class="rounded-card border border-borda bg-superficie p-5">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <h2 id="requisitos-titulo" class="font-semibold">Competências pedidas</h2>
                <p class="text-sm text-texto-suave">Soma dos pesos: <strong data-soma-pesos class="font-semibold text-texto tabular-nums"></strong></p>
            </div>
            <ul data-requisitos class="mt-3 flex flex-wrap gap-2"></ul>
        </section>
        <section aria-labelledby="selecao-titulo" class="flex flex-col gap-4">
            <div class="flex flex-wrap items-baseline justify-between gap-3 border-b border-borda pb-3">
                <h2 id="selecao-titulo" class="text-xl font-semibold tracking-tight">Seleção <span data-elegiveis class="font-normal text-texto-suave"></span></h2>
                <p class="flex items-center gap-2 text-sm text-texto-suave"><x-icone nome="cadeado" class="size-4" />Triagem anônima: o nome aparece a partir da entrevista, só nesta vaga.</p>
            </div>
            <div class="overflow-x-auto pb-2">
                <div data-colunas class="grid items-start gap-4 lg:min-w-[84rem] lg:grid-cols-4"></div>
            </div>
        </section>
    </div>
</template>
