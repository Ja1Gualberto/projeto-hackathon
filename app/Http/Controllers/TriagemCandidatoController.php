<?php

namespace App\Http\Controllers;

use App\DTOs\FiltroCandidatosDTO;
use App\Http\Requests\AdicionarTriagemRequest;
use App\Http\Requests\BuscarCandidatosRequest;
use App\Models\Vaga;
use App\Services\CandidatoService;
use App\Services\Selecao\SelecaoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class TriagemCandidatoController extends Controller
{
    public function __construct(private CandidatoService $candidatos, private SelecaoService $selecao) {}

    public function index(BuscarCandidatosRequest $request, Vaga $vaga): Response|RedirectResponse|JsonResponse
    {
        if (! $vaga->estaAberta()) {
            $mensagem = 'Vaga encerrada — somente consulta. Não é possível adicionar candidatos.';

            if ($request->expectsJson()) {
                throw ValidationException::withMessages(['vaga' => $mensagem]);
            }

            return redirect()->route('vagas.show', $vaga)->withErrors(['vaga' => $mensagem]);
        }

        $filtro = new FiltroCandidatosDTO(
            competencias: $request->filtro()->competencias,
            busca: $request->filtro()->busca,
            excluirVagaId: $vaga->id,
            qualquerCompetencia: true,
            porPagina: 12,
            somentePorCodigo: true,
        );

        $dados = [
            'vaga' => $vaga,
            'filtro' => $filtro,
            'candidatos' => $this->candidatos->buscar($filtro),
        ];

        if ($request->expectsJson()) {
            return response()->json([
                'message' => null,
                'fragments' => [
                    'resultados-candidatos' => view('participacoes.partials._resultados-triagem', $dados)->render(),
                ],
                'redirect' => null,
            ])->header('Cache-Control', 'private, no-store');
        }

        return response()->view('participacoes.triagem', [
            ...$dados,
            'competenciasSelecionadas' => $request->hasAny(['competencias', 'busca'])
                ? $filtro->competencias
                : $vaga->competencias->modelKeys(),
            'catalogo' => $this->candidatos->catalogoCompetencias(),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function store(AdicionarTriagemRequest $request, Vaga $vaga): RedirectResponse
    {
        if ($request->boolean('todos_filtrados')) {
            $filtro = new FiltroCandidatosDTO(
                competencias: array_map('intval', $request->validated('competencias') ?? []),
                busca: $request->validated('busca'),
                excluirVagaId: $vaga->id,
                qualquerCompetencia: true,
                somentePorCodigo: true,
            );
            $candidatoIds = array_values(array_diff(
                $this->candidatos->buscarIds($filtro),
                array_map('intval', $request->validated('excluidos') ?? []),
            ));
        } else {
            $candidatoIds = array_map('intval', $request->validated('candidatos'));
        }

        $this->selecao->adicionarATriagem($vaga, $candidatoIds);

        return redirect()->route('vagas.show', $vaga)->with('status', 'Candidatos selecionados adicionados à triagem.');
    }
}
