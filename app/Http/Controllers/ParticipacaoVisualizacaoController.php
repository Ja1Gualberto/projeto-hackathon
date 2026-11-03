<?php

namespace App\Http\Controllers;

use App\Models\Candidato;
use App\Models\Vaga;
use App\Services\Selecao\VisibilidadeCandidatoService;
use Illuminate\Http\JsonResponse;

class ParticipacaoVisualizacaoController extends Controller
{
    public function show(Vaga $vaga, Candidato $candidato, VisibilidadeCandidatoService $visibilidade): JsonResponse
    {
        abort_unless($visibilidade->participaDoQuadro($vaga, $candidato), 404);
        abort_unless($visibilidade->podeRevelar($vaga, $candidato), 403);

        return response()->json([
            'message' => null,
            'fragments' => [
                'visualizacao-candidato' => view('participacoes.partials._curriculo-analise', [
                    'vaga' => $vaga,
                    'perfil' => $visibilidade->perfilNaVaga($vaga, $candidato),
                ])->render(),
            ],
            'redirect' => null,
        ])->header('Cache-Control', 'private, no-store');
    }
}
