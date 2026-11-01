<?php

namespace App\Http\Controllers;

use App\Enums\MotivoExclusao;
use App\Http\Requests\AtualizarCandidatoRequest;
use App\Http\Requests\BuscarCandidatosRequest;
use App\Http\Requests\ExcluirCandidatoRequest;
use App\Models\Candidato;
use App\Services\Anotacoes\AnotacaoService;
use App\Services\CandidatoService;
use App\Services\Curriculo\CadastroCandidatoService;
use App\Services\Lgpd\ExclusaoCandidatoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

class CandidatoController extends Controller
{
    public function __construct(
        private CandidatoService $candidatos,
        private AnotacaoService $anotacoes,
    ) {}

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

    public function show(Candidato $candidato): Response
    {
        return response()->view('candidatos.show', [
            'candidato' => $this->candidatos->detalhar($candidato),
            'anotacoes' => $this->anotacoes->historico($candidato),
            'vagas' => $this->anotacoes->vagasParaAssociar(),
            'catalogo' => $this->candidatos->catalogoCompetencias(),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function edit(Candidato $candidato): Response
    {
        return response()->view('candidatos.edit', [
            'candidato' => $this->candidatos->detalhar($candidato),
            'catalogo' => $this->candidatos->catalogoCompetencias(),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function update(AtualizarCandidatoRequest $request, Candidato $candidato, CadastroCandidatoService $cadastro): RedirectResponse|JsonResponse
    {
        $cadastro->atualizar($candidato, $request->validated());
        $mensagem = 'Dados do candidato atualizados.';

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $mensagem,
                'fragments' => (object) [],
                'redirect' => route('candidatos.show', $candidato),
            ])->header('Cache-Control', 'private, no-store');
        }

        return redirect()->route('candidatos.show', $candidato)->with('status', $mensagem);
    }

    public function destroy(ExcluirCandidatoRequest $request, Candidato $candidato, ExclusaoCandidatoService $exclusao): RedirectResponse|JsonResponse
    {
        $exclusao->excluir($candidato, MotivoExclusao::from($request->validated('motivo')), $request->user());
        $mensagem = 'Dados do candidato excluídos definitivamente.';

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $mensagem,
                'fragments' => (object) [],
                'redirect' => route('candidatos.index'),
            ])->header('Cache-Control', 'private, no-store');
        }

        return redirect()->route('candidatos.index')->with('status', $mensagem);
    }
}
