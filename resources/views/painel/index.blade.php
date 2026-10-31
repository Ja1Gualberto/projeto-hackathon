@extends('layouts.app')

@section('titulo', 'Painel')

{{-- Provisória (Fase 2). Substituída pela versão com indicadores em T055 (US1). --}}
@section('conteudo')
    <x-cabecalho-pagina titulo="Painel" subtitulo="Visão geral do banco de talentos." />

    <x-estado-vazio
        icone="painel"
        titulo="Painel em construção"
        texto="Os indicadores do banco de talentos (total, servidores × estagiários, vagas abertas e competências mais frequentes) chegam com a busca de candidatos."
    />
@endsection
