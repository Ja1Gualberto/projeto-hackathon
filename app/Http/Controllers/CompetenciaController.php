<?php

namespace App\Http\Controllers;

use App\Enums\CategoriaCompetencia;
use App\Http\Requests\CriarCompetenciaRequest;
use App\Services\CompetenciaService;
use App\Services\Curriculo\ImportacaoCurriculoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class CompetenciaController extends Controller
{
    public function __construct(
        private CompetenciaService $competencias,
        private ImportacaoCurriculoService $importacoes,
    ) {}

    /**
     * Inclui no catálogo uma sugestão da leitura do currículo. Em AJAX devolve apenas a região
     * `catalogo-competencias`, que o cliente mescla sem apagar o que já foi editado na revisão.
     */
    public function store(CriarCompetenciaRequest $request): RedirectResponse|JsonResponse
    {
        $competencia = $this->competencias->adicionarAoCatalogo(
            $request->validated('nome'),
            CategoriaCompetencia::from($request->validated('categoria')),
        );

        if (filled($request->validated('importacao'))) {
            $this->importacoes->marcarCompetenciaAdicionada($request->validated('importacao'), $competencia->id);
        }

        $mensagem = 'Competência adicionada ao catálogo.';

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $mensagem,
                'fragments' => [
                    'catalogo-competencias' => view('candidatos.partials._catalogo-competencias', [
                        'catalogo' => $this->competencias->catalogo(),
                        'selecionadas' => [$competencia->id],
                        'extraidas' => [],
                    ])->render(),
                ],
                'redirect' => null,
            ])->header('Cache-Control', 'private, no-store');
        }

        return back()->with('status', $mensagem);
    }
}
