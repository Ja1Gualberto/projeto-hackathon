{{--
    Conteúdo da região "perfil-na-vaga". Recebe só o PerfilNaVagaDTO ($perfil) e a vaga: em triagem,
    identidade, contatos, idade, links, anotações e PDF chegam nulos, e formação, experiências e
    projetos já vêm sem e-mails, telefones, URLs e o nome (contracts/visibilidade-por-contexto.md).
--}}
@php
    $revelado = $perfil->identidadeRevelada;
    $identificacao = $perfil->nome ?? $perfil->codigo;
    $acoes = match ($perfil->etapa) {
        \App\Enums\EtapaSelecao::Triagem => [[\App\Enums\EtapaSelecao::Entrevista, 'Mover para Entrevista', 'primario']],
        \App\Enums\EtapaSelecao::Entrevista => [[\App\Enums\EtapaSelecao::Aprovado, 'Aprovar', 'primario'], [\App\Enums\EtapaSelecao::Triagem, 'Voltar para Triagem', 'secundario']],
        \App\Enums\EtapaSelecao::Aprovado => [[\App\Enums\EtapaSelecao::Entrevista, 'Voltar para Entrevista', 'secundario']],
    };
@endphp

<div class="flex flex-col gap-8">
    <header class="flex flex-col gap-4 border-b border-borda pb-6 lg:flex-row lg:items-start lg:justify-between">
        <div class="flex min-w-0 flex-col gap-3">
            <div class="flex flex-wrap items-center gap-2">
                <x-badge-tipo :tipo="$perfil->tipo" class="normal-case tracking-normal" />
                <x-badge :cor="$revelado ? 'primaria' : 'neutro'">Etapa nesta vaga: {{ $perfil->etapa->rotulo() }}</x-badge>
                @if ($perfil->desativado)
                    <x-badge>Desativado</x-badge>
                @endif
            </div>

            @if ($revelado)
                <h1 tabindex="-1" data-foco-apos-ajax class="text-3xl leading-tight font-semibold tracking-tight focus:outline-none sm:text-4xl">{{ $perfil->nome }}</h1>
                <p class="text-texto-suave">
                    <span class="font-mono">{{ $perfil->codigo }}</span> · {{ $perfil->area->rotulo() }}@if ($perfil->idade !== null) · {{ $perfil->idade }} anos @endif
                </p>
            @else
                <h1 tabindex="-1" data-foco-apos-ajax class="font-mono text-3xl leading-tight font-semibold tracking-tight focus:outline-none sm:text-4xl">{{ $perfil->codigo }}</h1>
                <p class="flex items-center gap-2 font-semibold text-texto"><x-icone nome="cadeado" class="size-5 text-primaria-700" />Identidade protegida na triagem anônima</p>
                <p class="max-w-2xl text-sm leading-relaxed text-texto-suave">{{ $perfil->area->rotulo() }}. Nome, contatos, idade, links, anotações e o PDF aparecem quando o candidato é movido para a entrevista nesta vaga.</p>
            @endif
        </div>

        <div class="flex shrink-0 flex-wrap items-start gap-3 lg:justify-end">
            @if ($perfil->podeMover)
                <form method="POST" action="{{ route('vagas.candidatos.etapa', [$vaga, $perfil->codigo]) }}" data-mover-etapa class="flex flex-wrap gap-2">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="contexto" value="perfil">
                    @foreach ($acoes as [$destino, $rotulo, $variante])
                        <x-botao type="submit" name="etapa" :value="$destino->value" :variante="$variante">{{ $rotulo }}<span class="sr-only">: {{ $identificacao }}</span></x-botao>
                    @endforeach
                </form>
            @endif
            @if ($perfil->temCurriculo)
                <x-botao :href="route('vagas.candidatos.curriculo', [$vaga, $perfil->codigo])" variante="secundario" target="_blank" rel="noopener">
                    <x-icone nome="documento" class="size-4" />Abrir PDF<span class="sr-only"> em nova aba</span>
                </x-botao>
            @endif
        </div>
    </header>

    @unless ($vaga->estaAberta())
        <x-alerta tipo="aviso" titulo="Vaga encerrada — somente consulta">A vaga não aceita mais movimentações; o perfil e o histórico continuam disponíveis.</x-alerta>
    @endunless

    <div class="grid items-start gap-8 xl:grid-cols-[minmax(0,1fr)_18rem]">
        <div class="flex min-w-0 flex-col gap-8">
            {{-- Região "competencias-candidato": aderência e competências mudam juntas quando uma anotação registra competências observadas. --}}
            <div id="competencias-candidato" class="flex flex-col gap-8">
                @include('participacoes.partials._competencias-na-vaga')
            </div>

            @foreach (['formacoes' => 'Formação', 'experiencias' => 'Experiências', 'projetos' => 'Projetos'] as $campo => $titulo)
                <section aria-labelledby="perfil-{{ $campo }}">
                    <h2 id="perfil-{{ $campo }}" class="mb-4 text-xl font-semibold tracking-tight">{{ $titulo }}</h2>
                    @if ($campo === 'experiencias')
                        <p class="mb-3 text-sm text-texto-suave">Experiência na área: {{ $perfil->experienciaNaArea ? 'sim' : 'não informado' }}</p>
                    @endif
                    <ul class="flex flex-col gap-3">
                        @forelse ($perfil->$campo as $texto)
                            <li class="max-w-prose border-l-2 border-borda pl-4 leading-relaxed wrap-anywhere">{{ $texto }}</li>
                        @empty
                            <li class="text-texto-suave">Não informado no currículo</li>
                        @endforelse
                    </ul>
                </section>
            @endforeach

            <section aria-labelledby="energia-na-vaga-titulo" class="flex flex-col gap-4 border-t border-borda pt-6">
                <h2 id="energia-na-vaga-titulo" class="text-xl font-semibold tracking-tight">Padrão de energia</h2>
                <p class="text-sm text-texto-suave">Sugestão revisável, não um diagnóstico. Não altera a aderência ou a ordenação.</p>
                @if ($perfil->energia)
                    <div class="flex flex-wrap gap-3 text-sm"><x-badge>{{ $perfil->origemEnergia->rotulo() }}</x-badge><span>{{ $perfil->energiaAnalisadaEm }}</span></div>
                    <x-eixos-energia :eixos="$perfil->energia->eixos()" :vaga="$vaga->temPadraoEnergia() ? ['dinamico_analitico' => $vaga->energia_dinamico_analitico, 'individual_equipe' => $vaga->energia_individual_equipe, 'execucao_planejamento' => $vaga->energia_execucao_planejamento] : null" />
                    <p class="text-sm leading-relaxed wrap-anywhere">{{ $perfil->energia->justificativa ?: 'Justificativa não informada.' }}</p>
                    @if ($perfil->proximidadeEnergia !== null)
                        <p class="text-sm font-semibold">Energia: {{ $perfil->proximidadeEnergia }}% de proximidade</p>
                    @endif
                @else
                    <p class="text-sm text-texto-suave">Não analisado</p>
                @endif
            </section>

            @if ($revelado)
                {{-- Só com a identidade revelada nesta vaga: as anotações podem citar o nome (matriz de visibilidade). --}}
                <section aria-labelledby="perfil-anotacoes" class="flex flex-col gap-4 border-t border-borda pt-6">
                    <div>
                        <h2 id="perfil-anotacoes" class="text-xl font-semibold tracking-tight">Anotações de entrevista</h2>
                        <p class="mt-2 text-sm leading-relaxed text-texto-suave">Todas as anotações do candidato, de qualquer vaga. As registradas aqui ficam associadas a esta vaga.</p>
                    </div>
                    @if (($catalogo ?? null) !== null)
                        @include('anotacoes._formulario', ['acao' => route('vagas.candidatos.anotacoes.store', [$vaga, $perfil->codigo]), 'catalogo' => $catalogo])
                    @endif
                    <div id="historico-anotacoes">
                        @include('anotacoes._historico', ['anotacoes' => $perfil->anotacoes])
                    </div>
                </section>
            @endif
        </div>

        <aside class="flex min-w-0 flex-col gap-6 rounded-card border border-borda bg-superficie p-5" aria-label="Contato e histórico">
            @if ($revelado)
                <section>
                    <h2 class="mb-4 text-lg font-semibold">Contato</h2>
                    <dl class="flex flex-col gap-4 text-sm">
                        <div><dt class="mb-1 text-texto-suave">E-mail</dt><dd class="wrap-anywhere">{{ $perfil->email ?: 'Não informado no currículo' }}</dd></div>
                        <div><dt class="mb-1 text-texto-suave">Telefone</dt><dd>{{ $perfil->telefone ?: 'Não informado no currículo' }}</dd></div>
                    </dl>
                </section>
                <section class="border-t border-borda pt-5">
                    <h2 class="mb-3 font-semibold">Links</h2>
                    <ul class="flex flex-col gap-3 text-sm">
                        @forelse ($perfil->links as $link)
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
            @else
                <section>
                    <h2 class="mb-3 text-lg font-semibold">Contato e links</h2>
                    <p class="flex items-start gap-2 text-sm text-texto-suave">
                        <x-icone nome="cadeado" class="mt-0.5 size-4" />
                        @if ($perfil->quantidadeLinks === 0)
                            Nenhum link informado no currículo. Os contatos aparecem a partir da entrevista.
                        @else
                            {{ $perfil->quantidadeLinks }} {{ $perfil->quantidadeLinks === 1 ? 'link — visível' : 'links — visíveis' }} a partir da entrevista
                        @endif
                    </p>
                </section>
            @endif

            <section class="border-t border-borda pt-5" aria-labelledby="perfil-historico">
                <h2 id="perfil-historico" class="mb-3 font-semibold">Histórico nesta vaga</h2>
                <ol class="flex flex-col gap-3 text-sm">
                    @forelse ($perfil->movimentacoes as $movimentacao)
                        <li class="border-l-2 border-primaria-200 pl-3">Movido para {{ $movimentacao->para->rotulo() }} por {{ $movimentacao->usuario?->name ?? 'usuário removido' }} em {{ $movimentacao->created_at->format('d/m/Y H:i') }}</li>
                    @empty
                        <li class="text-texto-suave">Nenhuma movimentação: em triagem desde o cadastro.</li>
                    @endforelse
                </ol>
            </section>
        </aside>
    </div>
</div>
