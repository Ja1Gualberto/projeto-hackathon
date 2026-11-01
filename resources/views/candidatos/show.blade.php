@extends('layouts.app')

@section('titulo', $candidato->nome)

@section('conteudo')
    <nav aria-label="Localização" class="text-sm">
        <a href="{{ route('candidatos.index') }}" class="font-medium text-primaria-700 underline underline-offset-4">Voltar ao banco de talentos</a>
    </nav>

    <header class="flex flex-col gap-4 border-b border-borda pb-6 sm:flex-row sm:items-start sm:justify-between">
        <div class="flex min-w-0 flex-col gap-3">
            <div><x-badge-tipo :tipo="$candidato->tipo" class="normal-case tracking-normal" /></div>
            <h1 class="text-3xl leading-tight font-semibold tracking-tight sm:text-4xl">{{ $candidato->nome }}</h1>
            <p class="text-texto-suave">{{ $candidato->area->rotulo() }}@if ($candidato->idade !== null)<span class="ml-3">{{ $candidato->idade }} anos</span>@endif</p>
            <div id="situacao-candidato">@include('candidatos.partials._situacao')</div>
        </div>
        @if ($candidato->curriculo_arquivo)
            <x-botao :href="route('candidatos.curriculo', $candidato)" variante="secundario" target="_blank" rel="noopener">Abrir PDF<span class="sr-only"> em nova aba</span></x-botao>
        @endif
    </header>

    <div class="grid items-start gap-8 xl:grid-cols-[minmax(0,1fr)_17rem]">
        <div class="flex min-w-0 flex-col gap-8">
            @foreach (['formacoes' => 'Formação', 'experiencias' => 'Experiências'] as $campo => $titulo)
                <section aria-labelledby="secao-{{ $campo }}">
                    <h2 id="secao-{{ $campo }}" class="mb-4 text-xl font-semibold tracking-tight">{{ $titulo }}</h2>
                    @if ($campo === 'experiencias')
                        <p class="mb-3 text-sm text-texto-suave">Experiência na área: {{ $candidato->experiencia_na_area ? 'sim' : 'não informado' }}</p>
                    @endif
                    <ul class="flex flex-col gap-3">
                        @forelse ($candidato->$campo ?? [] as $item)
                            <li class="max-w-prose border-l-2 border-borda pl-4 leading-relaxed wrap-anywhere">{{ $item }}</li>
                        @empty
                            <li class="text-texto-suave">Não informado no currículo</li>
                        @endforelse
                    </ul>
                </section>
            @endforeach

            <div id="competencias-candidato">
                @include('candidatos.partials._competencias', ['competencias' => $candidato->competencias])
            </div>

            <section aria-labelledby="secao-projetos" class="border-t border-borda pt-6">
                <h2 id="secao-projetos" class="mb-4 text-xl font-semibold tracking-tight">Projetos</h2>
                <ul class="flex flex-col gap-3">
                    @forelse ($candidato->projetos ?? [] as $projeto)
                        <li class="max-w-prose border-l-2 border-borda pl-4 leading-relaxed wrap-anywhere">{{ $projeto }}</li>
                    @empty
                        <li class="text-texto-suave">Não informado no currículo</li>
                    @endforelse
                </ul>
            </section>

            @include('candidatos.partials._secao-anotacoes')
            <div id="secao-lgpd">@include('candidatos.partials._secao-lgpd')</div>
            @include('candidatos.partials._secao-energia')
        </div>

        <aside class="flex min-w-0 flex-col gap-6 rounded-card border border-borda bg-superficie p-5" aria-label="Contato e documentos">
            <section>
                <h2 class="mb-4 text-lg font-semibold">Contato e identificação</h2>
                <dl class="flex flex-col gap-4 text-sm">
                    <div><dt class="mb-1 text-texto-suave">E-mail</dt><dd class="wrap-anywhere">{{ $candidato->email ?: 'Não informado no currículo' }}</dd></div>
                    <div><dt class="mb-1 text-texto-suave">Telefone</dt><dd>{{ $candidato->telefone ?: 'Não informado no currículo' }}</dd></div>
                    <div><dt class="mb-1 text-texto-suave">CPF</dt><dd>{{ $candidato->cpf ? '***.***.***-'.substr($candidato->cpf, -2) : 'Não informado no currículo' }}</dd></div>
                </dl>
            </section>
            <section class="border-t border-borda pt-5">
                <h2 class="mb-3 font-semibold">Links</h2>
                <ul class="flex flex-col gap-3 text-sm">
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
            <section class="border-t border-borda pt-5">
                <h2 class="mb-3 font-semibold">Currículo original</h2>
                @if ($candidato->curriculo_arquivo)
                    <p class="mb-3 text-sm wrap-anywhere text-texto-suave">{{ $candidato->curriculo_nome_original }}</p>
                    <x-botao :href="route('candidatos.curriculo', $candidato)" variante="secundario" tamanho="sm" target="_blank" rel="noopener">Abrir PDF<span class="sr-only"> em nova aba</span></x-botao>
                @else
                    <p class="text-sm text-texto-suave">Não informado no currículo</p>
                @endif
            </section>
        </aside>
    </div>
@endsection
