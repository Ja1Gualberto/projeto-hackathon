<?php

namespace App\Http\Controllers;

use App\Http\Requests\BuscarCandidatosRequest;
use App\Models\Candidato;
use App\Services\CandidatoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\View\View;

class CandidatoController extends Controller
{
    public function __construct(private CandidatoService $candidatos) {}

    public function index(BuscarCandidatosRequest $request): Response|JsonResponse
    {
        $filtro = $request->filtro();

        $dados = [
            'filtro' => $filtro,
            'candidatos' => $this->candidatos->buscar($filtro),
            'catalogo' => $this->candidatos->catalogoCompetencias(),
        ];

        if ($request->expectsJson()) {
            return response()->json([
                'message' => null,
                'fragments' => [
                    'resultados-candidatos' => view('candidatos.partials._resultados', $dados)->render(),
                ],
                'redirect' => null,
            ])->header('Cache-Control', 'private, no-store');
        }

        return response()->view('candidatos.index', $dados)->header('Cache-Control', 'private, no-store');
    }

    public function show(Candidato $candidato): View
    {
        return view('candidatos.show', ['candidato' => $this->candidatos->detalhar($candidato)]);
    }
}
