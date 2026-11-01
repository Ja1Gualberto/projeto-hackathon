<?php

namespace App\Http\Controllers;

use App\Enums\StatusCandidato;
use App\Http\Requests\AlterarStatusCandidatoRequest;
use App\Models\Candidato;
use App\Services\CandidatoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class CandidatoStatusController extends Controller
{
    public function __construct(private CandidatoService $candidatos) {}

    public function update(AlterarStatusCandidatoRequest $request, Candidato $candidato): RedirectResponse|JsonResponse
    {
        $status = StatusCandidato::from($request->validated('status'));
        $this->candidatos->alterarStatus($candidato, $status);
        $mensagem = $status === StatusCandidato::Desativado
            ? 'Candidato desativado. Ele não aparece em buscas e rankings, mas os dados continuam guardados.'
            : 'Candidato reativado.';

        if ($request->expectsJson()) {
            $dados = ['candidato' => $this->candidatos->detalhar($candidato)];

            return response()->json([
                'message' => $mensagem,
                'fragments' => [
                    'situacao-candidato' => view('candidatos.partials._situacao', $dados)->render(),
                    'secao-lgpd' => view('candidatos.partials._secao-lgpd', $dados)->render(),
                ],
                'redirect' => null,
            ])->header('Cache-Control', 'private, no-store');
        }

        return redirect()->route('candidatos.show', $candidato)->with('status', $mensagem);
    }
}
