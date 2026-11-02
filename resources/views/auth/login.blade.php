@extends('layouts.visitante')

@section('titulo', 'Entrar')

@section('conteudo')
    <div class="flex flex-col gap-6">
        <div class="flex flex-col gap-1">
            <h1 class="text-2xl font-bold text-texto">Entrar</h1>
            <p class="text-texto-suave">Acesso restrito à equipe de recrutamento.</p>
        </div>

        @if ($errors->any())
            <x-alerta tipo="erro" titulo="Não foi possível entrar">{{ $errors->first() }}</x-alerta>
        @endif

        <form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-5">
            @csrf

            <x-campo nome="email" rotulo="E-mail" tipo="email" autocomplete="username" autofocus obrigatorio />
            <x-campo nome="password" rotulo="Senha" tipo="password" autocomplete="current-password" obrigatorio />

            <label class="flex items-center gap-2 text-sm text-texto-suave">
                <input type="checkbox" name="lembrar" value="1" class="size-4 rounded border-borda text-primaria-600">
                Manter conectado
            </label>

            <x-botao type="submit" tamanho="lg" class="w-full" data-carregando>
                Entrar
                <x-icone nome="seta" class="size-4" />
            </x-botao>
        </form>

        <p class="text-center text-sm text-texto-suave">
            Ambiente de demonstração — dados fictícios.
        </p>
    </div>
@endsection
