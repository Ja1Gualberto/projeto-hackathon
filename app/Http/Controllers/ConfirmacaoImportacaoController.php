<?php

namespace App\Http\Controllers;

use App\Http\Requests\ConfirmarImportacaoRequest;
use App\Models\Candidato;
use App\Services\Curriculo\CadastroCandidatoService;
use App\Services\Curriculo\ImportacaoCurriculoService;
use App\Services\Curriculo\LoteImportacaoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use stdClass;

class ConfirmacaoImportacaoController extends Controller
{
    public function __construct(
        private ImportacaoCurriculoService $importacoes,
        private CadastroCandidatoService $cadastro,
        private LoteImportacaoService $lotes,
    ) {}

    public function store(ConfirmarImportacaoRequest $request, string $importacao): RedirectResponse|JsonResponse
    {
        $dados = $this->importacoes->obter($importacao);

        abort_if($dados === null, 404);

        $candidato = $this->cadastro->confirmar($dados, $request->validated(), $request->user());
        $this->importacoes->descartar($importacao);

        $mensagem = 'Candidato salvo no banco de talentos.';
        $destino = $this->destino($dados['lote'] ?? null, $importacao, $candidato);

        if ($request->expectsJson()) {
            // Dentro de um lote a tela seguinte é outra revisão: o aviso precisa chegar até ela.
            if (isset($dados['lote'])) {
                $request->session()->flash('status', $mensagem);
            }

            return response()->json([
                'message' => $mensagem,
                'fragments' => new stdClass,
                'redirect' => $destino,
            ])->header('Cache-Control', 'private, no-store');
        }

        return redirect()->to($destino)->with('status', $mensagem);
    }

    /**
     * Fora de um lote, segue para o perfil; dentro dele, para o próximo pendente ou para o resumo.
     */
    private function destino(?string $lote, string $importacao, Candidato $candidato): string
    {
        if ($lote === null) {
            return route('candidatos.show', $candidato);
        }

        $this->lotes->marcarCadastrado($lote, $importacao, $candidato);
        $proximo = $this->lotes->proximoPendente($lote, $importacao);

        return $proximo === null ? route('importacoes.lotes.show', $lote) : route('importacoes.show', $proximo);
    }
}
