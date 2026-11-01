<?php

namespace App\Http\Controllers;

use App\Http\Requests\MoverEtapaRequest;
use App\Models\Candidato;
use App\Models\Vaga;
use App\Services\Selecao\SelecaoService;
use App\Services\Selecao\VisibilidadeCandidatoService;
use App\Services\VagaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class ParticipacaoEtapaController extends Controller
{
    public function __construct(
        private SelecaoService $selecao,
        private VisibilidadeCandidatoService $visibilidade,
        private VagaService $vagas,
    ) {}

    /**
     * Move o candidato e, em AJAX, devolve a região inteira já no estado posterior à movimentação
     * (`quadro-vaga` ou `perfil-na-vaga`), para nada revelado sobrar na tela ao voltar à triagem.
     */
    public function update(MoverEtapaRequest $request, Vaga $vaga, Candidato $candidato): RedirectResponse|JsonResponse
    {
        $etapa = $request->etapa();
        $this->selecao->moverEtapa($vaga, $candidato, $etapa, $request->user());
        $mensagem = $this->selecao->mensagemDeMovimentacao($candidato, $etapa);

        if ($request->expectsJson()) {
            $fragmento = $request->vemDoPerfil()
                ? ['perfil-na-vaga' => view('participacoes.partials._perfil', $this->visibilidade->telaDoPerfil($vaga, $candidato))->render()]
                : ['quadro-vaga' => view('vagas.partials._quadro', $this->vagas->quadro($vaga))->render()];

            return response()->json([
                'message' => $mensagem,
                'fragments' => $fragmento,
                'redirect' => null,
            ])->header('Cache-Control', 'private, no-store');
        }

        $destino = $request->vemDoPerfil()
            ? route('vagas.candidatos.show', [$vaga, $candidato->codigo])
            : route('vagas.show', $vaga);

        return redirect()->to($destino)->with('status', $mensagem);
    }
}
