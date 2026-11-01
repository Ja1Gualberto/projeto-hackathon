<div class="flex flex-wrap items-baseline justify-between gap-3 border-b border-borda pb-4">
    <h2 id="resultados-titulo" tabindex="-1" class="text-xl font-semibold tracking-tight">{{ $candidatos->total() }} talentos encontrados</h2>
    <p class="text-sm text-texto-suave">Perfis ativos, em ordem alfabética</p>
</div>

@if ($filtro->competencias !== [])
    <div class="flex flex-wrap items-center gap-2" aria-label="Competências selecionadas">
        <span class="text-sm text-texto-suave">Com todas:</span>
        @foreach (collect($catalogo)->flatten(1)->whereIn('id', $filtro->competencias) as $selecionada)
            <x-tag-competencia :nome="$selecionada->nome" />
        @endforeach
    </div>
@endif

@if ($candidatos->isNotEmpty())
    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-1 xl:grid-cols-2">
        @foreach ($candidatos as $candidato)
            @include('candidatos.partials._card', ['candidato' => $candidato])
        @endforeach
    </div>
    <div data-paginacao-busca>{{ $candidatos->links() }}</div>
@else
    <x-estado-vazio titulo="Nenhum talento com esses critérios" texto="Remova uma competência ou amplie a área e o tipo de candidato para tentar novamente.">
        <x-botao :href="route('candidatos.index')" data-limpar-busca variante="secundario">Remover filtros</x-botao>
    </x-estado-vazio>
@endif
