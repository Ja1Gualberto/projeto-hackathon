<?php

namespace App\Http\Controllers;

use App\Models\Vaga;
use App\Services\VagaService;
use App\Services\VagaTelaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use stdClass;

class VagaStatusController extends Controller
{
    public function __construct(private VagaService $vagas, private VagaTelaService $tela) {}

    /**
     * Encerra a vaga e devolve dados para o JavaScript atualizar o quadro.
     */
    public function update(Request $request, Vaga $vaga): RedirectResponse|JsonResponse
    {
        $this->vagas->encerrar($vaga);

        $mensagem = 'Vaga encerrada. O quadro fica disponível para consulta.';

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $mensagem,
                'fragments' => new stdClass,
                'data' => ['quadro-vaga' => $this->tela->quadro($this->vagas->quadro($vaga))],
                'redirect' => null,
            ])->header('Cache-Control', 'private, no-store');
        }

        return redirect()->route('vagas.show', $vaga)->with('status', $mensagem);
    }
}
