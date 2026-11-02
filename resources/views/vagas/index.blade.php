@extends('layouts.app')

@section('titulo', 'Vagas')

@section('conteudo')
    <script type="application/json" id="dados-vagas" data-tela="lista">{!! $dadosTela !!}</script>
    <x-cabecalho-pagina titulo="Vagas" subtitulo="Defina as competências de cada vaga e veja quem do banco mais se aproxima delas.">
        <x-botao :href="route('vagas.create')"><x-icone nome="vaga" class="size-4" />Nova vaga</x-botao>
    </x-cabecalho-pagina>

    <div data-lista-vazia hidden>
        <x-estado-vazio icone="vaga" titulo="Nenhuma vaga cadastrada" texto="Cadastre uma vaga com as competências e os pesos desejados para ver o ranking dos candidatos elegíveis.">
            <x-botao :href="route('vagas.create')">Cadastrar a primeira vaga</x-botao>
        </x-estado-vazio>
    </div>
    <ul data-lista-vagas class="grid gap-4 md:grid-cols-2 xl:grid-cols-3" aria-label="Vagas cadastradas"></ul>

    <template id="modelo-vaga">
        <li class="flex flex-col gap-4 rounded-card border border-borda p-5 transition-shadow hover:shadow-sm motion-reduce:transition-none">
            <div class="flex flex-col gap-1">
                <h2 class="text-xl leading-snug font-semibold tracking-tight"><a data-titulo href="#" class="hover:text-primaria-700 hover:underline focus-visible:text-primaria-700"></a></h2>
                <p data-area class="text-sm text-texto-suave"></p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <x-badge data-status />
                <x-badge cor="primaria" data-tipo />
                <span data-competencias-total class="text-sm text-texto-suave"></span>
            </div>
            <dl class="mt-auto grid grid-cols-2 gap-3 border-t border-borda pt-4 text-sm">
                <div class="col-span-2"><dt class="sr-only">Candidatos elegíveis</dt><dd data-elegiveis class="font-semibold"></dd></div>
                <div><dt class="text-texto-suave">Em entrevista</dt><dd data-entrevista class="font-semibold tabular-nums"></dd></div>
                <div><dt class="text-texto-suave">Aprovados</dt><dd data-aprovado class="font-semibold tabular-nums"></dd></div>
            </dl>
            <x-botao href="#" variante="secundario" class="w-full" data-abrir-quadro />
        </li>
    </template>
    <noscript>Ative o JavaScript para consultar as vagas.</noscript>
@endsection
