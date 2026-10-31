@extends('layouts.visitante')

@section('titulo', 'Identidade protegida')

@section('conteudo')
    <div class="flex flex-col items-center gap-4 text-center">
        <span class="flex size-14 items-center justify-center rounded-full bg-primaria-50 text-primaria-700">
            <x-icone nome="cadeado" class="size-7" />
        </span>
        <h1 class="text-2xl font-bold text-texto">Identidade protegida na triagem anônima</h1>
        <p class="text-texto-suave">
            Na etapa de triagem, nome, contatos e o currículo original ficam ocultos para que a escolha
            se baseie só nas competências. Esses dados aparecem quando o candidato é movido para a
            entrevista nesta vaga.
        </p>
        <x-botao :href="url()->previous()" variante="secundario">
            Voltar
        </x-botao>
    </div>
@endsection
