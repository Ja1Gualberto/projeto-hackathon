@extends('layouts.app')

@section('titulo', 'Importação não encontrada')

@section('conteudo')
    <x-estado-vazio icone="documento" titulo="Esta importação não está mais disponível" texto="A revisão expirou, já foi confirmada ou foi aberta em outra sessão. Nenhum dado foi salvo a partir dela.">
        <x-botao :href="route('importacoes.create')"><x-icone nome="upload" class="size-4" />Enviar novamente</x-botao>
    </x-estado-vazio>
@endsection
