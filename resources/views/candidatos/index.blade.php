@extends('layouts.app')

@section('titulo', 'Candidatos')
@section('largura-conteudo', 'max-w-none')

@section('conteudo')
    <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div class="flex flex-col gap-1">
            <h1 class="text-3xl font-bold tracking-tight text-texto">Encontre talentos</h1>
            <p class="text-texto-suave">Um banco de competências para conectar pessoas às necessidades da SETIC.</p>
        </div>
        <div class="flex flex-wrap gap-3">
            <a href="{{ $urlImportacao }}" class="inline-flex items-center justify-center gap-2 rounded-lg font-semibold transition-colors duration-150 disabled:cursor-not-allowed disabled:opacity-60 bg-primaria-600 text-white shadow-sm hover:bg-primaria-700 px-4 py-2 text-sm">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" aria-hidden="true" focusable="false" class="size-5 shrink-0 size-4"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" /></svg>
                Importar currículo
            </a>
        </div>
    </header>

    <div {{ $alertaErros }} data-alerta-tipo="erro" data-alerta-duracao="4500" role="alert" aria-atomic="true" class="alerta alerta--erro">
        <img src="{{ $urlArteErro }}" alt="" aria-hidden="true" width="438" height="174" class="alerta-arte">
        <div class="relative flex min-w-0 flex-col gap-1">
            <p class="text-sm leading-tight font-bold" data-alerta-titulo>Não foi possível concluir</p>
            <div class="text-xs leading-normal wrap-anywhere" data-alerta-mensagem>
                <p class="font-semibold">Revise os filtros para continuar.</p>
                <ul class="mt-2 list-inside list-disc">{{ $errosFiltros }}</ul>
            </div>
        </div>
        <button type="button" data-alerta-fechar aria-label="Fechar alerta" title="Fechar alerta" class="alerta-fechar">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m6 6 12 12M6 18 18 6" stroke-linecap="round" /></svg>
        </button>
    </div>

    <div class="grid items-start gap-6 xl:grid-cols-[15rem_minmax(0,1fr)]">
        {{ $filtrosHtml }}

        <section id="resultados-candidatos" aria-labelledby="resultados-titulo" class="flex min-w-0 flex-col gap-5">
            {{ $resultados }}
        </section>
    </div>
@endsection
