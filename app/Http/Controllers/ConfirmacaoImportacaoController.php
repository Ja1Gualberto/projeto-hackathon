<?php

namespace App\Http\Controllers;

use App\Http\Requests\ConfirmarImportacaoRequest;
use App\Services\Curriculo\CadastroCandidatoService;
use App\Services\Curriculo\ImportacaoCurriculoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use stdClass;

class ConfirmacaoImportacaoController extends Controller
{
    public function __construct(
        private ImportacaoCurriculoService $importacoes,
        private CadastroCandidatoService $cadastro,
    ) {}

    public function store(ConfirmarImportacaoRequest $request, string $importacao): RedirectResponse|JsonResponse
    {
        $dados = $this->importacoes->obter($importacao);

        abort_if($dados === null, 404);

        $candidato = $this->cadastro->confirmar($dados, $request->validated(), $request->user());
        $this->importacoes->descartar($importacao);

        $mensagem = 'Candidato salvo no banco de talentos.';
        $destino = route('candidatos.show', $candidato);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $mensagem,
                'fragments' => new stdClass,
                'redirect' => $destino,
            ])->header('Cache-Control', 'private, no-store');
        }

        return redirect()->to($destino)->with('status', $mensagem);
    }
}
