@extends('layouts.app')

@section('titulo', 'Nova vaga')

@section('conteudo')
    <script type="application/json" id="dados-vagas" data-tela="formulario">{!! $dadosTela !!}</script>
    <nav aria-label="Localização" class="text-sm">
        <a href="{{ route('vagas.index') }}" class="font-medium text-primaria-700 underline underline-offset-4">Todas as vagas</a>
    </nav>

    <x-cabecalho-pagina titulo="Nova vaga" subtitulo="Descreva o perfil procurado. O ranking dos candidatos elegíveis aparece logo depois de salvar." />

    <form method="POST" action="{{ route('vagas.store') }}" data-ajax data-formulario-vaga hidden class="flex flex-col gap-8 rounded-card border border-borda bg-superficie p-6 sm:p-8" aria-label="Cadastro de vaga">
        @csrf
        @include('vagas.partials._formulario')

        <div class="flex flex-col gap-3 border-t border-borda pt-6 sm:flex-row sm:items-center sm:justify-end">
            <x-botao :href="route('vagas.index')" variante="fantasma">Cancelar</x-botao>
            <x-botao type="submit" tamanho="lg"><x-icone nome="check" class="size-5" />Salvar e ver ranking</x-botao>
        </div>
    </form>
    <noscript>Ative o JavaScript para cadastrar a vaga.</noscript>
@endsection
