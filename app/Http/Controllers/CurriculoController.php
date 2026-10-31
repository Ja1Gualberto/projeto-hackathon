<?php

namespace App\Http\Controllers;

use App\Models\Candidato;
use App\Services\CandidatoService;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CurriculoController extends Controller
{
    public function show(Candidato $candidato, CandidatoService $candidatos): StreamedResponse
    {
        $curriculo = $candidatos->obterCurriculo($candidato);

        abort_if($curriculo === null, 404);

        return Storage::disk('curriculos')->response($curriculo['arquivo'], $curriculo['nomeOriginal'], [
            'Content-Type' => 'application/pdf',
            'Cache-Control' => 'private, no-store',
        ], 'inline');
    }
}
