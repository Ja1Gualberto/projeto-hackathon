@extends('layouts.app')

@section('titulo', $vaga->titulo)

@section('conteudo')
    <nav aria-label="Localização" class="text-sm">
        <a href="{{ route('vagas.index') }}" class="font-medium text-primaria-700 underline underline-offset-4">Todas as vagas</a>
    </nav>

    @foreach (['vaga', 'etapa'] as $campo)
        @error($campo)
            <x-alerta tipo="erro">{{ $message }}</x-alerta>
        @enderror
    @endforeach

    <section id="quadro-vaga" aria-label="Quadro da vaga" class="transition-opacity aria-busy:cursor-progress aria-busy:opacity-70">
        @include('vagas.partials._quadro')
    </section>
@endsection
