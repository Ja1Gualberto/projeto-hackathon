<?php

namespace App\Http\Controllers;

use App\DTOs\FiltroCandidatosDTO;
use App\Http\Requests\AdicionarTriagemRequest;
use App\Http\Requests\BuscarCandidatosRequest;
use App\Models\Vaga;
use App\Services\CandidatoService;
use App\Services\Selecao\SelecaoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

class TriagemCandidatoController extends Controller
{
    public function __construct(private CandidatoService $candidatos, private SelecaoService $selecao) {}

    public function index(BuscarCandidatosRequest $request, Vaga $vaga): Response|RedirectResponse
    {
        if (! $vaga->estaAberta()) {
            return redirect()->route('vagas.show', $vaga)->withErrors(['vaga' => 'Vaga encerrada — somente consulta. Não é possível adicionar candidatos.']);
        }

        $filtro = new FiltroCandidatosDTO(
            competencias: $request->filtro()->competencias,
            busca: $request->filtro()->busca,
            excluirVagaId: $vaga->id,
            qualquerCompetencia: true,
            porPagina: 12,
        );

        return response()->view('participacoes.triagem', [
            'vaga' => $vaga,
            'filtro' => $filtro,
            'competenciasSelecionadas' => $request->hasAny(['competencias', 'busca'])
                ? $filtro->competencias
                : $vaga->competencias->modelKeys(),
            'candidatos' => $this->candidatos->buscar($filtro),
            'catalogo' => $this->candidatos->catalogoCompetencias(),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function store(AdicionarTriagemRequest $request, Vaga $vaga): RedirectResponse
    {
        $this->selecao->adicionarATriagem($vaga, array_map('intval', $request->validated('candidatos')));

        return redirect()->route('vagas.show', $vaga)->with('status', 'Candidatos selecionados adicionados à triagem.');
    }
}
