@extends('layouts.app')

@section('titulo', 'Revisar currículo')

@section('conteudo')
    <nav aria-label="Localização" class="text-sm">
        <a href="{{ route('importacoes.create') }}" class="font-medium text-primaria-700 underline underline-offset-4">Enviar outro currículo</a>
    </nav>

    <x-cabecalho-pagina titulo="Revise o currículo" subtitulo="Confira o que foi lido, corrija o que for preciso e registre o consentimento. Nada é salvo antes da confirmação." />

    @if ($situacao === \App\DTOs\ResultadoLeituraDTO::SEM_TEXTO)
        <x-alerta tipo="aviso" titulo="Leitura manual">Não conseguimos ler o texto deste PDF (parece digitalizado). Preencha os dados manualmente.</x-alerta>
    @elseif ($situacao === \App\DTOs\ResultadoLeituraDTO::CORROMPIDO)
        <x-alerta tipo="aviso" titulo="Leitura manual">O arquivo parece corrompido. O PDF foi guardado; preencha os dados manualmente.</x-alerta>
    @else
        <x-alerta tipo="info">Leitura automática concluída. Os campos abaixo são sugestões: revise antes de salvar.</x-alerta>
    @endif

    <div class="grid items-start gap-8 xl:grid-cols-[minmax(0,1fr)_20rem]">
        <form method="POST" action="{{ route('importacoes.confirmar', $uuid) }}" data-ajax class="flex min-w-0 flex-col gap-8 rounded-card border border-borda bg-superficie p-6 sm:p-8" aria-label="Revisão do currículo">
            @csrf
            <div data-ajax-erros role="alert" tabindex="-1" hidden class="rounded-lg border border-red-300 bg-red-50 p-3 text-sm text-red-800"></div>

            @if ($errors->any())
                <x-alerta tipo="erro" titulo="Revise os campos indicados para salvar.">
                    <ul class="list-inside list-disc">
                        @foreach ($errors->all() as $erro)<li>{{ $erro }}</li>@endforeach
                    </ul>
                </x-alerta>
            @endif

            @if ($duplicado)
                <section aria-labelledby="duplicidade-titulo" class="flex flex-col gap-4 rounded-card border border-amber-300 bg-amber-50 p-5">
                    <div class="flex items-start gap-3">
                        <x-icone nome="alerta" class="mt-0.5 text-amber-800" />
                        <div>
                            <h2 id="duplicidade-titulo" class="font-semibold text-amber-900">Possível cadastro duplicado</h2>
                            <p class="mt-1 text-sm text-amber-900">
                                Já existe um cadastro com o mesmo e-mail ou CPF:
                                <a href="{{ route('candidatos.show', $duplicado) }}" target="_blank" rel="noopener" class="font-semibold underline underline-offset-4">{{ $duplicado->nome }}<span class="sr-only"> (abre em nova aba)</span></a>.
                            </p>
                        </div>
                    </div>
                    <input type="hidden" name="candidato_existente_id" value="{{ $duplicado->id }}">
                    <fieldset class="flex flex-col gap-2 pl-8">
                        <legend class="sr-only">O que fazer com este currículo</legend>
                        <label class="flex cursor-pointer items-start gap-3">
                            <input type="radio" id="modo-atualizar" name="modo" value="atualizar" @checked(old('modo', 'atualizar') === 'atualizar') class="mt-1 size-4 accent-primaria-600">
                            <span><span class="font-semibold">Atualizar cadastro existente</span><span class="block text-sm text-texto-suave">Mantém o código, as participações e as anotações; substitui o PDF.</span></span>
                        </label>
                        <label class="flex cursor-pointer items-start gap-3">
                            <input type="radio" id="modo-novo" name="modo" value="novo" @checked(old('modo') === 'novo') class="mt-1 size-4 accent-primaria-600">
                            <span><span class="font-semibold">Criar novo mesmo assim</span><span class="block text-sm text-texto-suave">Use quando forem pessoas diferentes.</span></span>
                        </label>
                    </fieldset>
                    <p id="modo-erro" data-erro-campo="candidato_existente_id" @if (! $errors->has('candidato_existente_id')) hidden @endif class="pl-8 text-sm font-medium text-red-700">{{ $errors->first('candidato_existente_id') }}</p>
                </section>
            @else
                <input type="hidden" name="modo" value="novo">
            @endif

            @include('candidatos.partials._formulario')

            <div class="flex flex-col gap-3 border-t border-borda pt-6 sm:flex-row sm:items-center sm:justify-end">
                <x-botao :href="route('importacoes.create')" variante="fantasma">Cancelar</x-botao>
                <x-botao type="submit" tamanho="lg"><x-icone nome="check" class="size-5" />Confirmar e salvar</x-botao>
            </div>
        </form>

        <aside class="flex min-w-0 flex-col gap-6" aria-label="Arquivo e sugestões">
            <section class="rounded-card border border-borda bg-superficie p-5">
                <h2 class="mb-3 font-semibold">Arquivo enviado</h2>
                <p class="flex items-start gap-2 text-sm wrap-anywhere">
                    <x-icone nome="documento" class="mt-0.5 size-4 text-texto-suave" />
                    {{ $importacao['nome_original'] }}
                </p>
                <p class="mt-1 text-sm text-texto-suave">{{ number_format($importacao['tamanho'] / 1024, 0, ',', '.') }} KB · guardado em área privada</p>
            </section>

            <section aria-labelledby="sugestoes-titulo" class="rounded-card border border-borda bg-superficie p-5">
                <h2 id="sugestoes-titulo" class="font-semibold">Fora do catálogo</h2>
                <p class="mt-1 text-sm leading-relaxed text-texto-suave">Termos do currículo que ainda não são competências padronizadas. Inclua os que fizerem sentido; os demais serão ignorados.</p>
                @if ($sugestoes === [])
                    <p class="mt-4 text-sm text-texto-suave">Nenhum termo novo encontrado.</p>
                @else
                    <ul class="mt-4 flex flex-col gap-4">
                        @foreach ($sugestoes as $sugestao)
                            <li data-sugestao class="flex flex-col gap-2 border-t border-borda pt-4 first:border-t-0 first:pt-0">
                                <span class="font-semibold wrap-anywhere">{{ $sugestao }}</span>
                                {{-- Mini-formulário fora do formulário principal: incluir não apaga a revisão. --}}
                                <form method="POST" action="{{ route('competencias.store') }}" data-ajax data-ajax-mesclar="catalogo-competencias" data-sugestao-form class="flex flex-wrap items-end gap-2">
                                    @csrf
                                    <input type="hidden" name="nome" value="{{ $sugestao }}">
                                    <input type="hidden" name="importacao" value="{{ $uuid }}">
                                    <label for="categoria-sugestao-{{ $loop->index }}" class="sr-only">Categoria de {{ $sugestao }}</label>
                                    <select id="categoria-sugestao-{{ $loop->index }}" name="categoria" class="rounded-lg border border-borda-campo bg-superficie px-2 py-1.5 text-sm">
                                        @foreach (\App\Enums\CategoriaCompetencia::cases() as $categoria)
                                            <option value="{{ $categoria->value }}">{{ $categoria === \App\Enums\CategoriaCompetencia::Hard ? 'Técnica' : 'Comportamental' }}</option>
                                        @endforeach
                                    </select>
                                    <x-botao type="submit" variante="secundario" tamanho="sm">Adicionar ao catálogo</x-botao>
                                    <button type="button" data-ignorar-sugestao class="rounded-lg px-2 py-1.5 text-sm font-semibold text-texto-suave hover:bg-slate-100">Ignorar<span class="sr-only"> {{ $sugestao }}</span></button>
                                </form>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        </aside>
    </div>
@endsection
