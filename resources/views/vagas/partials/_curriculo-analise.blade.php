<x-modal id="curriculo-analise-quadro" titulo="Currículo e análise de IA" amplo aria-describedby="visualizacao-candidato-nome" class="max-h-[calc(100dvh_-_2rem)] max-w-[min(90rem,calc(100%_-_2rem))] overflow-y-auto">
    <div class="flex flex-col gap-5">
        <p id="visualizacao-candidato-nome" data-visualizacao-nome class="text-lg font-semibold wrap-anywhere"></p>
        <p data-visualizacao-carregando role="status" hidden class="text-sm text-texto-suave">Carregando currículo e análise…</p>
        <div data-visualizacao-falha hidden class="flex flex-col items-start gap-3">
            <p data-visualizacao-erro role="alert" class="text-sm text-red-800"></p>
            <x-botao variante="secundario" tamanho="sm" data-tentar-visualizacao>Tentar novamente</x-botao>
        </div>
        <div id="visualizacao-candidato"></div>
    </div>
</x-modal>
