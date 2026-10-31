@extends('layouts.visitante')

@section('titulo', 'Página não encontrada')

@section('conteudo')
    <div class="flex flex-col items-center gap-4 text-center">
        <span class="flex size-14 items-center justify-center rounded-full bg-primaria-50 text-primaria-700">
            <x-icone nome="busca" class="size-7" />
        </span>
        <h1 class="text-2xl font-bold text-texto">Página não encontrada</h1>
        <p class="text-texto-suave">
            O endereço pode ter mudado ou o registro não existe mais.
        </p>
        <x-botao :href="url('/')" variante="secundario">
            Ir para o início
        </x-botao>
    </div>
@endsection
