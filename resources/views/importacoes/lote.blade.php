@extends('layouts.app')

@section('titulo', 'Currículos importados')

@section('conteudo')
    <nav aria-label="Localização" class="text-sm">
        <a href="{{ route('importacoes.create') }}" class="font-medium text-primaria-700 underline underline-offset-4">Enviar outros currículos</a>
    </nav>

    <x-cabecalho-pagina titulo="Currículos importados" :subtitulo="$resumo['cadastrados'].' de '.$resumo['total'].' cadastrados. Nada é salvo antes da sua decisão.'">
        @if ($resumo['pendentes'] > 0)
            <x-botao href="#escolha-sem-js" data-abrir-modal="escolha-lote" variante="secundario">Escolher como continuar</x-botao>
            <x-botao :href="route('importacoes.show', $resumo['proximo'])">Revisar pendentes<x-icone nome="seta" class="size-4" /></x-botao>
        @else
            <x-botao :href="route('candidatos.index')">Ver banco de talentos<x-icone nome="seta" class="size-4" /></x-botao>
        @endif
    </x-cabecalho-pagina>

    @if ($errors->any())
        <x-alerta tipo="erro" titulo="Nada foi salvo.">
            <ul class="list-inside list-disc">
                @foreach ($errors->unique() as $erro)<li>{{ $erro }}</li>@endforeach
            </ul>
        </x-alerta>
    @endif

    <ul class="flex flex-col gap-3" aria-label="Situação de cada currículo">
        @foreach ($resumo['itens'] as $item)
            <li class="flex flex-col gap-3 rounded-card border border-borda bg-superficie p-5 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex min-w-0 flex-col gap-1.5">
                    <p class="flex items-start gap-2 font-semibold wrap-anywhere">
                        <x-icone nome="documento" class="mt-0.5 size-4 text-texto-suave" />
                        {{ $item['nome_original'] }}
                    </p>
                    <div class="flex flex-wrap items-center gap-2 text-sm">
                        @if ($item['situacao'] === \App\Services\Curriculo\LoteImportacaoService::CADASTRADO)
                            <x-badge cor="sucesso">Cadastrado</x-badge>
                            <span class="text-texto-suave">{{ $item['candidato_nome'] }}</span>
                        @elseif ($item['situacao'] === \App\Services\Curriculo\LoteImportacaoService::DESCARTADO)
                            <x-badge>Descartado</x-badge>
                        @elseif ($item['apto'])
                            <x-badge cor="primaria">Pronto para cadastro</x-badge>
                        @else
                            <x-badge cor="aviso">Precisa de revisão</x-badge>
                            <span class="text-texto-suave">{{ $item['motivo'] }}</span>
                        @endif
                    </div>
                </div>
                <div class="flex shrink-0 flex-wrap items-center gap-2">
                    @if ($item['situacao'] === \App\Services\Curriculo\LoteImportacaoService::CADASTRADO)
                        <x-botao :href="route('candidatos.show', $item['candidato_id'])" variante="secundario" tamanho="sm">Ver perfil</x-botao>
                    @elseif ($item['situacao'] === \App\Services\Curriculo\LoteImportacaoService::PENDENTE)
                        <x-botao :href="route('importacoes.show', $item['uuid'])" variante="secundario" tamanho="sm">Revisar</x-botao>
                        <form method="POST" action="{{ route('importacoes.destroy', $item['uuid']) }}" data-ajax>
                            @csrf
                            @method('DELETE')
                            <x-botao type="submit" variante="fantasma" tamanho="sm">Descartar<span class="sr-only"> {{ $item['nome_original'] }}</span></x-botao>
                        </form>
                    @endif
                </div>
            </li>
        @endforeach
    </ul>

    @if ($resumo['pendentes'] > 0)
        <x-modal id="escolha-lote" :titulo="$resumo['total'].' currículos lidos'" amplo :abrir-ao-carregar="$abrirEscolha || $errors->any()" class="max-h-[calc(100dvh-2rem)] overflow-y-auto">
            @include('importacoes.partials._escolha-lote', ['contextoEscolha' => 'modal'])
        </x-modal>
        <noscript>
            <section id="escolha-sem-js" class="rounded-card border border-borda bg-superficie p-5" aria-label="Como continuar">
                @include('importacoes.partials._escolha-lote', ['contextoEscolha' => 'sem-js'])
            </section>
        </noscript>
    @endif
@endsection
