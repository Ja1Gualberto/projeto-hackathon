<form method="POST" action="{{ route('vagas.triagem.store', $vaga) }}" class="flex flex-col gap-4" data-selecao-triagem data-total-candidatos="{{ $candidatos->total() }}">
    @csrf
    <input type="hidden" name="todos_filtrados" value="{{ old('todos_filtrados', '0') }}">
    <input type="hidden" name="busca" value="{{ $filtro->busca }}">
    @foreach ($filtro->competencias as $competenciaId)
        <input type="hidden" name="competencias[]" value="{{ $competenciaId }}">
    @endforeach
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 id="resultados-titulo" tabindex="-1" class="text-lg font-semibold">{{ $candidatos->total() }} candidatos disponíveis</h2>
        <x-botao type="submit">Adicionar selecionados à triagem</x-botao>
    </div>
    <p class="text-sm text-texto-suave">Marque candidatos individualmente ou selecione todos os resultados dos filtros, incluindo outras páginas. Adicione antes de alterar os filtros ou navegar para outra página. O vínculo não altera a participação em outros processos.</p>
    <div class="flex flex-wrap items-center gap-3">
        <x-botao variante="secundario" data-selecionar-todos-triagem aria-controls="candidatos-triagem" :disabled="$candidatos->isEmpty()">Selecionar todos</x-botao>
        <p class="text-sm text-texto-suave" role="status" data-resumo-selecao></p>
    </div>
    <hr class="border-borda">
    <div id="candidatos-triagem" class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @forelse ($candidatos as $candidato)
            <label class="flex cursor-pointer items-start gap-3 rounded-card border border-borda bg-superficie p-5 has-checked:border-primaria-600 has-checked:bg-primaria-50">
                <input type="checkbox" name="candidatos[]" value="{{ $candidato->id }}" @checked(old('todos_filtrados') ? ! in_array($candidato->id, array_map('intval', old('excluidos', [])), true) : in_array($candidato->id, array_map('intval', old('candidatos', [])), true)) class="mt-1 size-5 shrink-0 accent-primaria-600" data-candidato-triagem>
                <input type="hidden" name="excluidos[]" value="{{ $candidato->id }}" disabled data-excluir-candidato="{{ $candidato->id }}">
                <span class="flex min-w-0 flex-col gap-3">
                    <span class="font-mono font-semibold">{{ $candidato->codigo }}</span>
                    <span class="text-sm text-texto-suave">{{ $candidato->area->rotulo() }}</span>
                    <span class="flex flex-wrap gap-2">
                        @foreach ($candidato->competencias as $competencia)
                            <x-tag-competencia :nome="$competencia->nome" :da-vaga="in_array($competencia->id, $competenciasDaVagaEmDestaque, true)" :data-correspondencia="in_array($competencia->id, $competenciasEmDestaque, true) ? 'true' : 'false'" />
                        @endforeach
                    </span>
                </span>
            </label>
        @empty
            <p class="text-sm text-texto-suave">Nenhum candidato disponível com esses critérios. Amplie os filtros ou importe currículos.</p>
        @endforelse
    </div>
</form>
<div data-paginacao-busca>
    {{ $candidatos->links('components.paginacao') }}
</div>
