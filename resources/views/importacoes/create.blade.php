@extends('layouts.app')

@section('titulo', 'Importar currículo')

@section('conteudo')
    <x-cabecalho-pagina titulo="Importar currículo" subtitulo="Envie o PDF: o sistema lê formação, experiências e competências para você revisar." />

    <form method="POST" action="{{ route('importacoes.store') }}" enctype="multipart/form-data" data-ajax class="flex max-w-3xl flex-col gap-7 rounded-card border border-borda bg-superficie p-6 sm:p-8">
        @csrf
        <div data-ajax-erros role="alert" tabindex="-1" hidden class="rounded-lg border border-red-300 bg-red-50 p-3 text-sm text-red-800"></div>

        @if ($errors->any())
            <x-alerta tipo="erro" titulo="Não foi possível ler o arquivo.">
                <ul class="list-inside list-disc">
                    @foreach ($errors->all() as $erro)<li>{{ $erro }}</li>@endforeach
                </ul>
            </x-alerta>
        @endif

        <div class="flex flex-col gap-2">
            <label for="arquivo" class="flex cursor-pointer flex-col items-center gap-3 rounded-card border-2 border-dashed border-primaria-300 bg-primaria-50 px-6 py-10 text-center transition-colors hover:border-primaria-500 focus-within:border-primaria-600">
                <span class="flex size-14 items-center justify-center rounded-full bg-superficie text-primaria-700 shadow-sm">
                    <x-icone nome="upload" class="size-7" />
                </span>
                <span class="text-lg font-semibold">Selecione o currículo em PDF</span>
                <span class="text-sm text-texto-suave">PDF com texto selecionável, até 10 MB</span>
                <input id="arquivo" type="file" name="arquivo" accept="application/pdf,.pdf" required
                    @error('arquivo') aria-invalid="true" aria-describedby="arquivo-erro" @enderror
                    class="mt-2 max-w-full text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-primaria-600 file:px-4 file:py-2 file:font-semibold file:text-white">
            </label>
            <p id="arquivo-erro" data-erro-campo="arquivo" @error('arquivo') @else hidden @enderror class="text-sm font-medium text-red-700">@error('arquivo'){{ $message }}@enderror</p>
        </div>

        <fieldset class="flex flex-col gap-3">
            <legend class="mb-2 font-semibold">O candidato é…</legend>
            <div class="grid gap-4 sm:grid-cols-2">
                @foreach (\App\Enums\TipoCandidato::cases() as $tipo)
                    <label class="relative cursor-pointer">
                        <input type="radio" id="tipo-{{ $tipo->value }}" name="tipo" value="{{ $tipo->value }}" required @checked(old('tipo') === $tipo->value) class="peer sr-only">
                        <span @class([
                            'flex h-full items-start gap-4 rounded-card border-2 border-borda bg-superficie p-5 transition-colors peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-primaria-600',
                            'hover:border-servidor-400 peer-checked:border-servidor-600 peer-checked:bg-servidor-50' => $tipo === \App\Enums\TipoCandidato::Servidor,
                            'hover:border-estagiario-400 peer-checked:border-estagiario-600 peer-checked:bg-estagiario-50' => $tipo === \App\Enums\TipoCandidato::Estagiario,
                        ])>
                            <span @class([
                                'flex size-11 shrink-0 items-center justify-center rounded-xl',
                                'bg-servidor-100 text-servidor-800' => $tipo === \App\Enums\TipoCandidato::Servidor,
                                'bg-estagiario-100 text-estagiario-800' => $tipo === \App\Enums\TipoCandidato::Estagiario,
                            ])>
                                <x-icone :nome="$tipo->icone()" class="size-6" />
                            </span>
                            <span class="flex flex-col gap-1">
                                <span class="text-lg font-semibold">{{ $tipo->rotulo() }}</span>
                                <span class="text-sm text-texto-suave">{{ $tipo === \App\Enums\TipoCandidato::Servidor ? 'Integra o quadro do serviço público.' : 'Indicado pelo CIEE ou instituição de ensino.' }}</span>
                            </span>
                        </span>
                    </label>
                @endforeach
            </div>
            <p id="tipo-erro" data-erro-campo="tipo" @error('tipo') @else hidden @enderror class="text-sm font-medium text-red-700">@error('tipo'){{ $message }}@enderror</p>
        </fieldset>

        <div class="flex flex-col gap-4 border-t border-borda pt-6 sm:flex-row sm:items-center sm:justify-between">
            <p class="flex items-start gap-2 text-sm text-texto-suave">
                <x-icone nome="cadeado" class="mt-0.5 size-4" />
                O PDF fica em área privada. Nada vai para o banco antes da sua revisão e do registro do consentimento.
            </p>
            <x-botao type="submit" tamanho="lg" class="shrink-0">
                <x-icone nome="documento" class="size-5" />Ler currículo
            </x-botao>
        </div>
    </form>
@endsection
