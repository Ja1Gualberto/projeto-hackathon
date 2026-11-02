@extends('layouts.visitante')

@section('titulo', 'Sessão expirada')

@section('conteudo')
    {{-- Também aparece em envio sem JavaScript com token inválido: nada foi gravado. --}}
    <div class="flex flex-col items-center gap-4 text-center">
        <span class="flex size-14 items-center justify-center rounded-full bg-amber-50 text-amber-800">
            <x-icone nome="alerta" class="size-7" />
        </span>
        <h1 class="text-2xl font-bold text-texto">Sua sessão expirou</h1>
        <p class="text-texto-suave">
            Por segurança, este envio não foi processado. Volte, atualize a página e confira os dados
            antes de tentar novamente.
        </p>
        <x-botao :href="url()->previous()" variante="secundario">
            Voltar
        </x-botao>
    </div>
@endsection
