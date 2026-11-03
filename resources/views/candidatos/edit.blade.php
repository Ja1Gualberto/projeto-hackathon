@extends('layouts.app')

@section('titulo', 'Corrigir dados do candidato')

@section('conteudo')
    <nav aria-label="Localização" class="text-sm">
        <a href="{{ route('candidatos.show', $candidato) }}" class="inline-flex items-center gap-2 font-medium text-primaria-700 underline underline-offset-4">
            <x-icone nome="seta" class="size-4 rotate-180" />
            Voltar ao perfil
        </a>
    </nav>
    <x-cabecalho-pagina titulo="Corrigir dados do candidato" subtitulo="Confira o cadastro e o consentimento. Salvar renova o prazo de retenção dos dados." />

    <form method="POST" action="{{ route('candidatos.update', $candidato) }}" data-ajax class="flex max-w-4xl flex-col gap-8 rounded-card border border-borda bg-superficie p-6 sm:p-8" aria-label="Correção dos dados do candidato">
        @csrf
        @method('PUT')
        <div data-ajax-erros role="alert" tabindex="-1" hidden class="rounded-lg border border-red-300 bg-red-50 p-3 text-sm text-red-800"></div>
        @if ($errors->any())
            <x-alerta tipo="erro" titulo="Revise os campos indicados para salvar.">
                <ul class="list-inside list-disc">
                    @foreach ($errors->all() as $erro)<li>{{ $erro }}</li>@endforeach
                </ul>
            </x-alerta>
        @endif

        @include('candidatos.partials._formulario', [
            'valores' => $candidato->only(['nome', 'email', 'telefone', 'cpf', 'data_nascimento', 'area', 'experiencia_na_area', 'formacoes', 'experiencias', 'projetos', 'links', 'consentimento_meio', 'consentimento_em']),
            'selecionadas' => $candidato->competencias->modelKeys(),
        ])

        <div class="flex flex-wrap justify-end gap-3 border-t border-borda pt-6">
            <x-botao :href="route('candidatos.show', $candidato)" variante="fantasma">Cancelar</x-botao>
            <x-botao type="submit">Salvar correções</x-botao>
        </div>
    </form>
@endsection
