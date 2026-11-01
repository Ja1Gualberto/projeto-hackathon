<?php

namespace App\Http\Controllers;

use App\Http\Requests\AjustarPerfilEnergiaRequest;
use App\Models\Candidato;
use App\Services\CandidatoService;
use App\Services\Energia\PerfilEnergiaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PerfilEnergiaController extends Controller
{
    public function __construct(private PerfilEnergiaService $energia, private CandidatoService $candidatos) {}

    public function store(Request $request, Candidato $candidato): RedirectResponse|JsonResponse
    {
        if ($this->energia->analisar($candidato) === null) {
            $mensagem = 'Análise indisponível no momento.';

            return $request->expectsJson()
                ? response()->json(['message' => $mensagem], 503)->header('Cache-Control', 'private, no-store')
                : redirect()->route('candidatos.show', $candidato)->with('status', $mensagem);
        }

        return $this->responder($request, $candidato, 'Perfil sugerido pela IA. Revise antes de utilizar.');
    }

    public function update(AjustarPerfilEnergiaRequest $request, Candidato $candidato): RedirectResponse|JsonResponse
    {
        $this->energia->ajustar($candidato, $request->eixos(), $request->validated('justificativa'), $request->user());

        return $this->responder($request, $candidato, 'Padrão de energia revisado por humano.');
    }

    private function responder(Request $request, Candidato $candidato, string $mensagem): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => $mensagem,
                'fragments' => ['secao-energia' => view('candidatos.partials._secao-energia', [
                    'candidato' => $this->candidatos->detalhar($candidato),
                ])->render()],
                'redirect' => null,
            ])->header('Cache-Control', 'private, no-store');
        }

        return redirect()->route('candidatos.show', $candidato)->with('status', $mensagem);
    }
}
