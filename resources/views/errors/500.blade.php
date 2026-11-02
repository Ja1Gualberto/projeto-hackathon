@extends('layouts.visitante')

@section('titulo', 'Erro inesperado')

@section('conteudo')
    <div class="flex flex-col items-center gap-4 text-center">
        <span class="flex size-14 items-center justify-center rounded-full bg-amber-50 text-amber-800">
            <x-icone nome="alerta" class="size-7" />
        </span>
        <h1 class="text-2xl font-bold text-texto">Não foi possível concluir a operação</h1>
        <p class="text-texto-suave">
            Ocorreu um erro inesperado. Se você estava salvando algo, confira o estado do registro
            antes de repetir a ação.
        </p>
        <x-botao :href="url('/')" variante="secundario">
            Ir para o início
        </x-botao>
    </div>
@endsection
