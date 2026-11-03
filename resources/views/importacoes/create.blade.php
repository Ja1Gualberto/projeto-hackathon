@extends('layouts.app')

@section('titulo', 'Importar currículo')

@section('conteudo')
    <x-cabecalho-pagina titulo="Importar currículo" subtitulo="Envie um ou vários PDFs: o sistema lê formação, experiências e competências para você revisar." class="mx-auto max-w-3xl"/>

    <form method="POST" action="{{ route('importacoes.store') }}" enctype="multipart/form-data" data-ajax class="mx-auto flex max-w-3xl flex-col gap-7 rounded-card border border-borda bg-superficie p-6 sm:p-8">
        @csrf
        <div data-ajax-erros role="alert" tabindex="-1" hidden class="rounded-lg border border-red-300 bg-red-50 p-3 text-sm text-red-800"></div>

        @if ($errors->any())
            <x-alerta tipo="erro" titulo="Não foi possível ler o arquivo.">
                <ul class="list-inside list-disc">
                    @foreach ($errors->all() as $erro)<li>{{ $erro }}</li>@endforeach
                </ul>
            </x-alerta>
        @endif

        @php($erroArquivos = $errors->first('arquivos') ?: $errors->first('arquivos.*'))
        <div class="flex flex-col gap-2">
            <label for="arquivos" class="flex cursor-pointer flex-col items-center gap-3 rounded-card border-2 border-dashed border-primaria-300 bg-primaria-50 px-6 py-10 text-center transition-colors hover:border-primaria-500 focus-within:border-primaria-600">
                <span class="flex size-14 items-center justify-center rounded-full bg-superficie text-primaria-700 shadow-sm">
                    <x-icone nome="upload" class="size-7" />
                </span>
                <span class="text-lg font-semibold">Selecione um ou mais currículos em PDF</span>
                <span class="text-sm text-texto-suave">PDF com texto selecionável, até 10 MB cada. Envie até {{ config('banco_talentos.importacao_max_arquivos') }} arquivos por vez.</span>
                <input id="arquivos" type="file" name="arquivos[]" accept="application/pdf,.pdf" multiple required
                    data-arquivos-curriculo data-max-arquivos="{{ config('banco_talentos.importacao_max_arquivos') }}"
                    @if ($erroArquivos) aria-invalid="true" aria-describedby="arquivos-erro" @endif
                    class="mt-2 max-w-full text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-primaria-600 file:px-4 file:py-2 file:font-semibold file:text-white">
            </label>
            <ul data-arquivos-lista hidden aria-label="Arquivos selecionados" class="flex flex-col gap-1 rounded-lg border border-borda bg-fundo p-3 text-sm wrap-anywhere"></ul>
            <p id="arquivos-erro" data-erro-campo="arquivos" @if (! $erroArquivos) hidden @endif class="text-sm font-medium text-red-700">{{ $erroArquivos }}</p>
        </div>

        {{-- Campo desabilitado não é enviado: o campo oculto mantém a vaga de onde a importação partiu. --}}
        @if ($vagaFixa)
            <input type="hidden" name="vaga_id" value="{{ $vagaSelecionada }}">
        @endif
        <x-campo nome="vaga_id" rotulo="Processo seletivo" tipo="select" :valor="$vagaSelecionada" vazio="Selecione a vaga" :opcoes="$vagas->pluck('titulo', 'id')->all()" :disabled="$vagaFixa" obrigatorio ajuda="Todos os currículos enviados serão vinculados à triagem desta vaga após a confirmação. O mesmo candidato pode participar de outros processos." />

        @if ($vagas->isEmpty())
            <x-alerta tipo="aviso">Não há vagas abertas. <a href="{{ route('vagas.create') }}" class="font-semibold underline">Crie um processo seletivo</a> antes de importar os currículos.</x-alerta>
        @endif

        <div class="flex flex-col gap-4 border-t border-borda pt-6 sm:flex-row sm:items-center sm:justify-between">
            <p class="flex items-start gap-2 text-sm text-texto-suave">
                <x-icone nome="cadeado" class="mt-0.5 size-4" />
                O PDF fica em área privada. Nada vai para o banco antes da sua revisão e do registro do consentimento.
            </p>
            <x-botao type="submit" tamanho="lg" class="shrink-0" data-carregando>
                <x-icone nome="documento" class="size-5" />Ler currículo
            </x-botao>
        </div>
    </form>
@endsection
