<?php

namespace App\Http\Controllers;

use App\Models\Candidato;
use App\Models\Vaga;
use App\Services\CandidatoService;
use App\Services\Selecao\VisibilidadeCandidatoService;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ParticipacaoCurriculoController extends Controller
{
    /**
     * PDF original no contexto da vaga: 403 enquanto o candidato estiver em triagem nesta vaga,
     * inclusive por acesso direto (FR-023).
     */
    public function show(Vaga $vaga, Candidato $candidato, VisibilidadeCandidatoService $visibilidade, CandidatoService $candidatos): StreamedResponse
    {
        abort_unless($visibilidade->podeRevelar($vaga, $candidato), 403);

        $curriculo = $candidatos->obterCurriculo($candidato);

        abort_if($curriculo === null, 404);

        return Storage::disk('curriculos')->response($curriculo['arquivo'], $curriculo['nomeOriginal'], [
            'Content-Type' => 'application/pdf',
            'Cache-Control' => 'private, no-store',
        ], 'inline');
    }
}
