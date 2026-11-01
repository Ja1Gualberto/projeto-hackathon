@extends('layouts.app')

@section('titulo', 'Candidatos')

@section('conteudo')
    <x-cabecalho-pagina titulo="Encontre talentos" subtitulo="Um banco de competências para conectar pessoas às necessidades da SETIC.">
        <x-botao :href="Route::has('importacoes.create') ? route('importacoes.create') : null" :disabled="! Route::has('importacoes.create')" :title="! Route::has('importacoes.create') ? 'Disponível na próxima etapa' : null">
            <x-icone nome="upload" class="size-4" />Importar currículo
        </x-botao>
    </x-cabecalho-pagina>

    @if ($errors->any())
        <x-alerta tipo="erro">
            <p class="font-semibold">Revise os filtros para continuar.</p>
            <ul class="mt-2 list-inside list-disc">
                @foreach ($errors->all() as $erro)<li>{{ $erro }}</li>@endforeach
            </ul>
        </x-alerta>
    @endif

    <div class="grid items-start gap-7 xl:grid-cols-[16rem_minmax(0,1fr)]">
        <form method="GET" action="{{ route('candidatos.index') }}" data-autosubmit data-busca-candidatos class="flex min-w-0 flex-col gap-5 rounded-card border border-borda bg-superficie p-5" aria-label="Filtrar talentos">
            <div data-ajax-erros role="alert" tabindex="-1" hidden class="rounded-lg border border-red-300 bg-red-50 p-3 text-sm text-red-800"></div>
            <div class="flex items-center justify-between gap-3">
                <h2 class="text-lg font-semibold">Filtros</h2>
                <a href="{{ route('candidatos.index') }}" data-limpar-busca class="text-sm font-medium text-primaria-700 underline underline-offset-4">Limpar</a>
            </div>

            <x-campo nome="busca" rotulo="Nome ou e-mail" tipo="search" :valor="$filtro->busca" maxlength="100" placeholder="Quem você procura?" />
            <x-campo nome="situacao" rotulo="Situação" tipo="select" :valor="$filtro->situacao" data-valor-padrao="ativos" :opcoes="['ativos' => 'Ativos', 'desativados' => 'Desativados', 'retencao' => 'Retenção pendente']" />
            <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-1">
                <x-campo nome="tipo" rotulo="Tipo de candidato" tipo="select" :valor="$filtro->tipo" vazio="Todos os tipos" :opcoes="collect(\App\Enums\TipoCandidato::cases())->mapWithKeys(fn ($tipo) => [$tipo->value => $tipo->rotulo()])->all()" />
                <x-campo nome="area" rotulo="Área de atuação" tipo="select" :valor="$filtro->area" vazio="Todas as áreas" :opcoes="collect(\App\Enums\Area::cases())->mapWithKeys(fn ($area) => [$area->value => $area->rotulo()])->all()" />
            </div>
            <label class="flex cursor-pointer items-start gap-3 text-sm leading-relaxed">
                <input type="checkbox" name="experiencia" value="1" @checked($filtro->somenteComExperiencia) class="mt-1 size-4 shrink-0 accent-primaria-600">
                Somente com experiência na área
            </label>

            <div class="flex flex-col gap-3 border-t border-borda pt-5">
                <div>
                    <h2 class="font-semibold">Competências</h2>
                    <p id="regra-competencias" class="mt-1 text-sm leading-relaxed text-texto-suave">O perfil precisa ter <strong class="font-semibold text-texto">todas</strong> as selecionadas. Idiomas incluem níveis superiores.</p>
                </div>
                <div data-sem-autoenvio>
                    <label for="filtrar-competencias" class="mb-1.5 block text-sm font-medium">Filtrar competências</label>
                    <input id="filtrar-competencias" type="search" data-filtrar-chips="#catalogo-competencias" placeholder="Ex.: Python" class="w-full rounded-lg border border-borda px-3 py-2 text-sm">
                </div>
                <div id="catalogo-competencias" class="flex max-h-80 flex-col gap-5 overflow-y-auto p-1" aria-describedby="regra-competencias">
                    @foreach (['hard' => 'Técnicas', 'soft' => 'Comportamentais'] as $categoria => $rotulo)
                        <fieldset class="min-w-0">
                            <legend class="mb-2 text-sm font-semibold text-texto-suave">{{ $rotulo }}</legend>
                            <div class="flex flex-wrap gap-2">
                                @forelse ($catalogo[$categoria] as $competencia)
                                    <label data-chip class="relative cursor-pointer">
                                        <input type="checkbox" name="competencias[]" value="{{ $competencia->id }}" @checked(in_array($competencia->id, $filtro->competencias, true)) class="peer sr-only">
                                        <span class="inline-flex items-center gap-1.5 rounded-md border border-borda px-2.5 py-2 text-sm leading-tight text-texto-suave peer-checked:border-primaria-600 peer-checked:bg-primaria-50 peer-checked:font-semibold peer-checked:text-primaria-800 peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-primaria-600 hover:border-primaria-400">
                                            <span aria-hidden="true" class="text-primaria-600">{{ in_array($competencia->id, $filtro->competencias, true) ? '✓' : '+' }}</span>
                                            {{ $competencia->nome }}
                                        </span>
                                    </label>
                                @empty
                                    <p class="text-sm text-texto-suave">Nenhuma competência no catálogo.</p>
                                @endforelse
                            </div>
                        </fieldset>
                    @endforeach
                </div>
            </div>
            <x-botao type="submit" class="w-full"><x-icone nome="busca" class="size-4" />Aplicar filtros</x-botao>
            <noscript><p class="text-sm text-texto-suave">Selecione os critérios e use “Aplicar filtros”.</p></noscript>
        </form>

        <section id="resultados-candidatos" aria-labelledby="resultados-titulo" class="flex min-w-0 flex-col gap-5">
            @include('candidatos.partials._resultados')
        </section>
    </div>
@endsection
