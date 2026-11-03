@extends('layouts.app')

@section('titulo', $vaga->titulo)
@section('largura-conteudo', 'max-w-[100rem] 2xl:w-[94%]')

@section('conteudo')
    <script type="application/json" id="dados-vagas" data-tela="quadro">{!! $dadosTela !!}</script>
    <nav aria-label="Localização" class="text-sm">
        <a href="{{ route('vagas.index') }}" class="font-medium text-primaria-700 underline underline-offset-4">Todas as vagas</a>
    </nav>

    <section id="quadro-vaga" aria-label="Quadro da vaga" class="transition-opacity aria-busy:cursor-progress aria-busy:opacity-70"></section>
    @include('vagas.partials._quadro')
    @include('vagas.partials._coluna')
    @include('vagas.partials._card-ranking')
    @include('participacoes.partials._confirmar-entrevista')
    @include('vagas.partials._anotacao-entrevista')
    @include('vagas.partials._curriculo-analise')
    <noscript>Ative o JavaScript para consultar o quadro da vaga.</noscript>
@endsection
