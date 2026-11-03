<?php

namespace App\Http\Controllers;

use App\Models\Candidato;
use App\Models\Vaga;
use App\Services\Energia\PerfilEnergiaService;
use App\Services\Selecao\VisibilidadeCandidatoService;
use Illuminate\Http\JsonResponse;

class ParticipacaoEnergiaController extends Controller
{
    public function store(Vaga $vaga, Candidato $candidato, VisibilidadeCandidatoService $visibilidade, PerfilEnergiaService $energia): JsonResponse
    {
        abort_unless($visibilidade->participaDoQuadro($vaga, $candidato), 404);
        abort_unless($visibilidade->podeRevelar($vaga, $candidato), 403);

        if ($energia->analisar($candidato) === null) {
            return response()->json(['message' => 'Análise indisponível no momento.'], 503)
                ->header('Cache-Control', 'private, no-store');
        }

        return response()->json([
            'message' => 'Análise gerada. Revise antes de utilizar.',
            'fragments' => [
                'visualizacao-energia' => view('participacoes.partials._analise-energia', [
                    'vaga' => $vaga,
                    'perfil' => $visibilidade->perfilNaVaga($vaga, $candidato),
                ])->render(),
            ],
            'redirect' => null,
        ])->header('Cache-Control', 'private, no-store');
    }
}
