<?php

namespace App\Http\Controllers;

use App\Models\Vaga;
use App\Services\VagaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class VagaStatusController extends Controller
{
    public function __construct(private VagaService $vagas) {}

    /**
     * Encerra a vaga. Em AJAX devolve a região `quadro-vaga` já somente para consulta.
     */
    public function update(Request $request, Vaga $vaga): RedirectResponse|JsonResponse
    {
        $this->vagas->encerrar($vaga);

        $mensagem = 'Vaga encerrada. O quadro fica disponível para consulta.';

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $mensagem,
                'fragments' => ['quadro-vaga' => view('vagas.partials._quadro', $this->vagas->quadro($vaga))->render()],
                'redirect' => null,
            ])->header('Cache-Control', 'private, no-store');
        }

        return redirect()->route('vagas.show', $vaga)->with('status', $mensagem);
    }
}
