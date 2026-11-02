@extends('layouts.app')

@section('titulo', 'Vagas')

@section('conteudo')
    <x-cabecalho-pagina titulo="Vagas" subtitulo="Defina as competências de cada vaga e veja quem do banco mais se aproxima delas.">
        <x-botao :href="route('vagas.create')"><x-icone nome="vaga" class="size-4" />Nova vaga</x-botao>
    </x-cabecalho-pagina>

    @if ($vagas->isEmpty())
        <x-estado-vazio icone="vaga" titulo="Nenhuma vaga cadastrada" texto="Cadastre uma vaga com as competências e os pesos desejados para ver o ranking dos candidatos elegíveis.">
            <x-botao :href="route('vagas.create')">Cadastrar a primeira vaga</x-botao>
        </x-estado-vazio>
    @else
        <ul class="grid gap-4 md:grid-cols-2 xl:grid-cols-3" aria-label="Vagas cadastradas">
            @foreach ($vagas as $vaga)
                @php($totalElegiveis = $elegiveis[$vaga->id] ?? 0)
                <li @class(['flex flex-col gap-4 rounded-card border border-borda p-5 transition-shadow hover:shadow-sm motion-reduce:transition-none', 'bg-superficie' => $vaga->estaAberta(), 'border-dashed bg-fundo' => ! $vaga->estaAberta()])>
                    <div class="flex flex-col gap-1">
                        <h2 class="text-xl leading-snug font-semibold tracking-tight">
                            <a href="{{ route('vagas.show', $vaga) }}" class="hover:text-primaria-700 hover:underline focus-visible:text-primaria-700">{{ $vaga->titulo }}</a>
                        </h2>
                        <p class="text-sm text-texto-suave">{{ $vaga->area->rotulo() }}</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <x-badge :cor="$vaga->estaAberta() ? 'sucesso' : 'neutro'">{{ $vaga->status->rotulo() }}</x-badge>
                        <x-badge cor="primaria">{{ $vaga->tipo_candidato->rotulo() }}</x-badge>
                        <span class="text-sm text-texto-suave">{{ $vaga->competencias_count }} {{ $vaga->competencias_count === 1 ? 'competência' : 'competências' }}</span>
                    </div>
                    <dl class="mt-auto grid grid-cols-2 gap-3 border-t border-borda pt-4 text-sm">
                        <div class="col-span-2">
                            <dt class="sr-only">Candidatos elegíveis</dt>
                            <dd class="font-semibold">{{ $totalElegiveis }} {{ $totalElegiveis === 1 ? 'candidato elegível' : 'candidatos elegíveis' }}</dd>
                        </div>
                        <div>
                            <dt class="text-texto-suave">Em entrevista</dt>
                            <dd class="font-semibold tabular-nums">{{ $vaga->entrevista_count }}</dd>
                        </div>
                        <div>
                            <dt class="text-texto-suave">Aprovados</dt>
                            <dd class="font-semibold tabular-nums">{{ $vaga->aprovado_count }}</dd>
                        </div>
                    </dl>
                    <x-botao :href="route('vagas.show', $vaga)" variante="secundario" class="w-full">
                        {{ $vaga->estaAberta() ? 'Abrir quadro' : 'Consultar quadro' }}<span class="sr-only"> de {{ $vaga->titulo }}</span>
                    </x-botao>
                </li>
            @endforeach
        </ul>
    @endif
@endsection
