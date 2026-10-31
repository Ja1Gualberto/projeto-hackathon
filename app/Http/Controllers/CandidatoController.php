<?php

namespace App\Http\Controllers;

use App\Http\Requests\BuscarCandidatosRequest;
use App\Models\Candidato;
use App\Services\CandidatoService;
use Illuminate\View\View;

class CandidatoController extends Controller
{
    public function __construct(private CandidatoService $candidatos) {}

    public function index(BuscarCandidatosRequest $request): View
    {
        $filtro = $request->filtro();

        return view('candidatos.index', [
            'filtro' => $filtro,
            'candidatos' => $this->candidatos->buscar($filtro),
            'catalogo' => $this->candidatos->catalogoCompetencias(),
        ]);
    }

    public function show(Candidato $candidato): View
    {
        return view('candidatos.show', ['candidato' => $this->candidatos->detalhar($candidato)]);
    }
}
