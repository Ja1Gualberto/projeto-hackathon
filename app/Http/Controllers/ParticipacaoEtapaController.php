<?php

namespace App\Http\Controllers;

use App\Http\Requests\MoverEtapaRequest;
use App\Models\Candidato;
use App\Models\Vaga;
use App\Services\Selecao\SelecaoService;
use App\Services\Selecao\VisibilidadeCandidatoService;
use App\Services\VagaService;
use App\Services\VagaTelaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use stdClass;

class ParticipacaoEtapaController extends Controller
{
    public function __construct(
        private SelecaoService $selecao,
        private VisibilidadeCandidatoService $visibilidade,
        private VagaService $vagas,
        private VagaTelaService $tela,
    ) {}

    /**
     * Move o candidato e devolve os dados do quadro ou o fragmento do perfil no estado final,
     * para nada revelado sobrar na tela ao voltar à triagem.
     */
    public function update(MoverEtapaRequest $request, Vaga $vaga, Candidato $candidato): RedirectResponse|JsonResponse
    {
        $etapa = $request->etapa();
        $this->selecao->moverEtapa($vaga, $candidato, $etapa, $request->user());
        $mensagem = $this->selecao->mensagemDeMovimentacao($candidato, $etapa);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $mensagem,
                'fragments' => $request->vemDoPerfil()
                    ? ['perfil-na-vaga' => view('participacoes.partials._perfil', $this->visibilidade->telaDoPerfil($vaga, $candidato))->render()]
                    : new stdClass,
                ...($request->vemDoPerfil() ? [] : ['data' => ['quadro-vaga' => $this->tela->quadro($this->vagas->quadro($vaga))]]),
                'redirect' => null,
            ])->header('Cache-Control', 'private, no-store');
        }

        $destino = $request->vemDoPerfil()
            ? route('vagas.candidatos.show', [$vaga, $candidato->codigo])
            : route('vagas.show', $vaga);

        return redirect()->to($destino)->with('status', $mensagem);
    }
}
