@extends('layouts.app')

@section('titulo', 'Selecionar candidatos')

@section('conteudo')
    <nav aria-label="Localização"><a href="{{ route('vagas.show', $vaga) }}" class="text-sm font-medium text-primaria-700 underline underline-offset-4">Voltar ao quadro da vaga</a></nav>
    <x-cabecalho-pagina titulo="Selecionar candidatos" :subtitulo="$vaga->titulo.' — filtre por competências e marque quem deseja adicionar à triagem.'">
        <x-botao :href="route('importacoes.create', ['vaga_id' => $vaga->id])" variante="secundario">Importar currículos para esta vaga</x-botao>
    </x-cabecalho-pagina>

    @if ($errors->any())
        <x-alerta tipo="erro"><ul class="list-inside list-disc">@foreach ($errors->all() as $erro)<li>{{ $erro }}</li>@endforeach</ul></x-alerta>
    @endif

    <form method="GET" action="{{ route('vagas.triagem.index', $vaga) }}" class="flex flex-col gap-5 rounded-card border border-borda bg-superficie p-5">
        <x-campo nome="busca" rotulo="Buscar no banco" tipo="search" :valor="$filtro->busca" maxlength="100" ajuda="Busque pelo código, nome ou e-mail. A identidade continua oculta nos cartões da triagem." />
        <fieldset class="flex flex-col gap-3">
            <legend class="mb-2 font-semibold">Filtrar por competências</legend>
            <p class="text-sm text-texto-suave">O resultado deve atender a pelo menos uma das competências marcadas. Quem atende a mais competências selecionadas aparece primeiro. Candidatos já vinculados a esta vaga não aparecem.</p>
            @foreach ($catalogo as $categoria => $competencias)
                <div class="flex flex-col gap-2">
                    <h2 class="text-sm font-semibold">{{ $categoria === 'hard' ? 'Competências técnicas' : 'Competências comportamentais' }}</h2>
                    <div class="flex max-h-48 flex-wrap gap-2 overflow-y-auto p-1">
                        @foreach ($competencias as $competencia)
                            <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-borda px-3 py-2 text-sm has-checked:border-primaria-600 has-checked:bg-primaria-50">
                                <input type="checkbox" name="competencias[]" value="{{ $competencia->id }}" @checked(in_array($competencia->id, $competenciasSelecionadas, true)) class="size-4 accent-primaria-600">
                                {{ $competencia->nome }}
                            </label>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </fieldset>
        <div class="flex flex-wrap gap-3">
            <x-botao type="submit">Filtrar candidatos</x-botao>
            <x-botao :href="route('vagas.triagem.index', [$vaga, 'busca' => ''])" variante="secundario">Limpar filtros</x-botao>
        </div>
    </form>

    <form method="POST" action="{{ route('vagas.triagem.store', $vaga) }}" class="flex flex-col gap-4">
        @csrf
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-lg font-semibold">{{ $candidatos->total() }} candidatos disponíveis</h2>
            <x-botao type="submit">Adicionar selecionados à triagem</x-botao>
        </div>
        <p class="text-sm text-texto-suave">Marque os candidatos desta página e adicione antes de navegar para outra página. O vínculo não altera a participação em outros processos.</p>
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @forelse ($candidatos as $candidato)
                <label class="flex cursor-pointer items-start gap-3 rounded-card border border-borda bg-superficie p-5 has-checked:border-primaria-600 has-checked:bg-primaria-50">
                    <input type="checkbox" name="candidatos[]" value="{{ $candidato->id }}" @checked(in_array($candidato->id, array_map('intval', old('candidatos', [])), true)) class="mt-1 size-5 shrink-0 accent-primaria-600">
                    <span class="flex min-w-0 flex-col gap-3">
                        <span class="font-mono font-semibold">{{ $candidato->codigo }}</span>
                        <span class="text-sm text-texto-suave">{{ $candidato->area->rotulo() }}</span>
                        <span class="flex flex-wrap gap-2">@foreach ($candidato->competencias as $competencia)<x-tag-competencia :nome="$competencia->nome" />@endforeach</span>
                    </span>
                </label>
            @empty
                <p class="text-sm text-texto-suave">Nenhum candidato disponível com esses critérios. Amplie os filtros ou importe currículos.</p>
            @endforelse
        </div>
    </form>
    {{ $candidatos->links('components.paginacao') }}
@endsection
