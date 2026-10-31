@extends('layouts.visitante')

@section('titulo', 'Arquivo muito grande')

@section('conteudo')
    {{-- Renderizada automaticamente para PostTooLargeException (status 413). A sessão ainda não
         foi iniciada nesse ponto, então não é possível voltar ao formulário com mensagem flash. --}}
    <div class="flex flex-col items-center gap-4 text-center">
        <span class="flex size-14 items-center justify-center rounded-full bg-amber-50 text-amber-800">
            <x-icone nome="alerta" class="size-7" />
        </span>
        <h1 class="text-2xl font-bold text-texto">O arquivo excede o limite de 10 MB.</h1>
        <p class="text-texto-suave">
            Envie um currículo em PDF de até 10 MB. Se o arquivo for digitalizado, tente exportá-lo
            novamente com texto selecionável.
        </p>
        <x-botao :href="url()->previous()" variante="secundario">
            Voltar
        </x-botao>
    </div>
@endsection
