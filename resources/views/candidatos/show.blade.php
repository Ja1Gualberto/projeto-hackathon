@extends('layouts.app')

@section('titulo', $candidato->nome)

@section('conteudo')
    <nav aria-label="Localização" class="text-sm">
        <a href="{{ route('candidatos.index') }}" class="font-medium text-primaria-700 underline underline-offset-4">Voltar ao banco de talentos</a>
    </nav>

    <div data-perfil-candidato class="perfil-candidato min-w-0 rounded-lg border border-borda bg-superficie">
        <header class="flex flex-col gap-4 p-5 sm:p-6 lg:flex-row lg:items-start lg:justify-between">
            <div class="flex min-w-0 flex-col gap-2">
                <h1 class="text-2xl leading-tight font-semibold tracking-tight wrap-anywhere">{{ $candidato->nome }}</h1>
                <div class="flex flex-wrap items-center gap-x-4 gap-y-2 text-sm text-texto-suave">
                    <span>{{ $candidato->area->rotulo() }}</span>
                    @if ($candidato->idade !== null)<span>{{ $candidato->idade }} anos</span>@endif
                    @foreach ($candidato->participacoes as $participacao)
                        <a href="{{ route('vagas.show', $participacao->vaga) }}" class="rounded-md border border-borda px-2 py-0.5 text-xs font-medium text-primaria-700 underline underline-offset-4">{{ $participacao->vaga->titulo }}</a>
                    @endforeach
                    <div id="situacao-candidato">@include('candidatos.partials._situacao')</div>
                </div>
            </div>
            <div class="flex shrink-0 flex-wrap items-center gap-2">
                @if ($candidato->curriculo_arquivo)
                    <x-botao :href="route('candidatos.curriculo', $candidato)" variante="secundario" target="_blank" rel="noopener"><x-icone nome="documento" class="size-4" />Abrir PDF<span class="sr-only"> em nova aba</span></x-botao>
                @endif
                <x-botao :href="route('candidatos.edit', $candidato)" variante="secundario">Editar informações</x-botao>
            </div>
        </header>

        <nav data-abas-perfil aria-label="Seções do perfil" class="flex flex-wrap gap-x-5 border-b border-borda px-5 sm:gap-x-6 sm:px-6">
            @foreach (['visao-geral' => 'Visão geral', 'curriculo' => 'Currículo', 'entrevistas' => 'Entrevistas e avaliações', 'historico' => 'Histórico'] as $aba => $rotulo)
                <a id="aba-{{ $aba }}" href="#{{ $aba }}" data-aba-perfil="{{ $aba }}" class="border-b-2 border-transparent py-3 text-sm font-medium text-texto-suave hover:text-primaria-700 aria-selected:border-primaria-600 aria-selected:text-primaria-700 focus-visible:rounded-sm focus-visible:outline-offset-[-3px]">{{ $rotulo }}</a>
            @endforeach
        </nav>

        <section id="visao-geral" data-painel-perfil aria-labelledby="aba-visao-geral" class="p-5 sm:p-6">
            <div class="grid items-start gap-6 lg:grid-cols-[minmax(0,7fr)_minmax(14rem,3fr)] lg:gap-8">
                <div class="flex min-w-0 flex-col gap-5">
                    @foreach (['formacoes' => 'Formação acadêmica', 'experiencias' => 'Experiências profissionais'] as $campo => $titulo)
                        <section aria-labelledby="secao-{{ $campo }}" class="flex flex-col gap-3 border-b border-borda pb-5">
                            <h2 id="secao-{{ $campo }}" class="text-base font-semibold">{{ $titulo }}</h2>
                            @if ($campo === 'experiencias')
                                <p class="text-sm text-texto-suave">Experiência na área: {{ $candidato->experiencia_na_area ? 'sim' : 'não informado' }}</p>
                            @endif
                            <ul class="flex list-disc flex-col gap-2 pl-4 text-sm leading-relaxed marker:text-slate-400">
                                @forelse ($candidato->$campo ?? [] as $item)
                                    <li class="max-w-prose pl-1 wrap-anywhere">{{ $item }}</li>
                                @empty
                                    <li class="list-none text-texto-suave">Não informado no currículo</li>
                                @endforelse
                            </ul>
                        </section>
                    @endforeach

                    <div id="competencias-visao-geral" data-espelho-competencias>
                        @include('candidatos.partials._competencias', ['competencias' => $candidato->competencias, 'idSecao' => 'resumo-competencias'])
                    </div>

                    <section aria-labelledby="secao-projetos" class="flex flex-col gap-3 border-t border-borda pt-5">
                        <h2 id="secao-projetos" class="text-base font-semibold">Projetos</h2>
                        <ul class="flex list-disc flex-col gap-2 pl-4 text-sm leading-relaxed marker:text-slate-400">
                            @forelse ($candidato->projetos ?? [] as $projeto)
                                <li class="max-w-prose pl-1 wrap-anywhere">{{ $projeto }}</li>
                            @empty
                                <li class="list-none text-texto-suave">Não informado no currículo</li>
                            @endforelse
                        </ul>
                    </section>
                </div>

                <aside class="flex min-w-0 flex-col gap-5 border-t border-borda pt-5 text-sm lg:border-t-0 lg:border-l lg:pt-0 lg:pl-6" aria-label="Contato e documentos">
                    <section class="flex flex-col gap-3">
                        <h2 class="font-semibold">Contato e identificação</h2>
                        <dl class="flex flex-col gap-3">
                            <div><dt class="mb-1 text-xs text-texto-suave">E-mail</dt><dd class="wrap-anywhere">{{ $candidato->email ?: 'Não informado no currículo' }}</dd></div>
                            <div><dt class="mb-1 text-xs text-texto-suave">Telefone</dt><dd class="wrap-anywhere">{{ $candidato->telefone ?: 'Não informado no currículo' }}</dd></div>
                            <div><dt class="mb-1 text-xs text-texto-suave">CPF</dt><dd>{{ $candidato->cpf ? '***.***.***-'.substr($candidato->cpf, -2) : 'Não informado no currículo' }}</dd></div>
                        </dl>
                    </section>
                    <section class="flex flex-col gap-3 border-t border-borda pt-4">
                        <h2 class="font-semibold">Links externos</h2>
                        <ul class="flex flex-col gap-2">
                            @forelse ($candidato->links ?? [] as $link)
                                <li class="wrap-anywhere">
                                    @if (in_array(strtolower(parse_url($link, PHP_URL_SCHEME) ?? ''), ['http', 'https'], true))
                                        <a href="{{ $link }}" target="_blank" rel="noopener noreferrer" class="text-primaria-700 underline underline-offset-4">{{ $link }}<span class="sr-only"> (abre em nova aba)</span></a>
                                    @else
                                        <span class="text-texto-suave">Link indisponível: formato não permitido.</span>
                                    @endif
                                </li>
                            @empty
                                <li class="text-texto-suave">Não informado no currículo</li>
                            @endforelse
                        </ul>
                    </section>
                    <section class="flex flex-col gap-2 border-t border-borda pt-4">
                        <h2 class="font-semibold">Documento anexado</h2>
                        @if ($candidato->curriculo_arquivo)
                            <a href="#curriculo" class="wrap-anywhere text-primaria-700 underline underline-offset-4">{{ $candidato->curriculo_nome_original ?: 'Currículo em PDF' }}</a>
                            <p class="text-xs text-texto-suave">Arquivo original enviado no cadastro.</p>
                        @else
                            <p class="text-texto-suave">Não informado no currículo</p>
                        @endif
                    </section>
                </aside>
            </div>
        </section>

        <section id="curriculo" data-painel-perfil aria-labelledby="aba-curriculo" class="flex flex-col gap-4 border-t border-borda p-5 sm:p-6">
            <h2 class="text-base font-semibold">Currículo original</h2>
            @if ($candidato->curriculo_arquivo)
                <dl class="flex flex-wrap gap-x-8 gap-y-3 text-sm">
                    <div><dt class="text-xs text-texto-suave">Arquivo</dt><dd class="mt-1 wrap-anywhere">{{ $candidato->curriculo_nome_original ?: 'Currículo em PDF' }}</dd></div>
                    <div><dt class="text-xs text-texto-suave">Formato</dt><dd class="mt-1">PDF</dd></div>
                    @if ($candidato->curriculo_tamanho !== null)
                        <div><dt class="text-xs text-texto-suave">Tamanho</dt><dd class="mt-1">{{ number_format($candidato->curriculo_tamanho / 1024, 1, ',', '.') }} KB</dd></div>
                    @endif
                </dl>
                <p class="text-sm text-texto-suave">Se a prévia não aparecer no seu navegador, use “Abrir PDF” no cabeçalho para consultar o arquivo em outra aba.</p>
                <iframe data-pdf-perfil data-src="{{ route('candidatos.curriculo', $candidato) }}" title="Currículo original de {{ $candidato->nome }}" loading="lazy" class="hidden h-[65dvh] min-h-80 w-full rounded-md border border-borda"></iframe>
                <noscript><p class="text-sm text-texto-suave">A prévia requer JavaScript. O botão “Abrir PDF” permanece disponível no cabeçalho.</p></noscript>
            @else
                <p class="text-sm text-texto-suave">Nenhum PDF anexado. Os dados disponíveis podem ser consultados na visão geral.</p>
            @endif
        </section>

        <section id="entrevistas" data-painel-perfil @if ($errors->hasAny(['texto', 'vaga_id', 'competencias_observadas', 'competencias_observadas.*', 'dinamico_analitico', 'individual_equipe', 'execucao_planejamento', 'justificativa'])) data-painel-com-erros @endif aria-labelledby="aba-entrevistas" class="flex flex-col gap-6 border-t border-borda p-5 sm:p-6">
            @include('candidatos.partials._secao-anotacoes')
            <div id="competencias-candidato">
                @include('candidatos.partials._competencias', ['competencias' => $candidato->competencias])
            </div>
            <div id="secao-energia" class="border-t border-borda pt-5">@include('candidatos.partials._secao-energia')</div>
        </section>

        <section id="historico" data-painel-perfil @if ($errors->hasAny(['motivo', 'confirmacao', 'status'])) data-painel-com-erros @endif aria-labelledby="aba-historico" class="flex flex-col gap-6 border-t border-borda p-5 sm:p-6">
            <section aria-labelledby="historico-titulo" class="flex flex-col gap-3 border-b border-borda pb-5">
                <h2 id="historico-titulo" class="text-base font-semibold">Histórico cadastral</h2>
                <dl class="grid gap-4 text-sm sm:grid-cols-2">
                    <div><dt class="text-texto-suave">Cadastro criado em</dt><dd class="mt-1">{{ $candidato->created_at?->format('d/m/Y H:i') ?: 'Não informado' }}</dd></div>
                    <div><dt class="text-texto-suave">Última atualização cadastral</dt><dd class="mt-1">{{ $candidato->dados_atualizados_em?->format('d/m/Y H:i') ?: 'Não informado' }}</dd></div>
                </dl>
                <p class="max-w-prose text-sm leading-relaxed text-texto-suave">Estas são as datas disponíveis no cadastro. O sistema não mantém uma trilha detalhada de alterações de dados e de status.</p>
            </section>
            <div id="secao-lgpd">@include('candidatos.partials._secao-lgpd')</div>
        </section>
    </div>
@endsection
