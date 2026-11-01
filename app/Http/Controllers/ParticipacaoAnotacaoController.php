<?php

namespace App\Http\Controllers;

use App\Http\Requests\RegistrarAnotacaoRequest;
use App\Models\Candidato;
use App\Models\Vaga;
use App\Services\Anotacoes\AnotacaoService;
use App\Services\Selecao\VisibilidadeCandidatoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class ParticipacaoAnotacaoController extends Controller
{
    public function __construct(
        private AnotacaoService $anotacoes,
        private VisibilidadeCandidatoService $visibilidade,
    ) {}

    /**
     * Anotação pelo perfil na vaga. O 403 da triagem é decidido em RegistrarAnotacaoRequest, antes
     * da validação; a vaga é sempre a da rota (o `vaga_id` do formulário é ignorado). Em AJAX, as
     * regiões voltam montadas a partir do PerfilNaVagaDTO, como na página.
     */
    public function store(RegistrarAnotacaoRequest $request, Vaga $vaga, Candidato $candidato): RedirectResponse|JsonResponse
    {
        $this->anotacoes->registrarNaVaga(
            $vaga,
            $candidato,
            $request->user(),
            $request->validated('texto'),
            $request->competenciasObservadas(),
        );

        $mensagem = 'Anotação registrada.';

        if ($request->expectsJson()) {
            $perfil = $this->visibilidade->perfilNaVaga($vaga, $candidato);

            return response()->json([
                'message' => $mensagem,
                'fragments' => [
                    'historico-anotacoes' => view('anotacoes._historico', ['anotacoes' => $perfil->anotacoes])->render(),
                    'competencias-candidato' => view('participacoes.partials._competencias-na-vaga', ['perfil' => $perfil])->render(),
                ],
                'redirect' => null,
            ])->header('Cache-Control', 'private, no-store');
        }

        return redirect()->route('vagas.candidatos.show', [$vaga, $candidato->codigo])->with('status', $mensagem);
    }
}
