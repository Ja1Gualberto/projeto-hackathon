<?php

namespace App\Http\Controllers;

use App\Enums\TipoCandidato;
use App\Http\Requests\ImportarCurriculoRequest;
use App\Services\Curriculo\ImportacaoCurriculoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use stdClass;

class ImportacaoCurriculoController extends Controller
{
    public function __construct(private ImportacaoCurriculoService $importacoes) {}

    public function create(): View
    {
        return view('importacoes.create');
    }

    public function store(ImportarCurriculoRequest $request): RedirectResponse|JsonResponse
    {
        $uuid = $this->importacoes->importar($request->file('arquivo'), TipoCandidato::from($request->validated('tipo')));
        $destino = route('importacoes.show', $uuid);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Currículo lido. Revise os dados antes de salvar.',
                'fragments' => new stdClass,
                'redirect' => $destino,
            ])->header('Cache-Control', 'private, no-store');
        }

        return redirect()->to($destino);
    }

    public function show(Request $request, string $importacao): Response
    {
        $dados = $this->importacoes->obter($importacao);

        if ($dados === null) {
            abort_if($request->expectsJson(), 404);

            return response()->view('importacoes.expirada', [], 404)->header('Cache-Control', 'private, no-store');
        }

        return response()->view('importacoes.show', [
            'uuid' => $importacao,
            'importacao' => $dados,
            ...$this->importacoes->dadosRevisao($dados),
        ])->header('Cache-Control', 'private, no-store');
    }
}
