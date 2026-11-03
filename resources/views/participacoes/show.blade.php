@extends('layouts.app')

{{-- O título da aba nunca leva o nome: ele continuaria lá depois de uma volta à triagem por AJAX. --}}
@section('titulo', 'Candidato na vaga')

@section('conteudo')
    <nav aria-label="Localização" class="text-sm">
        <a href="{{ route('vagas.show', $vaga) }}" class="font-medium text-primaria-700 underline underline-offset-4">Voltar ao quadro de {{ $vaga->titulo }}</a>
    </nav>

    @error('etapa')
        <x-alerta tipo="erro">{{ $message }}</x-alerta>
    @enderror

    <section id="perfil-na-vaga" aria-label="Perfil do candidato nesta vaga" class="transition-opacity aria-busy:cursor-progress aria-busy:opacity-70">
        @include('participacoes.partials._perfil')
    </section>
    @include('participacoes.partials._confirmar-entrevista')
@endsection
