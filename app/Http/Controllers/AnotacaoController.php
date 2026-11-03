<?php

namespace App\Http\Controllers;

use App\Http\Requests\RegistrarAnotacaoRequest;
use App\Models\Candidato;
use App\Services\Anotacoes\AnotacaoService;
use App\Services\CandidatoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class AnotacaoController extends Controller
{
    public function __construct(
        private AnotacaoService $anotacoes,
        private CandidatoService $candidatos,
    ) {}

    /**
     * Anotação pela gestão do banco, com vaga opcional. Em AJAX devolve o histórico e as
     * competências atualizados; o formulário fica na página e só é limpo depois do sucesso.
     */
    public function store(RegistrarAnotacaoRequest $request, Candidato $candidato): RedirectResponse|JsonResponse
    {
        $this->anotacoes->registrar(
            $candidato,
            $this->anotacoes->vagaPorId($request->vagaId()),
            $request->user(),
            $request->validated('texto'),
            $request->competenciasObservadas(),
        );

        $mensagem = 'Anotação registrada.';

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $mensagem,
                'fragments' => [
                    'historico-anotacoes' => view('anotacoes._historico', ['anotacoes' => $this->anotacoes->historico($candidato)->take(3)])->render(),
                    'competencias-candidato' => view('candidatos.partials._competencias', [
                        'competencias' => $this->candidatos->detalhar($candidato)->competencias,
                    ])->render(),
                ],
                'redirect' => null,
            ])->header('Cache-Control', 'private, no-store');
        }

        return redirect()->route('candidatos.show', $candidato)->with('status', $mensagem);
    }
}
