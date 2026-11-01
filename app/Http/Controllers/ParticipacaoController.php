<?php

namespace App\Http\Controllers;

use App\Models\Candidato;
use App\Models\Vaga;
use App\Services\Selecao\VisibilidadeCandidatoService;
use Illuminate\Http\Response;

class ParticipacaoController extends Controller
{
    public function __construct(private VisibilidadeCandidatoService $visibilidade) {}

    /**
     * Perfil no contexto da vaga. A view recebe só o PerfilNaVagaDTO, nunca o model Candidato.
     */
    public function show(Vaga $vaga, Candidato $candidato): Response
    {
        abort_unless($this->visibilidade->participaDoQuadro($vaga, $candidato), 404);

        return response()->view('participacoes.show', $this->visibilidade->telaDoPerfil($vaga, $candidato))
            ->header('Cache-Control', 'private, no-store');
    }
}
