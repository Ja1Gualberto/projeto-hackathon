@extends('layouts.visitante')

@section('titulo', 'Sistema indisponível')

@section('conteudo')
    <div class="flex flex-col items-center gap-4 text-center">
        <span class="flex size-14 items-center justify-center rounded-full bg-amber-50 text-amber-800">
            <x-icone nome="alerta" class="size-7" />
        </span>
        <h1 class="text-2xl font-bold text-texto">Sistema temporariamente indisponível</h1>
        <p class="text-texto-suave">
            Tente novamente em alguns instantes.
        </p>
    </div>
@endsection
