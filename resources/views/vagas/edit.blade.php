@extends('layouts.app')

@section('titulo', 'Editar vaga')

@section('conteudo')
    <script type="application/json" id="dados-vagas" data-tela="formulario">{!! $dadosTela !!}</script>
    <nav aria-label="Localização" class="text-sm">
        <a href="{{ route('vagas.show', $vaga) }}" class="font-medium text-primaria-700 underline underline-offset-4">Voltar ao quadro da vaga</a>
    </nav>

    <x-cabecalho-pagina titulo="Editar vaga" subtitulo="Ao salvar, o ranking é recalculado com as competências e os pesos novos." />

    <form method="POST" action="{{ route('vagas.update', $vaga) }}" data-ajax data-formulario-vaga hidden class="flex flex-col gap-8 rounded-card border border-borda bg-superficie p-6 sm:p-8" aria-label="Edição da vaga">
        @csrf
        @method('PUT')
        @include('vagas.partials._formulario')

        <div class="flex flex-col gap-3 border-t border-borda pt-6 sm:flex-row sm:items-center sm:justify-end">
            <x-botao :href="route('vagas.show', $vaga)" variante="fantasma">Cancelar</x-botao>
            <x-botao type="submit" tamanho="lg"><x-icone nome="check" class="size-5" />Salvar alterações</x-botao>
        </div>
    </form>
    <noscript>Ative o JavaScript para editar a vaga.</noscript>
@endsection
